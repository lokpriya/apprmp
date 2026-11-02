<?php

namespace Tests\Feature;

use App\Exports\ShramdaanExport;
use App\Models\ShramdaanEntry;
use App\Models\ShramdaanPassbook;
use App\Models\User;
use App\Support\BsDate;
use App\Support\Modules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

/**
 * श्रमदान module, phase 4: ward verification and the reports. Verification is
 * what makes a day count towards a family's श्रेणी, so the tests are mostly
 * about who may verify what.
 */
class ShramdaanPhase4Test extends TestCase
{
    use RefreshDatabase;

    private int $lgId;
    private array $w = [];
    private array $tole = [];
    private string $fy;

    protected function setUp(): void
    {
        parent::setUp();
        $p = DB::table('provinces')->insertGetId(['name_np' => 'प', 'name_en' => 'P', 'code' => 'VP1']);
        $d = DB::table('districts')->insertGetId(['province_id' => $p, 'name_np' => 'ज', 'name_en' => 'D', 'code' => 'VD1']);
        $this->lgId = DB::table('local_governments')->insertGetId([
            'district_id' => $d, 'name_np' => 'ग', 'name_en' => 'LG', 'type' => 'rural_municipality', 'code' => 'VL1', 'is_active' => true,
            'modules_config' => json_encode([Modules::SHRAMDAAN => ['enabled' => true, 'settings' => []]]),
        ]);
        foreach ([1, 2] as $n) {
            $this->w[$n] = DB::table('wards')->insertGetId(['local_government_id' => $this->lgId, 'ward_number' => $n]);
            $this->tole[$n] = DB::table('settlements')->insertGetId(['ward_id' => $this->w[$n], 'name_np' => "टोल $n"]);
        }
        $this->fy = BsDate::fiscalYear(now()->toDateString());
    }

    private function user(string $role, array $wards = [], array $toles = []): User
    {
        $u = User::create(['name' => "$role user", 'email' => uniqid($role, true) . '@x.test', 'password' => bcrypt('secret-pass-1'),
            'role' => $role, 'local_government_id' => $this->lgId, 'is_active' => true]);
        foreach ($wards as $n) DB::table('user_ward_assignments')->insert(['user_id' => $u->id, 'ward_id' => $this->w[$n]]);
        foreach ($toles as $n) DB::table('user_settlement_assignments')->insert(['user_id' => $u->id, 'settlement_id' => $this->tole[$n]]);
        return $u->fresh();
    }

    private function as(User $u)
    {
        return $this->actingAs($u)->withoutMiddleware([
            \App\Http\Middleware\CheckLicense::class, \App\Http\Middleware\RequireTwoFactor::class,
        ]);
    }

    /** A published family with an open passbook in ward $n; returns the passbook. */
    private function passbook(int $n): ShramdaanPassbook
    {
        $code = 'HH-' . uniqid();
        $hh = DB::table('households')->insertGetId([
            'unique_code' => $code, 'ward_id' => $this->w[$n], 'settlement_id' => $this->tole[$n],
            'respondent_name' => "परिवार $n", 'status' => 'published', 'created_at' => now(), 'updated_at' => now(),
        ]);
        return ShramdaanPassbook::create(['household_id' => $hh, 'passbook_no' => "SD-$code", 'registered_on' => now()]);
    }

    private function entry(ShramdaanPassbook $pb, float $days, ?User $by = null, string $status = ShramdaanEntry::RECORDED): ShramdaanEntry
    {
        return $pb->entries()->create([
            'member_name' => 'राम', 'project_name' => 'बाटो', 'work_date' => now()->toDateString(),
            'work_date_bs' => BsDate::format(now()->toDateString()), 'fiscal_year' => $this->fy,
            'hours' => $days * 8, 'days' => $days, 'status' => $status, 'entered_by' => $by?->id,
        ]);
    }

    private function props($response): array
    {
        $response->assertOk();
        return $response->getOriginalContent()->getData()['page']['props'];
    }

    // ── verification ─────────────────────────────────────────────────────────

    public function test_ward_admin_sees_and_verifies_only_entries_in_own_wards(): void
    {
        $coord = $this->user('tole_coordinator', [], [1]);
        $mine  = $this->entry($this->passbook(1), 1, $coord);
        $other = $this->entry($this->passbook(2), 1, $coord);
        $admin = $this->user('ward_admin', [1]);

        $queue = $this->props($this->as($admin)->get('/shramdaan/verify'))['entries']['data'];
        $this->assertSame([$mine->id], array_column($queue, 'id'));

        $this->as($admin)->post('/shramdaan/verify', ['ids' => [$mine->id, $other->id]])->assertRedirect();

        $this->assertSame(ShramdaanEntry::VERIFIED, $mine->fresh()->status);
        $this->assertSame($admin->id, (int) $mine->fresh()->verified_by);
        $this->assertSame(ShramdaanEntry::RECORDED, $other->fresh()->status, 'another ward is skipped, not verified');
        $this->assertSame(1, DB::table('audit_logs')->where('event_type', 'shramdaan_verify')->count());
    }

    public function test_nobody_verifies_their_own_entry(): void
    {
        $admin = $this->user('ward_admin', [1]);
        $own   = $this->entry($this->passbook(1), 1, $admin);

        $this->as($admin)->post('/shramdaan/verify', ['ids' => [$own->id]])->assertSessionHas('error');
        $this->assertSame(ShramdaanEntry::RECORDED, $own->fresh()->status);

        $this->as($this->user('ward_admin', [1]))->post('/shramdaan/verify', ['ids' => [$own->id]]);
        $this->assertSame(ShramdaanEntry::VERIFIED, $own->fresh()->status, 'a second person can');
    }

