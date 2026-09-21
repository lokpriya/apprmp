<?php

namespace Tests\Feature;

use App\Services\GoogleSheetsSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression tests for survey_data['health'], which Google Sync never wrote.
 *
 * Reported from the live Rong site: every card on the स्वास्थ्य चेतना report read
 * "चयनित फिल्टरका लागि तथ्याङ्क उपलब्ध छैन।" although the questions had been asked
 * and answered by all 4,367 households. JSON_KEYS(survey_data) on that site
 * returned only ["social","decisions","hasLandInMunicipality","landParcels"] —
 * the sync wrote six sections and "health" was not one of them.
 *
 * Two of the eight answers were in fact arriving and being thrown away at the
 * last step: Utilities.handwashing went to household_utilities only, and
 * Economic.has_insurance went to survey_data.social.health_insurance, while the
 * report reads $.health.aware_handwashing and $.health.has_health_insurance.
 * The other six have no derived column at all and are read from optional
 * columns, so a municipality that has not added them still syncs cleanly.
 *
 * Kobo writes this group as choice *labels* and as a raw illness count, not as
 * the form's option values, so the mapping is asserted against the exact
 * strings found in Rong's export rather than invented ones.
 */
class GoogleSyncHealthMappingTest extends TestCase
{
    use RefreshDatabase;

    /** Call the private extractor directly — it is pure, given three rows. */
    private function extract(array $hhRow, ?array $utilities = null, ?array $economic = null): array
    {
        $service = app(GoogleSheetsSyncService::class);
        $method  = new \ReflectionMethod($service, 'extractHealthData');
        $method->setAccessible(true);

        return $method->invoke($service, $hhRow, $utilities, $economic);
    }

