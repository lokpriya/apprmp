<?php

namespace Tests\Feature;

use App\Models\Household;
use App\Models\LocalGovernment;
use App\Models\User;
use App\Support\RolePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Holes found by the permission audit of 2 November 2026. Each one was first
 * proved open by a test that made the forbidden change succeed; these are the
 * same scenarios, now asserting that the change is refused.
 */
class SecurityAuditFixesTest extends TestCase
{
    use RefreshDatabase;

    private int $lgId;
    private array $w = [];

    protected function setUp(): void
    {
        parent::setUp();
        $p = DB::table('provinces')->insertGetId(['name_np' => 'प', 'name_en' => 'P', 'code' => 'SP1']);
        $d = DB::table('districts')->insertGetId(['province_id' => $p, 'name_np' => 'ज', 'name_en' => 'D', 'code' => 'SD1']);
        $this->lgId = DB::table('local_governments')->insertGetId([
            'district_id' => $d, 'name_np' => 'ग', 'name_en' => 'LG', 'type' => 'rural_municipality', 'code' => 'SL1', 'is_active' => true,
        ]);
        foreach ([1, 2] as $n) {
            $this->w[$n] = DB::table('wards')->insertGetId(['local_government_id' => $this->lgId, 'ward_number' => $n]);
        }
    }

    private function user(string $role, array $wards = [1]): User
    {
        $u = User::create(['name' => $role, 'email' => uniqid($role, true) . '@x.test', 'password' => bcrypt('secret-pass-1'),
            'role' => $role, 'local_government_id' => $this->lgId, 'is_active' => true]);
        foreach ($wards as $n) DB::table('user_ward_assignments')->insert(['user_id' => $u->id, 'ward_id' => $this->w[$n]]);
        return $u->fresh();
    }

    private function as(User $u)
    {
        return $this->actingAs($u)->withoutMiddleware([
            \App\Http\Middleware\CheckLicense::class, \App\Http\Middleware\RequireTwoFactor::class,
        ]);
    }

    private function grant(string $role, array $perms): void
    {
        LocalGovernment::whereKey($this->lgId)->update(['role_permissions' => json_encode([
            $role => $perms, '_known' => RolePermissions::allPermissions(),
        ])]);
    }

    private function household(int $ward, string $status = 'draft', ?int $enumeratorId = null): Household
    {
        return Household::find(DB::table('households')->insertGetId([
            'unique_code' => uniqid('HH'), 'ward_id' => $this->w[$ward], 'respondent_name' => 'x',
            'status' => $status, 'enumerator_id' => $enumeratorId, 'created_at' => now(), 'updated_at' => now(),
        ]));
    }

    private function member(int $ward): int
    {
        return DB::table('household_members')->insertGetId([
            'household_id' => $this->household($ward, 'published')->id, 'full_name' => 'M', 'is_household_head' => true,
        ]);
    }

