<?php

namespace Tests\Feature;

use App\Models\LocalGovernment;
use App\Models\User;
use App\Support\RolePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * सदस्य खोज — the read-only household lookup for field staff.
 *
 * What matters here is what the page must NOT show: unpublished households,
 * other wards' households, anything to a role without the permission. The
 * results carry full phone numbers, so each of those is a leak, not a cosmetic
 * bug.
 */
class MemberSearchTest extends TestCase
{
    use RefreshDatabase;

    private int $lgId;
    private array $wardIds = [];
    private array $toleIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $provinceId = DB::table('provinces')->insertGetId(['name_np' => 'प', 'name_en' => 'P', 'code' => 'MP1']);
        $districtId = DB::table('districts')->insertGetId([
            'province_id' => $provinceId, 'name_np' => 'ज', 'name_en' => 'D', 'code' => 'MD1',
        ]);
        $this->lgId = DB::table('local_governments')->insertGetId([
            'district_id' => $districtId, 'name_np' => 'ग', 'name_en' => 'LG',
            'type' => 'rural_municipality', 'code' => 'ML1', 'is_active' => true,
        ]);

        foreach ([1, 2] as $n) {
            $this->wardIds[$n] = DB::table('wards')->insertGetId([
                'local_government_id' => $this->lgId, 'ward_number' => $n,
            ]);
            foreach (['ख टोल', 'क टोल'] as $tole) {
                $this->toleIds[$n][$tole] = DB::table('settlements')->insertGetId([
                    'ward_id' => $this->wardIds[$n], 'name_np' => $tole,
                ]);
            }
        }

