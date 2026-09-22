<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The institution list: pagination, and the type and tole filters.
 *
 * Reported from the field: a ward admin could see only their first 20
 * institutions with no way to reach the rest, and finding a particular one
 * meant either knowing its code or typing its name. Wards 5 and 6 of Rong hold
 * 22 and 21 institutions, so those admins could not reach part of their own
 * ward's data at all.
 *
 * Pagination itself was already written on both sides — the controller calls
 * paginate(20) and the page has a full pager with page numbers. It never
 * rendered because of the paginator shape; see InertiaPaginatorShapeTest.
 *
 * These tests go through the real route so that the controller, the filters and
 * the response shape are checked together.
 */
class InstitutionListFiltersTest extends TestCase
{
    use RefreshDatabase;

    private int $lgId;
    private array $wardIds = [];
    private array $settlementIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $provinceId = DB::table('provinces')->insertGetId([
            'name_np' => 'प', 'name_en' => 'P', 'code' => 'FP1',
        ]);
        $districtId = DB::table('districts')->insertGetId([
            'province_id' => $provinceId, 'name_np' => 'ज', 'name_en' => 'D', 'code' => 'FD1',
        ]);
        $this->lgId = DB::table('local_governments')->insertGetId([
            'district_id' => $districtId, 'name_np' => 'ग', 'name_en' => 'LG',
            'type' => 'rural_municipality', 'code' => 'FL1', 'is_active' => true,
        ]);

        foreach ([1, 2] as $number) {
            $wardId = DB::table('wards')->insertGetId([
                'local_government_id' => $this->lgId, 'ward_number' => $number,
            ]);
            $this->wardIds[$number] = $wardId;
            $this->settlementIds[$number] = DB::table('settlements')->insertGetId([
                'ward_id' => $wardId, 'name_np' => 'टोल ' . $number,
            ]);
        }