    private function business(int $ward, string $status, int $enumeratorId): int
    {
        $member = $this->member($ward);
        return DB::table('member_businesses')->insertGetId([
            'household_member_id' => $member,
            'household_id'        => DB::table('household_members')->where('id', $member)->value('household_id'),
            'ward_id' => $this->w[$ward], 'enumerator_id' => $enumeratorId, 'unique_code' => uniqid('BZ'),
            'business_name' => 'B', 'status' => $status, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    // ── 1. super admin account ───────────────────────────────────────────────

    public function test_municipal_admin_cannot_change_the_super_admin(): void
    {
        $super = $this->user('super_admin', []);
        $admin = $this->user('municipal_admin', []);
        $before = $super->password;

        $this->as($admin)->get("/admin/users/{$super->id}/edit")->assertForbidden();
        $this->as($admin)->patch("/admin/users/{$super->id}", [
            'name' => 'x', 'email' => 'taken@x.test', 'role' => 'enumerator', 'is_active' => false,
        ])->assertForbidden();
        $this->as($admin)->patch("/admin/users/{$super->id}/reset-password", [
            'password' => 'Taken-over-123', 'password_confirmation' => 'Taken-over-123',
        ])->assertForbidden();
        $this->as($admin)->patch("/admin/users/{$super->id}/toggle-active")->assertForbidden();
        $this->as($admin)->delete("/admin/users/{$super->id}")->assertForbidden();

        $super = $super->fresh();
        $this->assertNotNull($super);
        $this->assertSame('super_admin', $super->role);
        $this->assertSame($before, $super->password);
        $this->assertTrue((bool) $super->is_active);
    }

    public function test_municipal_admin_still_manages_ordinary_users(): void
    {
        $enumerator = $this->user('enumerator');

        $this->as($this->user('municipal_admin', []))->patch("/admin/users/{$enumerator->id}/reset-password", [
            'password' => 'New-pass-123', 'password_confirmation' => 'New-pass-123',
        ])->assertRedirect();

        $this->assertTrue(Hash::check('New-pass-123', $enumerator->fresh()->password));
    }

    /** The role list has no super_admin, so saving their own profile used to demote them. */
    public function test_super_admin_editing_a_super_admin_keeps_the_role(): void
    {
        $super = $this->user('super_admin', []);

        $this->as($super)->patch("/admin/users/{$super->id}", [
            'name' => 'Renamed', 'email' => $super->email, 'role' => 'enumerator', 'is_active' => false,
        ])->assertRedirect();

        $super = $super->fresh();
        $this->assertSame('Renamed', $super->name);
        $this->assertSame('super_admin', $super->role);
        $this->assertTrue((bool) $super->is_active, 'nobody can switch off their own account');
    }

    // ── 2. business ward ─────────────────────────────────────────────────────

    public function test_enumerator_cannot_file_a_business_in_another_ward(): void
    {
        $this->as($this->user('enumerator', [1]))
            ->post('/businesses', ['household_member_id' => $this->member(2), 'business_name' => 'B'])
            ->assertSessionHasErrors('household_member_id');

        $this->assertSame(0, DB::table('member_businesses')->count());
    }

    public function test_enumerator_can_file_a_business_in_own_ward(): void
    {
        $this->as($this->user('enumerator', [1]))
            ->post('/businesses', ['household_member_id' => $this->member(1), 'business_name' => 'B'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, DB::table('member_businesses')->count());
    }

    // ── 3. reviewer edit stays in their wards ───────────────────────────────

    public function test_reviewer_with_edit_permission_is_limited_to_own_wards(): void
    {
        $this->grant('ward_reviewer', ['households.view', 'households.edit', 'institutions.edit', 'businesses.edit']);
        $reviewer = $this->user('ward_reviewer', [1]);

        $this->assertTrue(Gate::forUser($reviewer)->allows('update', $this->household(1)));
        $this->assertFalse(Gate::forUser($reviewer)->allows('update', $this->household(2)));
        $this->as($reviewer)->get('/households/' . $this->household(2, 'submitted')->id . '/edit')->assertForbidden();
    }

    // ── 4. settings lock ─────────────────────────────────────────────────────

    public function test_settings_import_respects_the_lock(): void
    {
        LocalGovernment::whereKey($this->lgId)->update(['settings_locked_at' => now()]);
        $file = UploadedFile::fake()->createWithContent('s.json',
            json_encode(['version' => 1, 'general' => ['name_en' => 'CHANGED WHILE LOCKED']]));

        $this->as($this->user('municipal_admin', []))
            ->post('/admin/settings/import', ['file' => $file, 'sections' => ['general']])
            ->assertForbidden();

        $this->assertSame('LG', LocalGovernment::find($this->lgId)->name_en);
    }

    public function test_survey_options_respect_the_lock(): void
    {
        LocalGovernment::whereKey($this->lgId)->update(['settings_locked_at' => now()]);

        $this->as($this->user('super_admin', []))->post('/admin/survey-options', [
            'category' => 'gender', 'value' => 'x', 'label_ne' => 'x',
        ])->assertForbidden();

        $this->assertSame(0, DB::table('survey_options')->where('value', 'x')->count());
    }

    public function test_survey_rounds_respect_the_lock(): void
    {
        LocalGovernment::whereKey($this->lgId)->update(['settings_locked_at' => now()]);

        $this->as($this->user('municipal_admin', []))->post('/admin/settings/rounds', [])->assertForbidden();
    }

    // ── 6. business delete ───────────────────────────────────────────────────

    public function test_business_draft_can_be_deleted_and_member_flag_cleared(): void
    {
        $admin = $this->user('municipal_admin', []);
        $id = $this->business(1, 'draft', $admin->id);
        $memberId = DB::table('member_businesses')->where('id', $id)->value('household_member_id');
        DB::table('household_members')->where('id', $memberId)->update(['involveinbusiness' => true]);

        $this->as($admin)->delete("/businesses/{$id}")->assertRedirect('/businesses');

        $this->assertNotNull(DB::table('member_businesses')->where('id', $id)->value('deleted_at'));
        $this->assertSame(0, (int) DB::table('household_members')->where('id', $memberId)->value('involveinbusiness'));
    }

    public function test_business_that_is_not_a_draft_is_not_deleted(): void
    {
        $admin = $this->user('municipal_admin', []);
        $id = $this->business(1, 'published', $admin->id);

        $this->as($admin)->delete("/businesses/{$id}")->assertRedirect();

        $this->assertNull(DB::table('member_businesses')->where('id', $id)->value('deleted_at'));
    }

    public function test_enumerator_cannot_delete_a_business(): void
    {
        $enumerator = $this->user('enumerator', [1]);
        $id = $this->business(1, 'draft', $enumerator->id);

        $this->as($enumerator)->delete("/businesses/{$id}")->assertForbidden();
    }

    // ── 7. enumerators submit only their own drafts ──────────────────────────

    public function test_enumerator_bulk_submit_skips_other_enumerators_businesses(): void
    {
        $me    = $this->user('enumerator', [1]);
        $other = $this->user('enumerator', [1]);
        $mine   = $this->business(1, 'draft', $me->id);
        $theirs = $this->business(1, 'draft', $other->id);

        $this->as($me)->post('/businesses/bulk-submit', ['ids' => [$mine, $theirs]]);

        $this->assertSame('draft', DB::table('member_businesses')->where('id', $theirs)->value('status'));
    }

    public function test_enumerator_cannot_submit_another_enumerators_business(): void
    {
        $me    = $this->user('enumerator', [1]);
        $theirs = $this->business(1, 'draft', $this->user('enumerator', [1])->id);

        $this->as($me)->post("/businesses/{$theirs}/submit")->assertForbidden();
    }

    public function test_enumerator_bulk_submit_skips_other_enumerators_institutions(): void
    {
        $me    = $this->user('enumerator', [1]);
        $other = $this->user('enumerator', [1]);
        $theirs = DB::table('institutions')->insertGetId([
            'unique_code' => uniqid('IN'), 'ward_id' => $this->w[1], 'name_en' => 'I', 'institution_type' => 'school',
            'status' => 'draft', 'enumerator_id' => $other->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->as($me)->post('/institutions/bulk-submit', ['ids' => [$theirs]]);

        $this->assertSame('draft', DB::table('institutions')->where('id', $theirs)->value('status'));
    }
}