        DB::table('survey_options')->insert([
            'category' => 'relation_to_head', 'value' => 'son', 'label_ne' => 'छोरा',
            'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function user(string $role, array $wards = [1]): User
    {
        $user = User::create([
            'name'                => ucfirst($role),
            'email'               => uniqid($role, true) . '@example.test',
            'password'            => bcrypt('secret-not-used'),
            'role'                => $role,
            'local_government_id' => $this->lgId,
            'is_active'           => true,
        ]);
        foreach ($wards as $n) {
            DB::table('user_ward_assignments')->insert(['user_id' => $user->id, 'ward_id' => $this->wardIds[$n]]);
        }

        return $user->fresh();
    }

    private function household(string $code, int $ward, string $head, array $opts = []): int
    {
        $id = DB::table('households')->insertGetId([
            'unique_code'        => $code,
            'ward_id'            => $this->wardIds[$ward],
            'settlement_id'      => $this->toleIds[$ward][$opts['tole'] ?? 'क टोल'],
            'respondent_name'    => $head,
            'respondent_contact' => $opts['phone'] ?? null,
            'status'             => $opts['status'] ?? 'published',
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);
        DB::table('household_members')->insert([
            'household_id' => $id, 'full_name' => $head, 'is_household_head' => true,
            'relation_to_head' => 'self', 'sort_order' => 0,
        ]);
        foreach ($opts['members'] ?? [] as $i => $name) {
            DB::table('household_members')->insert([
                'household_id' => $id, 'full_name' => $name, 'is_household_head' => false,
                'relation_to_head' => 'son', 'gender' => 'male', 'age' => 12, 'sort_order' => $i + 1,
            ]);
        }

        return $id;
    }

    private function search(User $user, string $query)
    {
        return $this->actingAs($user)
            ->withoutMiddleware([
                \App\Http\Middleware\CheckLicense::class,
                \App\Http\Middleware\RequireTwoFactor::class,
            ])
            ->get('/member-search?' . $query);
    }

    /** @return array the Inertia props */
    private function props(User $user, string $query): array
    {
        $response = $this->search($user, $query);
        $response->assertOk();

        return $response->getOriginalContent()->getData()['page']['props'];
    }

    private function codes(User $user, string $query): array
    {
        return array_column($this->props($user, $query)['results']['data'], 'unique_code');
    }

    public function test_finds_by_household_number_head_member_name_and_phone(): void
    {
        $this->household('HH-W01-000001-1', 1, 'राम बहादुर थापा', [
            'phone' => '9841234567', 'members' => ['सीता थापा'],
        ]);
        $this->household('HH-W01-000002-1', 1, 'हरि प्रसाद शर्मा', ['phone' => '9800000000']);
        $enumerator = $this->user('enumerator');

        $this->assertSame(['HH-W01-000001-1'], $this->codes($enumerator, 'q=000001'));
        $this->assertSame(['HH-W01-000001-1'], $this->codes($enumerator, 'q=' . urlencode('राम बहादुर')));
        $this->assertSame(['HH-W01-000001-1'], $this->codes($enumerator, 'q=' . urlencode('सीता')));
        $this->assertSame(['HH-W01-000001-1'], $this->codes($enumerator, 'q=1234567'));
    }

    public function test_phone_typed_with_nepali_digits_or_country_code_still_matches(): void
    {
        $this->household('HH-W01-000001-1', 1, 'राम', ['phone' => '9841234567']);
        $enumerator = $this->user('enumerator');

        $this->assertSame(['HH-W01-000001-1'], $this->codes($enumerator, 'q=' . urlencode('९८४१२३४५६७')));
        $this->assertSame(['HH-W01-000001-1'], $this->codes($enumerator, 'q=' . urlencode('+977-984-1234567')));
    }

    public function test_result_carries_full_phone_and_marks_the_matched_member(): void
    {
        $this->household('HH-W01-000001-1', 1, 'राम थापा', ['phone' => '9841234567', 'members' => ['सीता थापा']]);

        $hh = $this->props($this->user('enumerator'), 'q=' . urlencode('सीता'))['results']['data'][0];

        $this->assertSame('9841234567', $hh['phone']);
        $this->assertSame('राम थापा', $hh['head_name']);
        $matched = array_values(array_filter($hh['members'], fn($m) => $m['matched']));
        $this->assertSame(['सीता थापा'], array_column($matched, 'name'));
        $this->assertSame('छोरा', $matched[0]['relation'], 'relation is shown as its Nepali label');
    }

    public function test_unpublished_households_never_appear(): void
    {
        foreach (['draft', 'submitted', 'ward_approved', 'rejected', 'escalated'] as $i => $status) {
            $this->household("HH-W01-00010{$i}-1", 1, 'राम', ['status' => $status]);
        }
        $this->household('HH-W01-000200-1', 1, 'राम', ['status' => 'published']);

        $this->assertSame(['HH-W01-000200-1'], $this->codes($this->user('municipal_admin'), 'q=' . urlencode('राम')));
    }

    public function test_enumerator_and_reviewer_only_see_their_assigned_wards(): void
    {
        $this->household('HH-W01-000001-1', 1, 'राम');
        $this->household('HH-W02-000001-1', 2, 'राम');

        foreach (['enumerator', 'ward_reviewer'] as $role) {
            $this->assertSame(['HH-W01-000001-1'], $this->codes($this->user($role, [1]), 'q=' . urlencode('राम')), $role);
        }

        // A ward_id outside the assignment is ignored, not obeyed.
        $this->assertSame(['HH-W01-000001-1'],
            $this->codes($this->user('enumerator', [1]), 'q=' . urlencode('राम') . '&ward_id=' . $this->wardIds[2]));
    }

    public function test_enumerator_sees_whole_ward_not_only_own_entries(): void
    {
        $this->household('HH-W01-000001-1', 1, 'राम');   // enumerator_id null: someone else's
        $this->assertSame(['HH-W01-000001-1'], $this->codes($this->user('enumerator'), 'q=' . urlencode('राम')));
    }

    public function test_all_wards_permission_widens_the_search(): void
    {
        $this->household('HH-W01-000001-1', 1, 'राम');
        $this->household('HH-W02-000001-1', 2, 'राम');
        LocalGovernment::whereKey($this->lgId)->update(['role_permissions' => json_encode([
            'enumerator' => ['members.search', 'members.search_all_wards'],
            '_known'     => RolePermissions::allPermissions(),
        ])]);

        $this->assertSame(['HH-W01-000001-1', 'HH-W02-000001-1'],
            $this->codes($this->user('enumerator', [1]), 'q=' . urlencode('राम')));
    }

    public function test_results_are_ordered_ward_then_tole_then_code(): void
    {
        $this->household('HH-W02-000001-1', 2, 'राम', ['tole' => 'क टोल']);
        $this->household('HH-W01-000009-1', 1, 'राम', ['tole' => 'ख टोल']);
        $this->household('HH-W01-000005-1', 1, 'राम', ['tole' => 'क टोल']);
        $this->household('HH-W01-000002-1', 1, 'राम', ['tole' => 'ख टोल']);

        $this->assertSame(
            ['HH-W01-000005-1', 'HH-W01-000002-1', 'HH-W01-000009-1', 'HH-W02-000001-1'],
            $this->codes($this->user('municipal_admin'), 'q=' . urlencode('राम')),
        );
    }

    public function test_like_wildcards_are_literal(): void
    {
        $this->household('HH-W01-000001-1', 1, 'राम');

        $this->assertSame([], $this->codes($this->user('municipal_admin'), 'q=' . urlencode('%%%')));
        $this->assertSame([], $this->codes($this->user('municipal_admin'), 'q=___'));
    }

    public function test_short_query_does_not_search(): void
    {
        $this->household('HH-W01-000001-1', 1, 'रा');

        $this->assertNull($this->props($this->user('enumerator'), 'q=ab')['results']);
        $this->assertSame(0, DB::table('audit_logs')->where('event_type', 'member_search')->count());
    }

    public function test_every_search_is_audited(): void
    {
        $this->household('HH-W01-000001-1', 1, 'राम');
        $user = $this->user('enumerator');

        $this->props($user, 'q=' . urlencode('राम'));

        $log = DB::table('audit_logs')->where('event_type', 'member_search')->first();
        $this->assertSame($user->id, (int) $log->user_id);
        // A single-ward user's ward is filled in for them.
        $this->assertSame(
            ['q' => 'राम', 'ward_id' => $this->wardIds[1], 'settlement_id' => null, 'page' => 1, 'results' => 1],
            json_decode($log->new_values, true));
    }

    public function test_tole_narrows_the_search(): void
    {
        $this->household('HH-W01-000001-1', 1, 'राम', ['tole' => 'क टोल']);
        $this->household('HH-W01-000002-1', 1, 'राम', ['tole' => 'ख टोल']);
        $tole = $this->toleIds[1]['ख टोल'];

        $this->assertSame(['HH-W01-000002-1'], $this->codes($this->user('municipal_admin'),
            'q=' . urlencode('राम') . "&ward_id={$this->wardIds[1]}&settlement_id={$tole}"));
    }

    public function test_tole_with_empty_search_lists_the_whole_tole_without_highlights(): void
    {
        $this->household('HH-W01-000001-1', 1, 'राम', ['tole' => 'ख टोल', 'members' => ['सीता']]);
        $this->household('HH-W01-000002-1', 1, 'हरि', ['tole' => 'ख टोल']);
        $this->household('HH-W01-000003-1', 1, 'श्याम', ['tole' => 'क टोल']);
        $tole = $this->toleIds[1]['ख टोल'];

        $data = $this->props($this->user('enumerator'), "q=&settlement_id={$tole}")['results']['data'];

        $this->assertSame(['HH-W01-000001-1', 'HH-W01-000002-1'], array_column($data, 'unique_code'));
        $this->assertFalse($data[0]['matched_code']);
        $this->assertFalse($data[0]['members'][1]['matched']);
    }

    public function test_tole_outside_the_chosen_ward_is_ignored(): void
    {
        $this->household('HH-W02-000001-1', 2, 'राम', ['tole' => 'ख टोल']);
        $otherWardTole = $this->toleIds[2]['ख टोल'];

        // Enumerator of ward 1 sends a ward 2 tole: ignored, so an empty box
        // searches nothing and a name search stays inside ward 1.
        $user = $this->user('enumerator', [1]);
        $this->assertNull($this->props($user, "q=&settlement_id={$otherWardTole}")['results']);
        $this->assertSame([], $this->codes($user, 'q=' . urlencode('राम') . "&settlement_id={$otherWardTole}"));
    }

    public function test_page_receives_only_toles_of_searchable_wards(): void
    {
        $toles = $this->props($this->user('enumerator', [1]), 'q=')['toles'];

        $this->assertEqualsCanonicalizing(array_values($this->toleIds[1]), array_map('intval', array_column($toles, 'id')));
    }

    public function test_role_without_the_permission_is_refused(): void
    {
        LocalGovernment::whereKey($this->lgId)->update(['role_permissions' => json_encode([
            'enumerator' => ['households.view'],
            '_known'     => RolePermissions::allPermissions(),
        ])]);

        $this->search($this->user('enumerator'), 'q=' . urlencode('राम'))->assertForbidden();
    }

    /**
     * A site whose super admin saved permissions before this feature existed
     * has an enumerator list without members.search. That is not a refusal —
     * nobody was asked — so the default applies.
     */
    public function test_permission_lists_saved_before_the_feature_get_the_default(): void
    {
        LocalGovernment::whereKey($this->lgId)->update(['role_permissions' => json_encode([
            'enumerator' => ['households.view'],
        ])]);

        $this->assertTrue($this->user('enumerator')->hasPermission('members.search'));
        $this->assertFalse($this->user('enumerator')->hasPermission('members.search_all_wards'));
        $this->assertFalse($this->user('enumerator')->hasPermission('households.create'),
            'everything else in a saved list is still taken as decided');
    }

    /** …and once the admin unticks it on the permissions page, it stays off. */
    public function test_unticking_after_the_upgrade_sticks(): void
    {
        LocalGovernment::whereKey($this->lgId)->update(['role_permissions' => json_encode([
            'enumerator'    => ['households.view'],
            'ward_reviewer' => ['review.view'],
        ])]);
        $super = $this->user('super_admin');

        $this->actingAs($super)
            ->withoutMiddleware([\App\Http\Middleware\CheckLicense::class, \App\Http\Middleware\RequireTwoFactor::class])
            ->patch(route('admin.role-permissions.update'), ['role' => 'enumerator', 'permissions' => ['households.view']])
            ->assertRedirect();

        $this->assertFalse($this->user('enumerator')->hasPermission('members.search'));
        $this->assertTrue($this->user('ward_reviewer')->hasPermission('members.search'),
            'the other role, not edited, keeps the default it had');
    }
}
