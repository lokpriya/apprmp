<?php

namespace Tests\Feature;

use App\Http\Controllers\InstitutionController;
use App\Models\SurveyOption;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Regression tests for institution_type validation.
 *
 * institution_type moved from a lookup table to survey_options, and for a while
 * InstitutionController::update() validated nothing at all — any string reached
 * the column, so a typo or a stale form value produced an institution that no
 * report could categorise and that no error ever surfaced. The rule must accept
 * exactly the options the active LG can actually choose from.
 */
class InstitutionTypeValidationTest extends TestCase
{
    use RefreshDatabase;

    private function ruleFor(?int $lgId): array
    {
        // Resolved through the container: the controller takes a constructor dependency.
        return (new ReflectionMethod(InstitutionController::class, 'institutionTypeRule'))
            ->invoke(app(InstitutionController::class), $lgId);
    }

    private function passes(?int $lgId, mixed $value): bool
    {
        return Validator::make(
            ['institution_type' => $value],
            ['institution_type' => $this->ruleFor($lgId)]
        )->passes();
    }

    /**
     * survey_options.local_government_id is a real foreign key and an LG requires
     * a district, which requires a province — so the whole chain has to exist.
     * Returns the generated id rather than assuming one.
     */
    private function localGovernment(string $suffix): int
    {
        $provinceId = DB::table('provinces')->insertGetId([
            'name_np' => "परीक्षण प्रदेश {$suffix}",
            'name_en' => "Test Province {$suffix}",
            'code' => "TP{$suffix}",
        ]);

        $districtId = DB::table('districts')->insertGetId([
            'province_id' => $provinceId,
            'name_np' => "परीक्षण जिल्ला {$suffix}",
            'name_en' => "Test District {$suffix}",
            'code' => "TD{$suffix}",
        ]);

        return DB::table('local_governments')->insertGetId([
            'district_id' => $districtId,
            'name_np' => "परीक्षण गाउँपालिका {$suffix}",
            'name_en' => "Test Rural Municipality {$suffix}",
            'type' => 'rural_municipality',
            'code' => "TL{$suffix}",
            'is_active' => true,
        ]);
    }

    private function option(array $attributes): SurveyOption
    {
        return SurveyOption::create(array_merge([
            'category' => 'institution_type',
            'value' => 'school',
            'label_ne' => 'विद्यालय',
            'is_active' => true,
            'local_government_id' => null,
        ], $attributes));
    }

    public function test_a_global_option_is_accepted(): void
    {
        $this->option(['value' => 'school']);

        $this->assertTrue($this->passes(1, 'school'));
    }

    public function test_an_unknown_value_is_rejected(): void
    {
        $this->option(['value' => 'school']);

        $this->assertFalse($this->passes(1, 'skool'), 'A typo must not reach the column.');
        $this->assertFalse($this->passes(1, '<script>alert(1)</script>'));
    }

    /** The field is optional — an institution may be recorded before its type is known. */
    public function test_null_is_accepted(): void
    {
        $this->option(['value' => 'school']);

        $this->assertTrue($this->passes(1, null));
    }

    public function test_an_option_belonging_to_this_local_government_is_accepted(): void
    {
        $lgId = $this->localGovernment('A');
        $this->option(['value' => 'local_coop', 'local_government_id' => $lgId]);

        $this->assertTrue($this->passes($lgId, 'local_coop'));
    }

    /**
     * The scoping that matters: three LGs share one codebase, and one LG's custom
     * option must not become selectable for another.
     */
    public function test_another_local_governments_option_is_rejected(): void
    {
        $ourLgId = $this->localGovernment('A');
        $otherLgId = $this->localGovernment('B');
        $this->option(['value' => 'local_coop', 'local_government_id' => $otherLgId]);

        $this->assertFalse($this->passes($ourLgId, 'local_coop'));
    }

    public function test_a_deactivated_option_is_rejected(): void
    {
        $this->option(['value' => 'school', 'is_active' => false]);

        $this->assertFalse($this->passes(1, 'school'), 'Deactivating an option must stop new records using it.');
    }

    /**
     * The rule keeps an explicit length bound alongside Rule::in, so validation
     * fails cleanly rather than the database truncating the value on write.
     */
    public function test_the_rule_bounds_the_value_length(): void
    {
        $this->assertContains('max:100', $this->ruleFor(1));
        $this->assertContains('string', $this->ruleFor(1));
    }
}
