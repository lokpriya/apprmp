<?php

namespace App\Http\Controllers\Admin;

use App\Exports\SurveyOptionsTemplateExport;
use App\Http\Controllers\Controller;
use App\Imports\SurveyOptionsImport;
use App\Models\LocalGovernment;
use App\Models\SurveyOption;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class SurveyOptionsController extends Controller
{
    public function index(Request $request): \Inertia\Response
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $allCategories         = SurveyOption::categories();
        $householdCategories   = SurveyOption::householdCategories();
        $institutionCategories = SurveyOption::institutionCategories();
        $businessCategories    = SurveyOption::businessCategories();

        $activeCategory = $request->get('category', array_key_first($householdCategories));

        // Ensure the category key is valid; fall back to first household category
        if (!array_key_exists($activeCategory, $allCategories)) {
            $activeCategory = array_key_first($householdCategories);
        }

        $fieldMap    = $this->categoryFieldMap();
        $usageKnown  = array_key_exists($activeCategory, $fieldMap);
        $usageCounts = [];

        if ($usageKnown) {
            [$table, $column] = $fieldMap[$activeCategory];
            $usageCounts = DB::table($table)
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->selectRaw("`{$column}` as val, COUNT(*) as cnt")
                ->groupBy($column)
                ->pluck('cnt', 'val')
                ->toArray();
        }

        $options = SurveyOption::where('category', $activeCategory)
            ->orderBy('sort_order')
            ->orderBy('label_ne')
            ->get()
            ->map(fn($o) => [
                'id'                  => $o->id,
                'value'               => $o->value,
                'label_ne'            => $o->label_ne,
                'label_en'            => $o->label_en,
                'sort_order'          => $o->sort_order,
                'is_active'           => $o->is_active,
                'local_government_id' => $o->local_government_id,
                'usage_count'         => $usageKnown ? ($usageCounts[$o->value] ?? 0) : null,
                'usage_known'         => $usageKnown,
            ]);

        return Inertia::render('Admin/SurveyOptions', [
            'householdCategories'   => $householdCategories,
            'householdSections'     => SurveyOption::householdCategorySections(),
            'institutionCategories' => $institutionCategories,
            'institutionSections'   => SurveyOption::institutionCategorySections(),
            'businessCategories'    => $businessCategories,
            'businessSections'      => SurveyOption::businessCategorySections(),
            'activeCategory'        => $activeCategory,
            'activeCategoryLabel'   => $allCategories[$activeCategory] ?? $activeCategory,
            'options'               => $options,
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $data = $request->validate([
            'category'  => 'required|string',
            'value'     => 'required|string|max:100|regex:/^[a-zA-Z0-9_\-]+$/',
            'label_ne'  => 'required|string|max:200',
            'label_en'  => 'nullable|string|max:200',
            'sort_order'=> 'required|integer|min:0',
            'is_active' => 'boolean',
        ], ['value.regex' => 'Value must contain only letters, numbers, underscores, or hyphens.']);

        SurveyOption::create([
            'category'            => $data['category'],
            'value'               => $data['value'],
            'label_ne'            => $data['label_ne'],
            'label_en'            => $data['label_en'] ?? null,
            'sort_order'          => $data['sort_order'],
            'is_active'           => $data['is_active'] ?? true,
            'local_government_id' => null,
        ]);

        $this->clearCaches($data['category']);

        return redirect()->route('admin.survey-options', ['category' => $data['category']])
            ->with('success', 'नयाँ विकल्प थपियो।');
    }

    public function update(Request $request, SurveyOption $surveyOption)
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $data = $request->validate([
            'value'     => 'required|string|max:100|regex:/^[a-zA-Z0-9_\-]+$/',
            'label_ne'  => 'required|string|max:200',
            'label_en'  => 'nullable|string|max:200',
            'sort_order'=> 'required|integer|min:0',
            'is_active' => 'boolean',
        ], ['value.regex' => 'Value must contain only letters, numbers, underscores, or hyphens.']);

        $surveyOption->update([
            'value'      => $data['value'],
            'label_ne'   => $data['label_ne'],
            'label_en'   => $data['label_en'] ?? null,
            'sort_order' => $data['sort_order'],
            'is_active'  => $data['is_active'] ?? $surveyOption->is_active,
        ]);

        $this->clearCaches($surveyOption->category);

        return redirect()->route('admin.survey-options', ['category' => $surveyOption->category])
            ->with('success', 'विकल्प अपडेट गरियो।');
    }

    public function toggle(SurveyOption $surveyOption)
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $surveyOption->update(['is_active' => !$surveyOption->is_active]);
        $this->clearCaches($surveyOption->category);

        return redirect()->route('admin.survey-options', ['category' => $surveyOption->category])
            ->with('success', 'स्थिति परिवर्तन गरियो।');
    }

    public function destroy(SurveyOption $surveyOption)
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);
        $category = $surveyOption->category;
        $surveyOption->delete();
        $this->clearCaches($category);

        return redirect()->route('admin.survey-options', ['category' => $category])
            ->with('success', 'विकल्प मेटाइयो।');
    }

    public function bulkDestroy(Request $request)
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $data = $request->validate([
            'ids'      => 'required|array|min:1|max:500',
            'ids.*'    => 'integer',
            'category' => 'required|string',
        ]);

        // Only delete options that actually belong to the specified category (safety check)
        $deleted = SurveyOption::whereIn('id', $data['ids'])
            ->where('category', $data['category'])
            ->delete();

        $this->clearCaches($data['category']);

        return redirect()
            ->route('admin.survey-options', ['category' => $data['category']])
            ->with('success', "{$deleted} विकल्प मेटाइयो।");
    }

    public function reorder(Request $request)
    {
        abort_unless(auth()->user()?->isAdmin() || auth()->user()?->isSuperAdmin(), 403);

        $data = $request->validate([
            'category'           => 'required|string',
            'order'              => 'required|array|min:1',
            'order.*.id'         => 'required|integer',
            'order.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($data['order'] as $item) {
            SurveyOption::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        $this->clearCaches($data['category']);

        return back();
    }

    public function downloadTemplate()
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        return Excel::download(new SurveyOptionsTemplateExport(), 'survey-options-template.xlsx');
    }

    public function import(Request $request)
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:5120',
        ], [
            'file.required' => 'Excel फाइल आवश्यक छ।',
            'file.mimes'    => 'xlsx, xls वा csv फाइल मात्र मान्य छ।',
            'file.max'      => 'फाइलको आकार 5MB भन्दा बढी हुनु हुँदैन।',
        ]);

        $import = new SurveyOptionsImport();

        try {
            Excel::import($import, $request->file('file'));
        } catch (\Exception $e) {
            return back()->withErrors(['file' => 'फाइल पढ्न सकिएन: ' . $e->getMessage()]);
        }

        $category = $request->get('category');

        return redirect()
            ->route('admin.survey-options', array_filter(['category' => $category]))
            ->with('import_result', [
                'inserted' => $import->inserted,
                'updated'  => $import->updated,
                'skipped'  => $import->skipped,
                'errors'   => array_slice($import->errors, 0, 50), // cap error list
            ]);
    }

    private function categoryFieldMap(): array
    {
        return [
            // household_members (VARCHAR columns)
            'relation_to_head'  => ['household_members', 'relation_to_head'],
            'marital_status'    => ['household_members', 'marital_status'],
            'blood_group'       => ['household_members', 'blood_group'],
            'disability_type'   => ['household_members', 'disability_type'],
            'education_level'   => ['household_members', 'education_level'],
            'occupation_type'   => ['household_members', 'occupation_type'],
            'employment_status' => ['household_members', 'employment_status'],
            'residence_status'  => ['household_members', 'residence_status'],
            // household_housing (VARCHAR columns)
            'roof_type'         => ['household_housing', 'roof_type'],
            'wall_type'         => ['household_housing', 'wall_type'],
            'foundation_type'   => ['household_housing', 'foundation_type'],
            'ownership_type'    => ['household_housing', 'ownership_type'],
            'house_condition'   => ['household_housing', 'house_condition'],
            // household_utilities (VARCHAR columns)
            'water_source'       => ['household_utilities', 'water_source'],
            'toilet_type'        => ['household_utilities', 'toilet_type'],
            'sanitation_type'    => ['household_utilities', 'sanitation_type'],
            'electricity_source' => ['household_utilities', 'electricity_source'],
            'cooking_fuel'       => ['household_utilities', 'cooking_fuel'],
            'internet_type'      => ['household_utilities', 'internet_type'],
            // institutions (VARCHAR — was ENUM, converted)
            'inst_ownership_type' => ['institutions', 'ownership_type'],
            // member_businesses
            'business_location' => ['member_businesses', 'business_location'],
            'business_type'     => ['member_businesses', 'business_type'],
            'business_field'    => ['member_businesses', 'business_field'],
        ];
    }

    private function clearCaches(string $category): void
    {
        Cache::forget("survey_options.{$category}.global");
        Cache::forget("survey_options.all.global");
        foreach (LocalGovernment::pluck('id') as $lgId) {
            Cache::forget("survey_options.{$category}.{$lgId}");
            Cache::forget("survey_options.all.{$lgId}");
        }
    }
}