    public function test_coordinator_cannot_verify_or_see_reports(): void
    {
        $coord = $this->user('tole_coordinator', [], [1]);
        $e = $this->entry($this->passbook(1), 1, $this->user('ward_admin', [1]));

        $this->as($coord)->get('/shramdaan/verify')->assertForbidden();
        $this->as($coord)->post('/shramdaan/verify', ['ids' => [$e->id]])->assertForbidden();
        $this->as($coord)->get('/shramdaan/reports')->assertForbidden();
        $this->as($coord)->get('/shramdaan/reports/export')->assertForbidden();
        $this->assertSame(ShramdaanEntry::RECORDED, $e->fresh()->status);
    }

    public function test_only_verified_days_reach_the_tier(): void
    {
        $coord = $this->user('tole_coordinator', [], [1]);
        $pb = $this->passbook(1);
        $a = $this->entry($pb, 2, $coord);
        $b = $this->entry($pb, 1, $coord);
        $this->entry($pb, 5, $coord);   // stays unverified

        $this->as($this->user('ward_admin', [1]))->post('/shramdaan/verify', ['ids' => [$a->id, $b->id]]);

        $totals = $this->props($this->as($coord)->get("/shramdaan/households/{$pb->household_id}"))['totals'];
        $this->assertEquals(3.0, $totals['verified_days']);
        $this->assertEquals(5.0, $totals['pending_days']);
        $this->assertSame('bronze', $totals['tier']);
    }

    public function test_unverify_returns_an_entry_to_recorded_and_is_audited(): void
    {
        $coord = $this->user('tole_coordinator', [], [1]);
        $admin = $this->user('ward_admin', [1]);
        $e = $this->entry($this->passbook(1), 1, $coord, ShramdaanEntry::VERIFIED);

        $this->as($this->user('ward_admin', [2]))->post("/shramdaan/entries/{$e->id}/unverify")->assertNotFound();
        $this->as($admin)->post("/shramdaan/entries/{$e->id}/unverify")->assertRedirect();

        $this->assertSame(ShramdaanEntry::RECORDED, $e->fresh()->status);
        $this->assertNull($e->fresh()->verified_by);
        $this->assertSame(1, DB::table('audit_logs')->where('event_type', 'shramdaan_unverify')->count());

        // …and the coordinator can correct it again.
        $this->as($coord)->delete("/shramdaan/entries/{$e->id}")->assertRedirect();
    }

    public function test_home_page_shows_the_waiting_count_to_verifiers_only(): void
    {
        $coord = $this->user('tole_coordinator', [], [1]);
        $this->entry($this->passbook(1), 1, $coord);
        $this->entry($this->passbook(2), 1, $coord);

        $this->assertSame(1, $this->props($this->as($this->user('ward_admin', [1]))->get('/shramdaan'))['pendingCount']);
        $this->assertSame(0, $this->props($this->as($coord)->get('/shramdaan'))['pendingCount']);
    }

    // ── reports ──────────────────────────────────────────────────────────────

    public function test_report_counts_per_tole_within_scope(): void
    {
        $coord = $this->user('tole_coordinator', [], [1]);
        $gold = $this->passbook(1);
        $this->entry($gold, 15, $coord, ShramdaanEntry::VERIFIED);
        $this->entry($this->passbook(1), 2, $coord);            // pending only
        $this->entry($this->passbook(2), 9, $coord, ShramdaanEntry::VERIFIED);

        $rows = collect($this->props($this->as($this->user('ward_admin', [1]))->get('/shramdaan/reports'))['rows']);

        $this->assertCount(1, $rows, 'ward 1 admin sees ward 1 only');
        $r = $rows->first();
        $this->assertSame(2, $r['households']);
        $this->assertSame(2, $r['passbooks']);
        $this->assertSame(1, $r['active']);
        $this->assertEquals(15.0, $r['verified']);
        $this->assertEquals(2.0, $r['pending']);
        $this->assertSame([1, 0, 0], [$r['gold'], $r['silver'], $r['bronze']]);

        $all = collect($this->props($this->as($this->user('municipal_admin'))->get('/shramdaan/reports'))['rows']);
        $this->assertSame(1, $all->firstWhere('ward_number', 2)['silver']);
    }

    public function test_excel_export_has_no_citizenship_and_is_audited(): void
    {
        Excel::fake();
        $coord = $this->user('tole_coordinator', [], [1]);
        $pb = $this->passbook(1);
        $pb->update(['head_citizenship' => '03-01-74-02891']);
        $this->entry($pb, 1, $coord, ShramdaanEntry::VERIFIED);

        $this->as($this->user('ward_admin', [1]))->get('/shramdaan/reports/export')->assertOk();

        $file = 'shramdaan-' . str_replace('/', '-', $this->fy) . '.xlsx';
        Excel::assertDownloaded($file, function (ShramdaanExport $export) {
            $dump = json_encode(array_map(fn($s) => [$s->headings(), $s->array()], $export->sheets()), JSON_UNESCAPED_UNICODE);
            return !str_contains($dump, '02891') && !str_contains($dump, 'नागरिकता') && str_contains($dump, 'प्रमाणित');
        });
        $this->assertSame(1, DB::table('audit_logs')->where('event_type', 'shramdaan_export')->count());
    }
}
