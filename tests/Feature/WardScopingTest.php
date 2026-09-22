<?php

namespace Tests\Feature;

use App\Models\Household;
use App\Models\User;
use App\Support\ScopesWardAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression tests for the ward a record gets filed under.
 *
 * Reported from the field: a ward admin assigned to ward 1 was shown every ward
 * in the municipality on the household and institution create forms, picked the
 * wrong one, and "got an error on submit".
 *
 * The error was the tail end of a worse problem. HouseholdPolicy::create() only
 * checks the households.create permission and never looks at the ward, and
 * store() validated `ward_id` with `exists:wards,id` — which any ward in the
 * table satisfies. So the record was created successfully in ward 2, with a
 * ward 2 unique_code, and only the redirect to the show page failed, because
 * the *view* policy does check assigned wards. The user saw an error and
 * assumed nothing was saved; in reality a record was stranded in a ward they
 * could no longer open, edit or delete.
 *
 * update() was worse still: it validated nothing, so an edit could move a
 * record into any ward in the system.
 *
 * These tests pin the rule at the layer that has to hold — the request — rather
 * than at the dropdown, which is only there to stop the mistake being made.
 */
class WardScopingTest extends TestCase
{
    use RefreshDatabase;
    use ScopesWardAccess;

    private int $lgId;
    private array $wardIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $provinceId = DB::table('provinces')->insertGetId([
            'name_np' => 'प', 'name_en' => 'P', 'code' => 'WP1',
        ]);
        $districtId = DB::table('districts')->insertGetId([
            'province_id' => $provinceId, 'name_np' => 'ज', 'name_en' => 'D', 'code' => 'WD1',
        ]);
        $this->lgId = DB::table('local_governments')->insertGetId([
            'district_id' => $districtId, 'name_np' => 'ग', 'name_en' => 'LG',
            'type' => 'rural_municipality', 'code' => 'WL1', 'is_active' => true,
        ]);

