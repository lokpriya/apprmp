<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Services\InstitutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Rong's log, September 2026: "Column 'scholarship_students' cannot be null" and
 * "Column 'dropout_count' cannot be null", four times. An education row with
 * an empty box was sent as NULL into a NOT NULL DEFAULT 0 column, the insert
 * failed, and the education details were never stored. Staff counts share the
 * same column shape.
 */
class InstitutionEmptyCountsTest extends TestCase
{
    use RefreshDatabase;

    private function institution(): Institution
    {
        $p = DB::table('provinces')->insertGetId(['name_np' => 'प', 'name_en' => 'P', 'code' => 'EP1']);
        $d = DB::table('districts')->insertGetId(['province_id' => $p, 'name_np' => 'ज', 'name_en' => 'D', 'code' => 'ED1']);
        $lg = DB::table('local_governments')->insertGetId([
            'district_id' => $d, 'name_np' => 'ग', 'name_en' => 'LG', 'type' => 'rural_municipality', 'code' => 'EL1', 'is_active' => true,
        ]);
        $ward = DB::table('wards')->insertGetId(['local_government_id' => $lg, 'ward_number' => 1]);

        return Institution::create([
            'unique_code' => 'IN-0001-W01-00001', 'ward_id' => $ward, 'name_en' => 'School',
            'institution_type' => 'school', 'status' => 'draft',
        ]);
    }

    public function test_empty_education_and_staff_counts_are_saved_as_zero(): void
    {
        $inst = $this->institution();

        // Every main field as the validated form sends it — empty, as here.
        $form = array_fill_keys(['established_date', 'gps_accuracy', 'gps_altitude', 'gps_lat', 'gps_lng',
            'institution_type', 'name_np', 'ownership_type', 'photo_number', 'registration_number', 'remarks',
            'respondent_contact', 'respondent_name', 'settlement_id', 'survey_data', 'survey_date'], '');

        app(InstitutionService::class)->update($inst, $form + [
            'name_en'   => 'School',
            'education' => [[
                // the exact shape of the 2026-09-10 12:14:55 failure
                'grade' => 'ecd', 'male_students' => '3', 'female_students' => '4', 'dalit_students' => '0',
                'janajati_students' => '7', 'scholarship_students' => '', 'dropout_count' => '',
            ]],
            'staff' => [[
                'staff_category' => 'teacher', 'male_count' => '2', 'female_count' => '',
            ]],
        ]);

        $edu = DB::table('institution_education')->where('institution_id', $inst->id)->first();
        $this->assertNotNull($edu, 'the education row must be stored');
        $this->assertSame(3, (int) $edu->male_students);
        $this->assertSame(0, (int) $edu->scholarship_students);
        $this->assertSame(0, (int) $edu->dropout_count);

        $staff = DB::table('institution_staff')->where('institution_id', $inst->id)->first();
        $this->assertSame(2, (int) $staff->male_count);
        $this->assertSame(0, (int) $staff->female_count);
    }
}