        foreach ([['school', 'विद्यालय'], ['forest_group', 'वन उपभोक्ता समूह']] as [$value, $label]) {
            DB::table('survey_options')->insert([
                'category'   => 'institution_type',
                'value'      => $value,
                'label_ne'   => $label,
                'is_active'  => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function wardAdmin(int $wardNumber): User
    {
        $user = User::create([
            'name'                => 'Ward Admin ' . $wardNumber,
            'email'               => uniqid('wa', true) . '@example.test',
            'password'            => bcrypt('secret-not-used'),
            'role'                => 'ward_admin',
            'local_government_id' => $this->lgId,
            'is_active'           => true,
        ]);

        DB::table('user_ward_assignments')->insert([
            'user_id' => $user->id,
            'ward_id' => $this->wardIds[$wardNumber],
        ]);

        return $user->fresh();
    }

    private function institution(int $wardNumber, string $type, int $n, bool $withTole = true): void
    {
        Institution::create([
            'unique_code'      => sprintf('IN-0001-W%02d-%05d', $wardNumber, $n),
            'ward_id'          => $this->wardIds[$wardNumber],
            'settlement_id'    => $withTole ? $this->settlementIds[$wardNumber] : null,
            'name_en'          => "Institution {$wardNumber}-{$n}",
            'institution_type' => $type,
            'status'           => 'published',
        ]);
    }

    /** @return array the Inertia props of the institution list */
    private function listProps(User $user, string $query = ''): array
    {
        $response = $this->actingAs($user)
            ->withoutMiddleware([
                \App\Http\Middleware\CheckLicense::class,
                \App\Http\Middleware\RequireTwoFactor::class,
            ])
            ->get('/institutions' . $query);

        $response->assertOk();

        return $response->getOriginalContent()->getData()['page']['props'];
    }

    /**
     * The reported bug end to end: 25 institutions, 20 on screen, and before the
     * fix a header reading "जम्मा 0" with no way to reach the other five.
     */
    public function test_a_ward_with_more_than_one_page_can_be_paged(): void
    {
        $user = $this->wardAdmin(1);
        for ($n = 1; $n <= 25; $n++) {
            $this->institution(1, 'school', $n);
        }

        $first = $this->listProps($user)['institutions'];

        $this->assertCount(20, $first['data']);
        $this->assertSame(25, $first['meta']['total'], 'The header reads this number.');
        $this->assertSame(2,  $first['meta']['last_page'], 'Below 2 the pager stays hidden.');

        $second = $this->listProps($user, '?page=2')['institutions'];

        $this->assertCount(5, $second['data']);
        $this->assertSame(2, $second['meta']['current_page']);
    }

    public function test_filtering_by_organisation_type(): void
    {
        $user = $this->wardAdmin(1);
        for ($n = 1; $n <= 3; $n++)  $this->institution(1, 'school', $n);
        for ($n = 4; $n <= 10; $n++) $this->institution(1, 'forest_group', $n);

        $all = $this->listProps($user)['institutions'];
        $this->assertSame(10, $all['meta']['total']);

        $schools = $this->listProps($user, '?institution_type=school')['institutions'];
        $this->assertSame(3, $schools['meta']['total']);

        $forests = $this->listProps($user, '?institution_type=forest_group')['institutions'];
        $this->assertSame(7, $forests['meta']['total']);
    }

    public function test_filtering_by_tole(): void
    {
        $user = $this->wardAdmin(1);
        $this->institution(1, 'school', 1, withTole: true);
        $this->institution(1, 'school', 2, withTole: true);
        $this->institution(1, 'school', 3, withTole: false);

        $all = $this->listProps($user)['institutions'];
        $this->assertSame(3, $all['meta']['total']);

        $tole = $this->listProps($user, '?settlement_id=' . $this->settlementIds[1])['institutions'];
        $this->assertSame(2, $tole['meta']['total'], 'The one with no tole is excluded.');
    }

    public function test_type_and_tole_filters_combine(): void
    {
        $user = $this->wardAdmin(1);
        $this->institution(1, 'school', 1);
        $this->institution(1, 'forest_group', 2);

        $props = $this->listProps(
            $user,
            '?institution_type=school&settlement_id=' . $this->settlementIds[1]
        )['institutions'];

        $this->assertSame(1, $props['meta']['total']);
    }

    /**
     * The type dropdown is built from the records in scope, so it can never
     * offer a choice that returns an empty table.
     */
    public function test_the_type_filter_offers_only_types_present_in_scope(): void
    {
        $user = $this->wardAdmin(1);
        $this->institution(1, 'school', 1);
        $this->institution(2, 'forest_group', 2);   // another ward

        $props = $this->listProps($user);

        $this->assertSame(['school'], collect($props['types'])->pluck('value')->all());
        $this->assertSame('विद्यालय', $props['types'][0]['label'], 'Labelled in Nepali, not by slug.');
    }

    /** The tole dropdown is scoped the same way, for the same reason. */
    public function test_the_tole_filter_offers_only_toles_in_scope(): void
    {
        $user = $this->wardAdmin(1);
        $this->institution(1, 'school', 1);

        $props = $this->listProps($user);

        $this->assertSame(
            [$this->settlementIds[1]],
            collect($props['settlements'])->pluck('id')->all()
        );
    }

    /** A filter must not become a way around ward scoping. */
    public function test_filters_cannot_reach_another_wards_records(): void
    {
        $user = $this->wardAdmin(1);
        $this->institution(2, 'school', 1);

        $props = $this->listProps(
            $user,
            '?ward_id=' . $this->wardIds[2] . '&settlement_id=' . $this->settlementIds[2]
        )['institutions'];

        $this->assertSame(0, $props['meta']['total']);
    }

    /** The filter values survive the round trip so the selects stay set. */
    public function test_the_chosen_filters_are_echoed_back(): void
    {
        $user = $this->wardAdmin(1);
        $this->institution(1, 'school', 1);

        $props = $this->listProps($user, '?institution_type=school&settlement_id=' . $this->settlementIds[1]);

        $this->assertSame('school', $props['filters']['institution_type']);
        $this->assertSame((string) $this->settlementIds[1], (string) $props['filters']['settlement_id']);
    }
}
