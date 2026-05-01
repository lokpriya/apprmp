<?php

namespace App\Services;

use App\Models\SurveyOption;
use Illuminate\Support\Facades\Cache;

class SurveyValueValidator
{
    /** category => [value => true] for O(1) lookup */
    private array $valid = [];

    /** Maps import column name => survey_options.category */
    public const FIELD_MAP = [
        // Members
        'relation_to_head'         => 'relation_to_head',
        'ethnicity'                => 'ethnicity',
        'religion'                 => 'religion',
        'mother_tongue'            => 'mother_tongue',
        'marital_status'           => 'marital_status',
        'education_level'          => 'education_level',
        'occupation_type'          => 'occupation_type',
        'employment_status'        => 'employment_status',
        'disability_type'          => 'disability_type',
        'blood_group'              => 'blood_group',
        'residence_status'         => 'residence_status',
        // Utilities
        'water_source'             => 'water_source',
        'toilet_type'              => 'toilet_type',
        'sanitation_type'          => 'sanitation_type',
        'electricity_source'       => 'electricity_source',
        'cooking_fuel'             => 'cooking_fuel',
        'solid_waste_management'   => 'solid_waste_management',
        'internet_type'            => 'internet_type',
        // Housing
        'house_type'               => 'house_type',
        'roof_type'                => 'roof_type',
        'wall_type'                => 'wall_type',
        'foundation_type'          => 'foundation_type',
        'ownership_type'           => 'ownership_type',
        'house_condition'          => 'house_condition',
        // Economic
        'primary_income_source'    => 'income_source',
        'annual_income_range'      => 'annual_income_range',
        'annual_expenditure_range' => 'annual_expenditure_range',
        // Business
        'business_type'            => 'business_type',
        'business_field'           => 'business_field',
        'business_location'        => 'business_location',
    ];

    public function __construct(private readonly ?int $lgId = null)
    {
        $this->load();
    }

    private function load(): void
    {
        $cacheKey = 'survey_validator.' . ($this->lgId ?? 'global');

        $data = Cache::remember($cacheKey, 3600, function () {
            return SurveyOption::select('category', 'value')
                ->where(fn ($q) => $q->whereNull('local_government_id')
                    ->orWhere('local_government_id', $this->lgId))
                ->where('is_active', true)
                ->get()
                ->groupBy('category')
                ->map(fn ($items) => $items->pluck('value')->flip()->toArray())
                ->toArray();
        });

        $this->valid = $data;
    }

    /**
     * Validate one field value against its survey_options category.
     * Returns null if valid (or empty / unmapped). Returns an error array if invalid.
     */
    public function check(string $field, ?string $value, int $rowNum): ?array
    {
        if ($value === null || $value === '') return null;

        $category = self::FIELD_MAP[$field] ?? null;
        if (!$category) return null;                          // no mapping — free text, skip
        if (!isset($this->valid[$category])) return null;    // category not seeded yet — skip silently

        if (isset($this->valid[$category][$value])) return null; // ✓ valid

        return [
            'type'     => 'invalid_option',
            'row'      => $rowNum,
            'field'    => $field,
            'value'    => $value,
            'category' => $category,
        ];
    }

    /**
     * Validate multiple fields at once for a single row.
     * Returns array of error objects (may be empty). Does NOT return early on first error —
     * collects all invalid fields so the user can fix everything in one pass.
     */
    public function checkFields(array $fieldValues, int $rowNum): array
    {
        $errors = [];
        foreach ($fieldValues as $field => $value) {
            $err = $this->check($field, $value ?: null, $rowNum);
            if ($err) $errors[] = $err;
        }
        return $errors;
    }

    /** Returns all valid values for a field (used in error messages / template hints). */
    public function validValues(string $field): array
    {
        $category = self::FIELD_MAP[$field] ?? null;
        if (!$category || !isset($this->valid[$category])) return [];
        return array_keys($this->valid[$category]);
    }
}
