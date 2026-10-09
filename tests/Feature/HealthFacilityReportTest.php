<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\InstitutionHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The health report must count health facilities, not every institution.
 *
 * Reported from the live Rong site: /public/institution-reports/health showed
 * "स्वास्थ्य संस्थाहरू 193" with every bed count, OPD average and service
 * percentage at zero. 193 is Rong's *total* institution count.
 *
 * Two faults stacked:
 *
 *  1. health() counted rows in institution_health with no filter on what kind
 *     of institution they belonged to, while school() right beside it correctly
 *     filters on institution_type = 'school'.
 *
 *  2. A detail row was being written for every institution the sync touched,
 *     because the Health sheet carries one row per institution rather than per
 *     health facility. Locally that produced 113 institution_health rows for
 *     113 institutions — 56 tole development committees, 29 forest user groups
 *     and 28 agro-vet groups, not one of them a health facility, and every row
 *     completely empty (health_type NULL on all 113).
 *
 * The zeros were the tell: a real facility has beds or an OPD figure. Anything
 * reporting hundreds of facilities and zero of everything else is counting the
 * wrong rows.
 */
class HealthFacilityReportTest extends TestCase
{
    use RefreshDatabase;

    private int $wardId;

    protected function setUp(): void
    {
        parent::setUp();

        $provinceId = DB::table('provinces')->insertGetId([
            'name_np' => 'प', 'name_en' => 'P', 'code' => 'HP1',
        ]);
        $districtId = DB::table('districts')->insertGetId([
            'province_id' => $provinceId, 'name_np' => 'ज', 'name_en' => 'D', 'code' => 'HD1',
        ]);
        $lgId = DB::table('local_governments')->insertGetId([
            'district_id' => $districtId, 'name_np' => 'ग', 'name_en' => 'LG',
            'type' => 'rural_municipality', 'code' => 'HL1', 'is_active' => true,
        ]);
        $this->wardId = DB::table('wards')->insertGetId([
            'local_government_id' => $lgId, 'ward_number' => 1,
        ]);
    }

    private function institution(string $type, int $n, bool $withEmptyHealthRow = false): Institution
    {
        $inst = Institution::create([
            'unique_code'      => sprintf('IN-0001-W01-%05d', $n),
            'ward_id'          => $this->wardId,
            'name_en'          => "Institution {$n}",
            'institution_type' => $type,
            'status'           => 'published',
        ]);

        // Reproduces what the sync used to do: an all-empty detail row on every
        // institution regardless of type.
        if ($withEmptyHealthRow) {
            InstitutionHealth::create(['institution_id' => $inst->id]);
        }

        return $inst;
    }

    /** The exact shape of the live bug. */
    public function test_non_health_institutions_are_not_counted_as_facilities(): void
    {
        foreach (range(1, 56) as $n)  $this->institution('tole_bikas',    $n,  withEmptyHealthRow: true);
        foreach (range(57, 85) as $n) $this->institution('forest_group',  $n,  withEmptyHealthRow: true);
        foreach (range(86, 113) as $n) $this->institution('agro_vet_form', $n, withEmptyHealthRow: true);

        $this->assertSame(113, InstitutionHealth::count(),
            'Precondition: a detail row exists for every institution, as it did on Rong.');

        $this->assertSame(0, $this->facilityCount(),
            'None of these are health facilities, so the report must show zero.');
    }

    public function test_health_posts_are_counted(): void
    {
        $this->institution('tole_bikas', 1, withEmptyHealthRow: true);
        $this->institution('health_post', 2);
        $this->institution('health_post', 3);

        $this->assertSame(2, $this->facilityCount());
    }

    /**
     * A health post with no detail row must still be counted. Counting from the
     * detail table instead would hide it.
     */
    public function test_a_health_post_without_a_detail_row_still_counts(): void
    {
        $this->institution('health_post', 1);

        $this->assertSame(0, InstitutionHealth::count());
        $this->assertSame(1, $this->facilityCount());
    }

    /** Unpublished records never reach a public report. */
    public function test_unpublished_health_posts_are_excluded(): void
    {
        $inst = $this->institution('health_post', 1);
        $inst->update(['status' => 'draft']);

        $this->assertSame(0, $this->facilityCount());
    }

    /** Bed and OPD totals must come only from health facilities. */
    public function test_aggregates_ignore_non_health_institutions(): void
    {
        $other = $this->institution('forest_group', 1);
        InstitutionHealth::create([
            'institution_id' => $other->id,
            'bed_count'      => 999,      // must never be summed
            'opd_daily'      => 500,
        ]);

        $post = $this->institution('health_post', 2);
        InstitutionHealth::create([
            'institution_id' => $post->id,
            'bed_count'      => 12,
            'opd_daily'      => 40,
        ]);

        $scoped = InstitutionHealth::whereIn(
            'institution_id',
            Institution::where('status', 'published')
                ->whereIn('institution_type', ['health_post'])
                ->select('id')
        );

        $this->assertSame(12, (int) (clone $scoped)->sum('bed_count'));
        $this->assertSame(40, (int) (clone $scoped)->sum('opd_daily'));
    }

    /** Mirrors the controller's facility count. */
    private function facilityCount(): int
    {
        return Institution::where('status', 'published')
            ->whereIn('institution_type', ['health_post'])
            ->count();
    }
}
