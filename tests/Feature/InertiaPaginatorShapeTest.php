<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Support\InertiaPaginator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Pins the { data, links, meta } shape every list page is written against.
 *
 * Reported from the field: a ward admin could see only the first 20 of their
 * institutions, with no way to reach the rest, and the header read "जम्मा 0
 * संस्थाहरू" above a table that plainly had rows in it.
 *
 * Both symptoms were the same defect. The pages read `x.meta.total` and
 * `x.meta.last_page` — the shape an API resource collection produces — while
 * the controllers handed Inertia a bare LengthAwarePaginator, whose toArray()
 * is flat: `total` and `last_page` sit at the top level, and `links` is an
 * array of page buttons rather than { prev, next }.
 *
 * So `meta` was undefined. Nothing crashed, because the markup guards it:
 *
 *     {{ institutions.meta?.total ?? 0 }}                    → always 0
 *     v-if="institutions.meta && institutions.meta.last_page > 1"
 *                                                            → never rendered
 *
 * Seven pages were affected — the three list pages, the three review queues and
 * the publish queue — so no list in the application could be paged at all.
 *
 * These tests fail if the helper stops producing the keys the templates read, or
 * if a page count is ever reported from the current page rather than the query.
 */
class InertiaPaginatorShapeTest extends TestCase
{
    use RefreshDatabase;

    private int $wardId;

    protected function setUp(): void
    {
        parent::setUp();

        $provinceId = DB::table('provinces')->insertGetId([
            'name_np' => 'प', 'name_en' => 'P', 'code' => 'PP1',
        ]);
        $districtId = DB::table('districts')->insertGetId([
            'province_id' => $provinceId, 'name_np' => 'ज', 'name_en' => 'D', 'code' => 'PD1',
        ]);
        $lgId = DB::table('local_governments')->insertGetId([
            'district_id' => $districtId, 'name_np' => 'ग', 'name_en' => 'LG',
            'type' => 'rural_municipality', 'code' => 'PL1', 'is_active' => true,
        ]);
        $this->wardId = DB::table('wards')->insertGetId([
            'local_government_id' => $lgId, 'ward_number' => 1,
        ]);
    }

    private function makeInstitutions(int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            Institution::create([
                'unique_code' => 'IN-0001-W01-' . str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                'ward_id'     => $this->wardId,
                'name_en'     => 'Institution ' . $i,
                'status'      => 'published',
            ]);
        }
    }

    public function test_the_helper_produces_the_keys_the_templates_read(): void
    {
        $this->makeInstitutions(1);

        $payload = InertiaPaginator::format(Institution::query()->paginate(20));

        $this->assertArrayHasKey('data',  $payload);
        $this->assertArrayHasKey('links', $payload);
        $this->assertArrayHasKey('meta',  $payload);

        foreach (['current_page', 'from', 'last_page', 'per_page', 'to', 'total'] as $key) {
            $this->assertArrayHasKey($key, $payload['meta'], "meta.$key is read by the list pages");
        }

        // The review and publish queues page with <Link :href="x.links.prev">.
        foreach (['first', 'last', 'prev', 'next'] as $key) {
            $this->assertArrayHasKey($key, $payload['links'], "links.$key is read by the review queues");
        }
    }

    /**
     * The reported bug: 25 records, 20 on screen, header says 0 and no pager.
     */
    public function test_a_list_longer_than_one_page_reports_the_full_total(): void
    {
        $this->makeInstitutions(25);

        $payload = InertiaPaginator::format(Institution::query()->paginate(20));

        $this->assertCount(20, $payload['data'], 'One page holds 20 rows.');
        $this->assertSame(25, $payload['meta']['total'], 'The total counts every match, not just this page.');
        $this->assertSame(2,  $payload['meta']['last_page'], 'Two pages — the pager must render.');
        $this->assertSame(1,  $payload['meta']['from']);
        $this->assertSame(20, $payload['meta']['to']);
        $this->assertNotNull($payload['links']['next']);
        $this->assertNull($payload['links']['prev'], 'There is no page before the first.');
    }

    public function test_the_second_page_carries_the_remainder(): void
    {
        $this->makeInstitutions(25);

        $payload = InertiaPaginator::format(Institution::query()->paginate(20, ['*'], 'page', 2));

        $this->assertCount(5, $payload['data']);
        $this->assertSame(2,  $payload['meta']['current_page']);
        $this->assertSame(25, $payload['meta']['total']);
        $this->assertSame(21, $payload['meta']['from']);
        $this->assertSame(25, $payload['meta']['to']);
        $this->assertNotNull($payload['links']['prev']);
        $this->assertNull($payload['links']['next'], 'There is no page after the last.');
    }

    /** An empty result must not report a phantom page or crash the guards. */
    public function test_an_empty_list_is_well_formed(): void
    {
        $payload = InertiaPaginator::format(Institution::query()->paginate(20));

        $this->assertSame([], $payload['data']);
        $this->assertSame(0, $payload['meta']['total']);
        $this->assertSame(1, $payload['meta']['last_page']);
        $this->assertNull($payload['meta']['from']);
        $this->assertNull($payload['meta']['to']);
    }

    /** through() maps rows to arrays before the helper runs; both must survive. */
    public function test_transformed_rows_survive_the_helper(): void
    {
        $this->makeInstitutions(2);

        $payload = InertiaPaginator::format(Institution::query()
            ->paginate(20)
            ->through(fn ($i) => ['code' => $i->unique_code])
            );

        $this->assertSame(2, $payload['meta']['total']);
        $this->assertArrayHasKey('code', $payload['data'][0]);
        $this->assertArrayNotHasKey('name_en', $payload['data'][0]);
    }
}