        foreach ([1, 2, 3] as $number) {
            $this->wardIds[$number] = DB::table('wards')->insertGetId([
                'local_government_id' => $this->lgId, 'ward_number' => $number,
            ]);
        }
    }

    private function user(string $role, array $wardNumbers = []): User
    {
        // users.role is an enum, so a hypothetical future role cannot be stored.
        // Persist a valid one and override the attribute in memory — the trait
        // reads it through the isX() helpers either way.
        $storedRole = in_array($role, ['super_admin', 'municipal_admin', 'ward_admin', 'ward_reviewer', 'enumerator'], true)
            ? $role
            : 'enumerator';

        $user = User::create([
            'name'                => ucfirst($role),
            'email'               => uniqid('u', true) . '@example.test',
            'password'            => bcrypt('secret-not-used'),
            'role'                => $storedRole,
            'local_government_id' => $this->lgId,
            'is_active'           => true,
        ]);

        foreach ($wardNumbers as $number) {
            DB::table('user_ward_assignments')->insert([
                'user_id' => $user->id,
                'ward_id' => $this->wardIds[$number],
            ]);
        }

        $user = $user->fresh();
        $user->role = $role;

        return $user;
    }

    /** Run the trait's helpers as the given user. */
    private function asUser(User $user, callable $fn)
    {
        $this->actingAs($user);
        return $fn();
    }

    // ── scopedWardIds ────────────────────────────────────────────────────────

    public function test_ward_admin_is_scoped_to_assigned_wards_only(): void
    {
        $user = $this->user('ward_admin', [1]);

        $ids = $this->asUser($user, fn() => $this->scopedWardIds());

        $this->assertSame([$this->wardIds[1]], $ids);
    }

    public function test_admin_gets_every_ward_in_the_municipality(): void
    {
        $user = $this->user('municipal_admin');

        $ids = $this->asUser($user, fn() => $this->scopedWardIds());

        $this->assertEqualsCanonicalizing(array_values($this->wardIds), $ids);
    }

    public function test_reviewer_with_several_wards_keeps_all_of_them(): void
    {
        $user = $this->user('ward_reviewer', [1, 2, 3]);

        $ids = $this->asUser($user, fn() => $this->scopedWardIds());

        $this->assertEqualsCanonicalizing(array_values($this->wardIds), $ids);
    }

    /**
     * An unrecognised role must get nothing, the same way the list queries do.
     * Getting this backwards would hand a future role every ward by default.
     */
    public function test_an_unknown_role_is_scoped_to_no_wards(): void
    {
        $user = $this->user('some_future_role');

        $ids = $this->asUser($user, fn() => $this->scopedWardIds());

        $this->assertSame([], $ids);
    }

    // ── defaultWardId ────────────────────────────────────────────────────────

    public function test_a_single_ward_user_gets_that_ward_preselected(): void
    {
        $user = $this->user('ward_admin', [2]);

        $default = $this->asUser($user, fn() => $this->defaultWardId());

        $this->assertSame($this->wardIds[2], $default);
    }

    public function test_a_multi_ward_user_gets_no_preselection(): void
    {
        $user = $this->user('ward_reviewer', [1, 2]);

        $default = $this->asUser($user, fn() => $this->defaultWardId());

        $this->assertNull($default, 'Someone with a real choice must still make it.');
    }

    public function test_editing_always_preselects_the_records_own_ward(): void
    {
        $user = $this->user('municipal_admin');

        $default = $this->asUser($user, fn() => $this->defaultWardId($this->wardIds[3]));

        $this->assertSame($this->wardIds[3], $default);
    }

    // ── selectableWards ──────────────────────────────────────────────────────

    public function test_the_dropdown_offers_only_permitted_wards(): void
    {
        $user = $this->user('ward_admin', [1]);

        $wards = $this->asUser($user, fn() => $this->selectableWards());

        $this->assertCount(1, $wards);
        $this->assertSame(1, $wards->first()->ward_number);
    }

    /**
     * If an admin reassigns wards while a draft exists, the record's own ward
     * must stay in the list — otherwise the select renders blank and the next
     * save silently moves the record.
     */
    public function test_the_records_own_ward_stays_selectable_when_out_of_scope(): void
    {
        $user = $this->user('ward_admin', [1]);

        $wards = $this->asUser($user, fn() => $this->selectableWards($this->wardIds[3]));

        $this->assertEqualsCanonicalizing(
            [$this->wardIds[1], $this->wardIds[3]],
            $wards->pluck('id')->all()
        );
    }

    // ── wardRule — the layer that actually has to hold ───────────────────────

    public function test_the_rule_accepts_an_assigned_ward(): void
    {
        $user = $this->user('ward_admin', [1]);

        $rule = $this->asUser($user, fn() => $this->wardRule());

        $this->assertTrue(
            validator(['ward_id' => $this->wardIds[1]], ['ward_id' => $rule])->passes()
        );
    }

    /** The reported bug: this used to pass, and the record was created. */
    public function test_the_rule_rejects_a_ward_the_user_is_not_assigned_to(): void
    {
        $user = $this->user('ward_admin', [1]);

        $rule = $this->asUser($user, fn() => $this->wardRule());

        $this->assertFalse(
            validator(['ward_id' => $this->wardIds[2]], ['ward_id' => $rule])->passes(),
            'A ward admin must not be able to file a record into someone else\'s ward.'
        );
    }

    public function test_the_rule_rejects_a_ward_that_does_not_exist(): void
    {
        $user = $this->user('municipal_admin');

        $rule = $this->asUser($user, fn() => $this->wardRule());

        $this->assertFalse(
            validator(['ward_id' => 999999], ['ward_id' => $rule])->passes()
        );
    }

    /** Saving an unchanged form must never fail, even after a reassignment. */
    public function test_the_rule_accepts_the_records_current_ward_on_edit(): void
    {
        $user = $this->user('ward_admin', [1]);

        $rule = $this->asUser($user, fn() => $this->wardRule($this->wardIds[3]));

        $this->assertTrue(
            validator(['ward_id' => $this->wardIds[3]], ['ward_id' => $rule])->passes()
        );
    }

    /** Allowing the current ward must not widen the rule to everything else. */
    public function test_allowing_the_current_ward_does_not_permit_other_wards(): void
    {
        $user = $this->user('ward_admin', [1]);

        $rule = $this->asUser($user, fn() => $this->wardRule($this->wardIds[3]));

        $this->assertFalse(
            validator(['ward_id' => $this->wardIds[2]], ['ward_id' => $rule])->passes()
        );
    }

    /** An unknown role has no wards, so every value must be refused. */
    public function test_an_unknown_role_can_file_nothing(): void
    {
        $user = $this->user('some_future_role');

        $rule = $this->asUser($user, fn() => $this->wardRule());

        foreach ($this->wardIds as $wardId) {
            $this->assertFalse(
                validator(['ward_id' => $wardId], ['ward_id' => $rule])->passes()
            );
        }
    }

    // ── End to end through the controller ────────────────────────────────────

    /**
     * These go through the real route so that a controller which forgets to use
     * wardRule() fails here. Licensing and 2FA are unrelated gates that answer
     * 402/302 before the controller runs, so they are stood down.
     */
    private function postHousehold(User $user, array $payload)
    {
        // HouseholdService reads these with `?:` rather than `??`, so they have
        // to be present even when empty — the real form always sends them.
        $payload += [
            'settlement_id'      => '',
            'respondent_contact' => '',
            'gps_lat'            => '',
            'gps_lng'            => '',
            'gps_altitude'       => '',
            'gps_accuracy'       => '',
            'photo_number'       => '',
            'remarks'            => '',
        ];

        return $this->actingAs($user)
            ->withoutMiddleware([
                \App\Http\Middleware\CheckLicense::class,
                \App\Http\Middleware\RequireTwoFactor::class,
            ])
            ->post('/households', $payload);
    }

    public function test_storing_a_household_in_an_unassigned_ward_is_rejected(): void
    {
        $user = $this->user('ward_admin', [1]);

        $this->postHousehold($user, [
            'ward_id'         => $this->wardIds[2],
            'respondent_name' => 'Wrong Ward',
        ])->assertSessionHasErrors('ward_id');

        $this->assertSame(0, Household::count(),
            'Nothing may be written when the ward is refused — the old code created the record anyway.');
    }

    public function test_storing_a_household_in_the_assigned_ward_succeeds(): void
    {
        $user = $this->user('ward_admin', [1]);

        $this->postHousehold($user, [
            'ward_id'         => $this->wardIds[1],
            'respondent_name' => 'Right Ward',
            'save_draft'      => true,
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, Household::where('ward_id', $this->wardIds[1])->count());
    }
}
