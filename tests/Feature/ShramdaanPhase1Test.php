<?php

namespace Tests\Feature;

use App\Models\LocalGovernment;
use App\Models\ShramdaanPassbook;
use App\Models\User;
use App\Support\Modules;
use App\Support\RolePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * श्रमदान module, phase 1: the on/off switch, the tole coordinator role, tole
 * assignment and the access boundary. Most of these tests are about what must
 * NOT happen — the module appearing while off, a coordinator reaching survey
 * data, anyone seeing toles outside their area.
 */
class ShramdaanPhase1Test extends TestCase
{
    use RefreshDatabase;

    private int $lgId;
    private array $w = [];
    private array $tole = [];

    protected function setUp(): void
    {
        parent::setUp();
        $p = DB::table('provinces')->insertGetId(['name_np' => 'प', 'name_en' => 'P', 'code' => 'KP1']);
        $d = DB::table('districts')->insertGetId(['province_id' => $p, 'name_np' => 'ज', 'name_en' => 'D', 'code' => 'KD1']);
        $this->lgId = DB::table('local_governments')->insertGetId([
            'district_id' => $d, 'name_np' => 'ग', 'name_en' => 'LG', 'type' => 'rural_municipality', 'code' => 'KL1', 'is_active' => true,
        ]);
        foreach ([1, 2] as $n) {
            $this->w[$n] = DB::table('wards')->insertGetId(['local_government_id' => $this->lgId, 'ward_number' => $n]);
            foreach (['क', 'ख'] as $t) {
                $this->tole["$n$t"] = DB::table('settlements')->insertGetId(['ward_id' => $this->w[$n], 'name_np' => "$t टोल $n"]);
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

    private function moduleOn(): void
    {
        LocalGovernment::find($this->lgId)->update(['modules_config' => [Modules::SHRAMDAAN => ['enabled' => true, 'settings' => []]]]);
    }

    private function household(string $tole, string $status = 'published'): int
    {
        $ward = (int) substr($tole, 0, 1);
        return DB::table('households')->insertGetId([
            'unique_code' => uniqid('HH'), 'ward_id' => $this->w[$ward], 'settlement_id' => $this->tole[$tole],
            'respondent_name' => 'x', 'status' => $status, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** @return array<string,array> tole name => row, from the module home page */
    private function toles(User $u): array
    {
        $res = $this->as($u)->get('/shramdaan');
        $res->assertOk();
        return collect($res->getOriginalContent()->getData()['page']['props']['toles'])->keyBy('name')->all();
    }

    // ── the switch ───────────────────────────────────────────────────────────

    public function test_module_is_off_by_default_and_its_pages_do_not_exist(): void
    {
        $this->assertFalse(Modules::enabled(Modules::SHRAMDAAN, $this->lgId));

        $this->as($this->user('tole_coordinator', [], ['1क']))->get('/shramdaan')->assertNotFound();
        $this->as($this->user('municipal_admin'))->get('/shramdaan')->assertNotFound();
    }

    public function test_super_admin_switches_it_on_and_the_change_is_audited(): void
    {
        $this->as($this->user('super_admin'))->patch('/admin/modules/shramdaan', [
            'enabled'  => true,
            'settings' => ['hours_per_day' => 8, 'min_age' => 16, 'tier_gold' => 15, 'tier_silver' => 8,
                'tier_bronze' => 3, 'incentive_text' => 'सिफारिस', 'unknown_key' => 'dropped'],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertTrue(Modules::enabled(Modules::SHRAMDAAN, $this->lgId));
        $this->assertSame('सिफारिस', Modules::settings(Modules::SHRAMDAAN, $this->lgId)['incentive_text']);
        $this->assertArrayNotHasKey('unknown_key', LocalGovernment::find($this->lgId)->modules_config['shramdaan']['settings']);
        $this->assertSame(1, DB::table('audit_logs')->where('event_type', 'module_config')->count());
    }

    public function test_only_the_super_admin_may_switch_modules(): void
    {
        $this->as($this->user('municipal_admin'))->get('/admin/modules')->assertForbidden();
        $this->as($this->user('municipal_admin'))->patch('/admin/modules/shramdaan', ['enabled' => true])->assertForbidden();
        $this->assertFalse(Modules::enabled(Modules::SHRAMDAAN, $this->lgId));
    }

    public function test_switch_respects_the_settings_lock(): void
    {
        LocalGovernment::find($this->lgId)->update(['settings_locked_at' => now()]);

        $this->as($this->user('super_admin'))->patch('/admin/modules/shramdaan', [
            'enabled' => true, 'settings' => ['hours_per_day' => 8, 'min_age' => 16, 'tier_gold' => 15, 'tier_silver' => 8, 'tier_bronze' => 3],
        ])->assertForbidden();
    }

    public function test_tiers_must_be_in_order(): void
    {
        $this->as($this->user('super_admin'))->patch('/admin/modules/shramdaan', [
            'enabled' => true, 'settings' => ['hours_per_day' => 8, 'min_age' => 16, 'tier_gold' => 3, 'tier_silver' => 8, 'tier_bronze' => 15],
        ])->assertSessionHasErrors(['settings.tier_gold', 'settings.tier_silver']);
    }

    // ── who sees which toles ─────────────────────────────────────────────────

    public function test_coordinator_sees_only_assigned_toles_and_only_published_households(): void
    {
        $this->moduleOn();
        $this->household('1क');
        $this->household('1क');
        $this->household('1क', 'draft');
        $this->household('2ख');

        $toles = $this->toles($this->user('tole_coordinator', [], ['1क']));

        $this->assertSame(['क टोल 1'], array_keys($toles));
        $this->assertSame(2, $toles['क टोल 1']['households'], 'drafts are not counted');
    }

    public function test_ward_admin_sees_every_tole_of_their_wards_only(): void
    {
        $this->moduleOn();
        $this->assertEqualsCanonicalizing(['क टोल 1', 'ख टोल 1'], array_keys($this->toles($this->user('ward_admin', [1]))));
    }

    public function test_municipal_admin_sees_all_toles(): void
    {
        $this->moduleOn();
        $this->assertCount(4, $this->toles($this->user('municipal_admin')));
    }

    public function test_enumerator_has_no_access_even_with_the_module_on(): void
    {
        $this->moduleOn();
        $this->as($this->user('enumerator', [1]))->get('/shramdaan')->assertForbidden();
    }

    // ── the coordinator reaches nothing else ─────────────────────────────────

    public function test_coordinator_cannot_reach_any_survey_or_admin_page(): void
    {
        $this->moduleOn();
        $c = $this->user('tole_coordinator', [], ['1क']);

        foreach (['/households', '/households/create', '/institutions', '/businesses', '/member-search',
                  '/review', '/admin', '/admin/users', '/admin/publish-queue', '/admin/feedback'] as $url) {
            $this->assertContains($this->as($c)->get($url)->status(), [403, 404], $url);
        }
    }

    public function test_coordinator_lands_on_shramdaan_or_is_told_it_is_off(): void
    {
        $c = $this->user('tole_coordinator', [], ['1क']);
        $this->as($c)->get('/dashboard')->assertForbidden();

        $this->moduleOn();
        $this->as($c)->get('/dashboard')->assertRedirect('/shramdaan');
    }

    // ── assigning coordinators ───────────────────────────────────────────────

    public function test_admin_creates_a_coordinator_with_toles_and_ward_ids_are_ignored(): void
    {
        $this->as($this->user('municipal_admin'))->post('/admin/users', [
            'name' => 'C', 'email' => 'c@x.test', 'password' => 'Pass-word-1', 'role' => 'tole_coordinator',
            'ward_ids' => [$this->w[1], $this->w[2]], 'settlement_ids' => [$this->tole['1क'], $this->tole['2ख']],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $c = User::where('email', 'c@x.test')->first();
        $this->assertSame('tole_coordinator', $c->role);
        $this->assertEqualsCanonicalizing([$this->tole['1क'], $this->tole['2ख']],
            $c->assignedSettlements()->pluck('settlements.id')->map(fn($i) => (int) $i)->all());
        $this->assertSame(0, $c->assignedWards()->count(), 'a coordinator never holds wards');
    }

    public function test_a_tole_from_another_municipality_is_rejected(): void
    {
        $other = DB::table('local_governments')->insertGetId([
            'district_id' => DB::table('districts')->value('id'), 'name_np' => 'अ', 'name_en' => 'O',
            'type' => 'rural_municipality', 'code' => 'KL2', 'is_active' => true,
        ]);
        $foreignTole = DB::table('settlements')->insertGetId([
            'ward_id' => DB::table('wards')->insertGetId(['local_government_id' => $other, 'ward_number' => 1]), 'name_np' => 'बाहिर',
        ]);

        $this->as($this->user('municipal_admin'))->post('/admin/users', [
            'name' => 'C', 'email' => 'c@x.test', 'password' => 'Pass-word-1', 'role' => 'tole_coordinator',
            'settlement_ids' => [$foreignTole],
        ])->assertSessionHasErrors('settlement_ids.0');
    }

    public function test_changing_role_away_from_coordinator_clears_toles(): void
    {
        $c = $this->user('tole_coordinator', [], ['1क']);

        $this->as($this->user('municipal_admin'))->patch("/admin/users/{$c->id}", [
            'name' => 'C', 'email' => $c->email, 'role' => 'enumerator', 'is_active' => true, 'ward_ids' => [$this->w[1]],
        ])->assertRedirect();

        $this->assertSame(0, $c->assignedSettlements()->count());
    }

    // ── permissions & storage ────────────────────────────────────────────────

    public function test_sites_with_saved_permission_lists_get_the_shramdaan_defaults(): void
    {
        // A ward_admin list saved before this release, without `_known`.
        LocalGovernment::find($this->lgId)->update(['role_permissions' => ['ward_admin' => ['households.view']]]);

        $this->assertTrue($this->user('ward_admin', [1])->hasPermission('shramdaan.verify'));
        $this->assertFalse($this->user('enumerator', [1])->hasPermission('shramdaan.view'));
        $this->assertSame(['shramdaan.view', 'shramdaan.edit'], RolePermissions::defaults()['tole_coordinator']);
    }

    public function test_citizenship_numbers_are_encrypted_at_rest_and_never_serialised(): void
    {
        $pb = ShramdaanPassbook::create([
            'household_id' => $this->household('1क'), 'passbook_no' => 'SD-HH-1', 'registered_on' => now(),
            'head_citizenship' => '03-01-74-02891',
        ]);

        $raw = DB::table('shramdaan_passbooks')->where('id', $pb->id)->value('head_citizenship');
        $this->assertStringNotContainsString('02891', $raw);
        $this->assertSame('03-01-74-02891', $pb->fresh()->head_citizenship);
        $this->assertArrayNotHasKey('head_citizenship', $pb->fresh()->toArray());
    }
}
