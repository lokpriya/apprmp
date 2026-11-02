<?php

namespace Tests\Feature;

use App\Models\LocalGovernment;
use App\Models\ShramdaanEntry;
use App\Models\ShramdaanPassbook;
use App\Models\User;
use App\Support\BsDate;
use App\Support\Modules;
use App\Support\RolePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * श्रमदान module, phase 2: a tole's families, the passbook, work entries, and
 * the ward's योजना list and coordinators.
 */
class ShramdaanPhase2Test extends TestCase
{
    use RefreshDatabase;

    private int $lgId;
    private array $w = [];
    private array $tole = [];

    protected function setUp(): void
    {
        parent::setUp();
        $p = DB::table('provinces')->insertGetId(['name_np' => 'प', 'name_en' => 'P', 'code' => 'QP1']);
        $d = DB::table('districts')->insertGetId(['province_id' => $p, 'name_np' => 'ज', 'name_en' => 'D', 'code' => 'QD1']);
        $this->lgId = DB::table('local_governments')->insertGetId([
            'district_id' => $d, 'name_np' => 'ग', 'name_en' => 'LG', 'type' => 'rural_municipality', 'code' => 'QL1', 'is_active' => true,
            'modules_config' => json_encode([Modules::SHRAMDAAN => ['enabled' => true, 'settings' => []]]),
        ]);
        foreach ([1, 2] as $n) {
            $this->w[$n] = DB::table('wards')->insertGetId(['local_government_id' => $this->lgId, 'ward_number' => $n]);
            foreach (['क', 'ख'] as $t) {
                $this->tole["$n$t"] = DB::table('settlements')->insertGetId(['ward_id' => $this->w[$n], 'name_np' => "$t$n"]);
            }
        }
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function user(string $role, array $wards = [], array $toles = []): User
    {
        $u = User::create(['name' => $role, 'email' => uniqid($role, true) . '@x.test', 'password' => bcrypt('secret-pass-1'),
            'role' => $role, 'local_government_id' => $this->lgId, 'is_active' => true]);
        foreach ($wards as $n) DB::table('user_ward_assignments')->insert(['user_id' => $u->id, 'ward_id' => $this->w[$n]]);
        foreach ($toles as $t) DB::table('user_settlement_assignments')->insert(['user_id' => $u->id, 'settlement_id' => $this->tole[$t]]);
        return $u->fresh();
    }

    private function as(User $u)
    {
        return $this->actingAs($u)->withoutMiddleware([
            \App\Http\Middleware\CheckLicense::class, \App\Http\Middleware\RequireTwoFactor::class,
        ]);
    }

    /** @return array{0:int,1:array<string,int>} household id, member ids by name */
    private function family(string $tole, array $members = ['राम' => 45, 'सीता' => 40, 'बाबु' => 10], string $status = 'published', ?string $surveyDate = null): array
    {
        $hh = DB::table('households')->insertGetId([
            'unique_code' => 'HH-' . uniqid(), 'ward_id' => $this->w[(int) substr($tole, 0, 1)], 'settlement_id' => $this->tole[$tole],
            'respondent_name' => array_key_first($members), 'status' => $status, 'survey_date' => $surveyDate ?? now()->toDateString(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $ids = [];
        $i = 0;
        foreach ($members as $name => $age) {
            $ids[$name] = DB::table('household_members')->insertGetId([
                'household_id' => $hh, 'full_name' => $name, 'age' => $age, 'is_household_head' => $i === 0,
                'relation_to_head' => $i === 0 ? 'self' : 'son', 'sort_order' => $i++,
            ]);
        }
        return [$hh, $ids];
    }

    private function props($response): array
    {
        $response->assertOk();
        return $response->getOriginalContent()->getData()['page']['props'];
    }

    private function opened(User $c, int $hh): ShramdaanPassbook
    {
        $this->as($c)->post("/shramdaan/households/{$hh}/open")->assertRedirect();
        return ShramdaanPassbook::where('household_id', $hh)->firstOrFail();
    }

    private function entry(User $c, int $hh, int $memberId, array $over = [])
    {
        return $this->as($c)->post("/shramdaan/households/{$hh}/entries", $over + [
            'work_date' => now()->subDays(3)->toDateString(), 'household_member_id' => $memberId,
            'project_name' => 'बाटो सरसफाइ', 'hours' => 6,
        ]);
    }

    // ── tole and passbook access ─────────────────────────────────────────────

    public function test_coordinator_lists_only_published_families_of_own_tole(): void
    {
        $c = $this->user('tole_coordinator', [], ['1क']);
        $this->family('1क', ['राम' => 45]);
        $this->family('1क', ['हरि' => 50], 'draft');

        $data = $this->props($this->as($c)->get("/shramdaan/toles/{$this->tole['1क']}"))['households']['data'];
        $this->assertSame(['राम'], array_column($data, 'head_name'));

        $this->as($c)->get("/shramdaan/toles/{$this->tole['1ख']}")->assertNotFound();
    }

    public function test_passbook_outside_the_coordinators_toles_or_unpublished_is_not_found(): void
    {
        $c = $this->user('tole_coordinator', [], ['1क']);
        [$other] = $this->family('2क');
        [$draft] = $this->family('1क', ['हरि' => 50], 'draft');

        $this->as($c)->get("/shramdaan/households/{$other}")->assertNotFound();
        $this->as($c)->get("/shramdaan/households/{$draft}")->assertNotFound();
        $this->as($c)->post("/shramdaan/households/{$other}/open")->assertNotFound();
    }

    public function test_viewing_does_not_create_a_passbook_opening_does(): void
    {
        $c = $this->user('tole_coordinator', [], ['1क']);
        [$hh] = $this->family('1क');

        $this->assertNull($this->props($this->as($c)->get("/shramdaan/households/{$hh}"))['passbook']);
        $this->assertSame(0, ShramdaanPassbook::count());

        $pb = $this->opened($c, $hh);
        $this->assertSame('SD-' . DB::table('households')->where('id', $hh)->value('unique_code'), $pb->passbook_no);
        $this->opened($c, $hh);
        $this->assertSame(1, ShramdaanPassbook::count(), 'opening twice keeps one passbook');
    }

    public function test_members_are_living_and_at_least_the_minimum_age_counted_from_today(): void
    {
        $c = $this->user('tole_coordinator', [], ['1क']);
        // Surveyed three years ago: the 14-year-old is 17 now and belongs on the passbook.
        [$hh, $ids] = $this->family('1क', ['राम' => 45, 'किशोर' => 14, 'बाबु' => 8], 'published', now()->subYears(3)->toDateString());
        DB::table('household_members')->insert(['household_id' => $hh, 'full_name' => 'स्वर्गीय', 'age' => 70, 'is_deceased' => true]);

        $members = collect($this->props($this->as($c)->get("/shramdaan/households/{$hh}"))['members'])->keyBy('name');

        $this->assertEqualsCanonicalizing(['राम', 'किशोर'], $members->keys()->all());
        $this->assertSame(17, $members['किशोर']['age']);
        $this->assertSame(48, $members['राम']['age']);
    }

    // ── details ──────────────────────────────────────────────────────────────

    public function test_details_are_saved_and_citizenship_is_encrypted(): void
    {
        $c = $this->user('tole_coordinator', [], ['1क']);
        [$hh, $ids] = $this->family('1क');
        $pb = $this->opened($c, $hh);

        $this->as($c)->patch("/shramdaan/households/{$hh}/details", [
            'head_citizenship' => '03-01-74-02891', 'certifying_officer' => 'किरण', 'remarks' => '',
            'members' => [['household_member_id' => $ids['सीता'], 'citizenship' => '03-01-74-04321', 'skill' => 'सरसफाइ', 'excluded' => false]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $raw = DB::table('shramdaan_member_details')->value('citizenship');
        $this->assertStringNotContainsString('04321', $raw);
        $this->assertSame('03-01-74-02891', $pb->fresh()->head_citizenship);
        $this->assertSame('सीता', DB::table('shramdaan_member_details')->value('member_name'), 'name kept as a snapshot');
    }

    public function test_details_refuse_a_member_of_another_family(): void
    {
        $c = $this->user('tole_coordinator', [], ['1क']);
        [$hh] = $this->family('1क');
        [, $otherIds] = $this->family('1क', ['अर्को' => 30]);
        $this->opened($c, $hh);

        $this->as($c)->patch("/shramdaan/households/{$hh}/details", [
            'members' => [['household_member_id' => $otherIds['अर्को'], 'skill' => 'x', 'excluded' => false]],
        ])->assertSessionHasErrors('members.0.household_member_id');
    }

    // ── entries ──────────────────────────────────────────────────────────────

    public function test_entry_gets_its_bs_date_fiscal_year_and_days_from_the_server(): void
    {
        $c = $this->user('tole_coordinator', [], ['1क']);
        [$hh, $ids] = $this->family('1क');
        $this->opened($c, $hh);
        $date = now()->subDays(3)->toDateString();

        $this->entry($c, $hh, $ids['सीता'], ['work_date' => $date, 'hours' => 6])->assertSessionHasNoErrors();

        $e = ShramdaanEntry::firstOrFail();
        $this->assertSame(BsDate::format($date), $e->work_date_bs);
        $this->assertSame(BsDate::fiscalYear($date), $e->fiscal_year);
        $this->assertSame('0.75', $e->days);
        $this->assertSame('सीता', $e->member_name);
        $this->assertSame(ShramdaanEntry::RECORDED, $e->status);
    }

    public function test_entry_rules(): void
    {
        $c = $this->user('tole_coordinator', [], ['1क']);
        [$hh, $ids] = $this->family('1क');
        [, $otherIds] = $this->family('1क', ['अर्को' => 30]);
        $this->opened($c, $hh);
        $otherWardProject = DB::table('shramdaan_projects')->insertGetId([
            'ward_id' => $this->w[2], 'name' => 'अर्को वडा', 'fiscal_year' => BsDate::fiscalYear(now()->toDateString()), 'is_active' => true,
        ]);

        $this->entry($c, $hh, $ids['राम'], ['work_date' => now()->addDay()->toDateString()])->assertSessionHasErrors('work_date');
        $this->entry($c, $hh, $otherIds['अर्को'])->assertSessionHasErrors('household_member_id');
        $this->entry($c, $hh, $ids['राम'], ['project_id' => $otherWardProject, 'project_name' => null])->assertSessionHasErrors('project_id');
        $this->entry($c, $hh, $ids['राम'], ['project_name' => ''])->assertSessionHasErrors('project_name');
        $this->entry($c, $hh, $ids['राम'], ['hours' => 30])->assertSessionHasErrors('hours');

        $this->assertSame(0, ShramdaanEntry::count());
    }

    public function test_a_listed_project_is_used_by_name(): void
    {
        $c = $this->user('tole_coordinator', [], ['1क']);
        [$hh, $ids] = $this->family('1क');
        $this->opened($c, $hh);
        $project = DB::table('shramdaan_projects')->insertGetId([
            'ward_id' => $this->w[1], 'name' => 'खानेपानी मर्मत', 'fiscal_year' => BsDate::fiscalYear(now()->toDateString()), 'is_active' => true,
        ]);

        $this->entry($c, $hh, $ids['राम'], ['project_id' => $project, 'project_name' => 'ignored'])->assertSessionHasNoErrors();

        $this->assertSame('खानेपानी मर्मत', ShramdaanEntry::first()->project_name);
    }

    public function test_verified_entries_are_locked_and_only_they_count(): void
    {
        $c = $this->user('tole_coordinator', [], ['1क']);
        [$hh, $ids] = $this->family('1क');
        $pb = $this->opened($c, $hh);
        $this->entry($c, $hh, $ids['राम'], ['hours' => 8]);
        $this->entry($c, $hh, $ids['सीता'], ['hours' => 8]);
        [$verified, $pending] = ShramdaanEntry::orderBy('id')->get()->all();
        $verified->update(['status' => ShramdaanEntry::VERIFIED, 'verified_at' => now()]);

        $this->as($c)->patch("/shramdaan/entries/{$verified->id}", ['work_date' => now()->subDay()->toDateString(),
            'household_member_id' => $ids['राम'], 'project_name' => 'x', 'hours' => 2])->assertStatus(422);
        $this->as($c)->delete("/shramdaan/entries/{$verified->id}")->assertStatus(422);
        $this->as($c)->delete("/shramdaan/entries/{$pending->id}")->assertRedirect();

        $totals = $this->props($this->as($c)->get("/shramdaan/households/{$hh}"))['totals'];
        $this->assertEquals(1.0, $totals['verified_days']);
        $this->assertEquals(0.0, $totals['pending_days']);
        $this->assertSame('participant', $totals['tier'], '1 verified day is below bronze (3)');
    }

    public function test_view_only_permission_cannot_write(): void
    {
        LocalGovernment::find($this->lgId)->update(['role_permissions' => [
            'tole_coordinator' => ['shramdaan.view'], '_known' => RolePermissions::allPermissions(),
        ]]);
        $c = $this->user('tole_coordinator', [], ['1क']);
        [$hh] = $this->family('1क');

        $this->as($c)->get("/shramdaan/households/{$hh}")->assertOk();
        $this->as($c)->post("/shramdaan/households/{$hh}/open")->assertForbidden();
    }

    // ── ward side ────────────────────────────────────────────────────────────

    public function test_coordinator_cannot_reach_the_ward_pages(): void
    {
        $c = $this->user('tole_coordinator', [], ['1क']);
        $this->as($c)->get('/shramdaan/projects')->assertForbidden();
        $this->as($c)->get('/shramdaan/coordinators')->assertForbidden();
    }

    public function test_ward_admin_adds_projects_only_in_own_ward(): void
    {
        $a = $this->user('ward_admin', [1]);
        $fy = BsDate::fiscalYear(now()->toDateString());

        $this->as($a)->post('/shramdaan/projects', ['ward_id' => $this->w[1], 'name' => 'बाटो', 'fiscal_year' => $fy])->assertSessionHasNoErrors();
        $this->as($a)->post('/shramdaan/projects', ['ward_id' => $this->w[2], 'name' => 'अर्को', 'fiscal_year' => $fy])->assertSessionHasErrors('ward_id');
        $this->as($a)->post('/shramdaan/projects', ['ward_id' => $this->w[1], 'settlement_id' => $this->tole['2क'], 'name' => 'x', 'fiscal_year' => $fy])
            ->assertSessionHasErrors('settlement_id');

        $this->assertSame(1, DB::table('shramdaan_projects')->count());
    }

    public function test_ward_admin_creates_a_coordinator_only_for_own_toles(): void
    {
        $a = $this->user('ward_admin', [1]);

        $this->as($a)->post('/shramdaan/coordinators', [
            'name' => 'C', 'email' => 'c@x.test', 'password' => 'Pass-word-1', 'settlement_ids' => [$this->tole['2क']],
        ])->assertSessionHasErrors('settlement_ids.0');

        $this->as($a)->post('/shramdaan/coordinators', [
            'name' => 'C', 'email' => 'c@x.test', 'password' => 'Pass-word-1', 'settlement_ids' => [$this->tole['1क']],
            'role' => 'municipal_admin',   // ignored: the role is fixed
        ])->assertSessionHasNoErrors();

        $c = User::where('email', 'c@x.test')->firstOrFail();
        $this->assertSame('tole_coordinator', $c->role);
        $this->assertSame([$this->tole['1क']], $c->assignedSettlements()->pluck('settlements.id')->map(fn($i) => (int) $i)->all());
    }

    public function test_ward_admin_changing_toles_keeps_the_coordinators_toles_elsewhere(): void
    {
        $c = $this->user('tole_coordinator', [], ['1क', '2क']);

        $this->as($this->user('ward_admin', [1]))->patch("/shramdaan/coordinators/{$c->id}/toles", [
            'settlement_ids' => [$this->tole['1ख']],
        ])->assertRedirect();

        $this->assertEqualsCanonicalizing([$this->tole['1ख'], $this->tole['2क']],
            $c->assignedSettlements()->pluck('settlements.id')->map(fn($i) => (int) $i)->all());
    }

    public function test_ward_admin_cannot_turn_another_user_into_anything(): void
    {
        $enumerator = $this->user('enumerator', [1]);

        $this->as($this->user('ward_admin', [1]))->patch("/shramdaan/coordinators/{$enumerator->id}/toles", [
            'settlement_ids' => [$this->tole['1क']],
        ])->assertNotFound();

        $this->assertSame(0, $enumerator->assignedSettlements()->count());
    }
}