    private function seedTreatmentOptions(): void
    {
        foreach (['govt_hospital', 'hospital', 'health_post', 'private_clinic', 'traditional'] as $value) {
            DB::table('survey_options')->insert([
                'category'   => 'treatment_place',
                'value'      => $value,
                'label_ne'   => $value,
                'is_active'  => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /** The two answers that already arrived but were written to the wrong place. */
    public function test_handwashing_and_insurance_reach_the_health_section(): void
    {
        $health = $this->extract(
            [],
            ['handwashing' => 'soap_water'],
            ['has_insurance' => '1'],
        );

        $this->assertSame('yes', $health['aware_handwashing']);
        $this->assertSame('yes', $health['has_health_insurance']);

        $health = $this->extract([], ['handwashing' => 'none'], ['has_insurance' => '0']);

        $this->assertSame('no', $health['aware_handwashing']);
        $this->assertSame('no', $health['has_health_insurance']);
    }

    /** water_only still means they wash their hands; the card is yes/no awareness. */
    public function test_water_only_counts_as_aware(): void
    {
        $health = $this->extract([], ['handwashing' => 'water_only']);

        $this->assertSame('yes', $health['aware_handwashing']);
    }

    /** An unrecognised value is left out rather than guessed into a bucket. */
    public function test_unknown_handwashing_value_is_not_guessed(): void
    {
        $health = $this->extract([], ['handwashing' => 'something_nobody_seeded']);

        $this->assertArrayNotHasKey('aware_handwashing', $health);
    }

    /** Kobo records a count per year; the form and report use four buckets. */
    public function test_illness_count_is_bucketed(): void
    {
        $cases = [
            '0'  => 'rarely',
            '1'  => '1_2',
            '2'  => '1_2',
            '3'  => '3_5',
            '5'  => '3_5',
            '6'  => '6_plus',
            '20' => '6_plus',
        ];

        foreach ($cases as $raw => $expected) {
            $health = $this->extract(['yearly_illness_frequency' => $raw]);
            $this->assertSame($expected, $health['illness_frequency'], "illness count {$raw}");
        }
    }

    /** A sheet that already carries the form's own bucket values passes through. */
    public function test_illness_bucket_value_passes_through(): void
    {
        DB::table('survey_options')->insert([
            'category' => 'illness_frequency', 'value' => '3_5', 'label_ne' => '३–५ पटक',
            'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $health = $this->extract(['illness_frequency' => '3_5']);

        $this->assertSame('3_5', $health['illness_frequency']);
    }

    /** The exact treatment-place labels Kobo wrote in Rong's export. */
    public function test_kobo_treatment_labels_map_to_option_values(): void
    {
        $this->seedTreatmentOptions();

        $cases = [
            'स्वास्थ्य चौकी'            => 'health_post',
            'अस्पताल (निजि तथा सरकारी)' => 'hospital',
            'निजि क्लिनीक'              => 'private_clinic',
            'लामा, धामी, झाक्री'        => 'traditional',
            'परम्परागत स्थानीय बैद्य'   => 'traditional',
        ];

        foreach ($cases as $label => $expected) {
            $health = $this->extract(['first_treatment_attempt_at' => $label]);
            $this->assertSame($expected, $health['treatment_place'], $label);
        }
    }

    /**
     * The combined hospital answer must not be filed as सरकारी अस्पताल.
     *
     * 1,057 of Rong's households chose "अस्पताल (निजि तथा सरकारी)", which covers
     * both government and private hospitals. Folding it into govt_hospital would
     * report a quarter of the municipality as using government hospitals when the
     * survey never asked them to distinguish.
     */
    public function test_combined_hospital_answer_is_not_reported_as_government(): void
    {
        $this->seedTreatmentOptions();

        $health = $this->extract(['first_treatment_attempt_at' => 'अस्पताल (निजि तथा सरकारी)']);

        $this->assertNotSame('govt_hospital', $health['treatment_place']);
        $this->assertSame('hospital', $health['treatment_place']);
    }

    /** The four awareness questions, in both accepted column spellings. */
    public function test_awareness_questions_map_from_kobo_and_form_columns(): void
    {
        $health = $this->extract([
            'know_about_nutrition'                => 'हो अथवा छ',
            'know_about_nutrition_for_pregnant'   => 'होइन अथवा छैन',
            'know_about_iron_tablet_for_pregnant' => 'हो अथवा छ',
            'pregnant_women_at_home'              => 'होइन अथवा छैन',
        ]);

        $this->assertSame('yes', $health['aware_nutrition']);
        $this->assertSame('no',  $health['aware_pregnant_nutrition']);
        $this->assertSame('yes', $health['aware_iron_capsule']);
        $this->assertSame('no',  $health['has_pregnant_woman']);

        $health = $this->extract([
            'aware_nutrition'    => 'Yes',
            'has_pregnant_woman' => 'No',
        ]);

        $this->assertSame('yes', $health['aware_nutrition']);
        $this->assertSame('no',  $health['has_pregnant_woman']);
    }

    /** "थाहा छैन" is a real answer, not a "no". */
    public function test_dont_know_insurance_is_kept_distinct_from_no(): void
    {
        $health = $this->extract([], null, ['has_insurance' => 'थाहा छैन']);

        $this->assertSame('dont_know', $health['has_health_insurance']);
    }

    public function test_numeric_detail_fields_are_cast(): void
    {
        $health = $this->extract([
            'distance_health_institution' => '30',
            'pregnancy_in_month'          => '5',
            'age_of_pregnant_women'       => '27',
        ]);

        $this->assertSame(30, $health['time_to_health_center']);
        $this->assertSame(5,  $health['pregnancy_months']);
        $this->assertSame(27, $health['pregnant_age']);
    }

    /**
     * A sheet without any of these columns must produce no health section at all,
     * so the three municipalities that have not added them keep syncing.
     */
    public function test_absent_columns_produce_nothing(): void
    {
        $this->assertSame([], $this->extract(['unique_code' => 'HH-1'], [], []));
        $this->assertSame([], $this->extract([], null, null));
    }

    /** Blank cells must not be written as empty strings the report would count. */
    public function test_blank_cells_are_skipped(): void
    {
        $health = $this->extract([
            'yearly_illness_frequency'  => '',
            'first_treatment_attempt_at' => '',
            'know_about_nutrition'      => '',
        ], ['handwashing' => ''], ['has_insurance' => '']);

        $this->assertSame([], $health);
    }
}
