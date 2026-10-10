<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Services\CrossTabService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * freq() must merge rows that resolve to the same label, as tab() does.
 *
 * tab() gained this in September, after the development-index report on Rong
 * showed "राष्ट्रिय ग्रिड" three times — once with the counts and twice empty —
 * because optionLabels() deliberately maps Kobo alias keys onto the same label.
 * freq() sits beside it, is used by the same report pages, and was left
 * emitting one row per distinct value.
 *
 * Found on aamchowk, whose institution_type options include both
 * agro_vet_form and agri_vet_group under the identical Nepali label
 * "कृषि तथा पशुपालन समूह". With institutions recorded under each slug the
 * frequency list showed two rows of the same name, each with part of the
 * total, while the ward cross-tab above it showed one correct row.
 *
 * Counts must be summed rather than dropped: real records live under both.
 */
class CrossTabFreqDuplicateLabelTest extends TestCase
{
    use RefreshDatabase;

    private int $wardId;

    protected function setUp(): void
    {
        parent::setUp();

        $provinceId = DB::table('provinces')->insertGetId([
            'name_np' => 'प', 'name_en' => 'P', 'code' => 'FQ1',
        ]);
        $districtId = DB::table('districts')->insertGetId([
            'province_id' => $provinceId, 'name_np' => 'ज', 'name_en' => 'D', 'code' => 'FQ2',
        ]);
        $lgId = DB::table('local_governments')->insertGetId([
            'district_id' => $districtId, 'name_np' => 'ग', 'name_en' => 'LG',
            'type' => 'rural_municipality', 'code' => 'FQ3', 'is_active' => true,
        ]);
        $this->wardId = DB::table('wards')->insertGetId([
            'local_government_id' => $lgId, 'ward_number' => 1,
        ]);
    }

    private function make(string $type, int $count): void
    {
        static $n = 0;
        for ($i = 0; $i < $count; $i++) {
            Institution::create([
                'unique_code'      => sprintf('IN-0001-W01-%05d', ++$n),
                'ward_id'          => $this->wardId,
                'name_en'          => "Inst {$n}",
                'institution_type' => $type,
                'status'           => 'published',
            ]);
        }
    }

    /** The aamchowk case: two slugs, one label. */
    public function test_two_slugs_sharing_a_label_collapse_into_one_row(): void
    {
        $this->make('agro_vet_form', 28);
        $this->make('agri_vet_group', 7);

        $labels = [
            'agro_vet_form'  => 'कृषि तथा पशुपालन समूह',
            'agri_vet_group' => 'कृषि तथा पशुपालन समूह',   // identical, as on aamchowk
        ];

        $freq = CrossTabService::freq(
            Institution::where('status', 'published'),
            'institution_type',
            $labels,
            ''      // no cache
        );

        $this->assertCount(1, $freq, 'One label must produce one row.');
        $this->assertSame('कृषि तथा पशुपालन समूह', $freq[0]['label']);
        $this->assertSame(35, $freq[0]['count'], 'Counts are summed, not dropped: 28 + 7.');
    }

    /** Distinct labels must still produce distinct rows. */
    public function test_distinct_labels_are_not_merged(): void
    {
        $this->make('school', 30);
        $this->make('health_post', 4);

        $freq = CrossTabService::freq(
            Institution::where('status', 'published'),
            'institution_type',
            ['school' => 'विद्यालय', 'health_post' => 'स्वास्थ्य चौकी/अस्पताल'],
            ''
        );

        $this->assertCount(2, $freq);
        $this->assertSame(['विद्यालय', 'स्वास्थ्य चौकी/अस्पताल'], array_column($freq, 'label'));
    }

    /** Ordering is by count descending, and must survive the merge. */
    public function test_rows_stay_ordered_by_count_after_merging(): void
    {
        $this->make('tole_bikas', 5);
        $this->make('agro_vet_form', 4);
        $this->make('agri_vet_group', 9);   // merged total 13 — must come first

        $freq = CrossTabService::freq(
            Institution::where('status', 'published'),
            'institution_type',
            [
                'tole_bikas'     => 'टोल विकास संस्था',
                'agro_vet_form'  => 'कृषि तथा पशुपालन समूह',
                'agri_vet_group' => 'कृषि तथा पशुपालन समूह',
            ],
            ''
        );

        $this->assertSame([13, 5], array_column($freq, 'count'));
        $this->assertSame('कृषि तथा पशुपालन समूह', $freq[0]['label']);
    }

    /** A value with no option still appears, labelled by its raw slug. */
    public function test_unmapped_values_are_still_reported(): void
    {
        $this->make('school', 2);
        $this->make('some_unmapped_type', 3);

        $freq = CrossTabService::freq(
            Institution::where('status', 'published'),
            'institution_type',
            ['school' => 'विद्यालय'],
            ''
        );

        $this->assertCount(2, $freq);
        $this->assertSame('some_unmapped_type', $freq[0]['label'], 'Raw slug, but never silently dropped.');
        $this->assertSame(3, $freq[0]['count']);
    }
}
