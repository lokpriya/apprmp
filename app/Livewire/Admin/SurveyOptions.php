<?php

namespace App\Livewire\Admin;

use App\Models\SurveyOption;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

class SurveyOptions extends Component
{
    public string  $activeTab      = 'household';  // 'household' | 'institution'
    public string  $activeCategory = '';
    public array   $options        = [];

    // Form fields
    public ?int    $editId      = null;
    public string  $formValue   = '';
    public string  $formLabelNe = '';
    public string  $formLabelEn = '';
    public int     $formSort    = 0;
    public bool    $formActive  = true;
    public bool    $showForm    = false;

    public string  $flash = '';
    public string  $errorMsg = '';

    public function mount(): void
    {
        abort_unless(Auth::user()?->isSuperAdmin(), 403);
        $this->activeCategory = array_key_first(SurveyOption::householdCategories());
        $this->loadOptions();
    }

    public function switchTab(string $tab): void
    {
        $this->activeTab = $tab;
        $cats = $tab === 'institution'
            ? SurveyOption::institutionCategories()
            : SurveyOption::householdCategories();
        $this->activeCategory = array_key_first($cats);
        $this->cancelForm();
        $this->loadOptions();
    }

    public function selectCategory(string $cat): void
    {
        $this->activeCategory = $cat;
        $this->cancelForm();
        $this->loadOptions();
    }

    public function loadOptions(): void
    {
        // Super admin sees ALL options for the category across all LGs
        $this->options = SurveyOption::where('category', $this->activeCategory)
            ->orderBy('sort_order')
            ->orderBy('label_ne')
            ->get()
            ->toArray();
    }

    public function openAdd(): void
    {
        $this->editId      = null;
        $this->formValue   = '';
        $this->formLabelNe = '';
        $this->formLabelEn = '';
        $this->formSort    = count($this->options) + 1;
        $this->formActive  = true;
        $this->showForm    = true;
    }

    public function openEdit(int $id): void
    {
        $this->errorMsg = '';
        $opt = SurveyOption::findOrFail($id);
        $this->editId      = $id;
        $this->formValue   = $opt->value;
        $this->formLabelNe = $opt->label_ne;
        $this->formLabelEn = $opt->label_en ?? '';
        $this->formSort    = $opt->sort_order;
        $this->formActive  = $opt->is_active;
        $this->showForm    = true;
    }

    public function save(): void
    {
        $this->errorMsg = '';
        $this->validate([
            'formValue'   => 'required|string|max:100|regex:/^[a-zA-Z0-9_\-]+$/',
            'formLabelNe' => 'required|string|max:200',
            'formLabelEn' => 'nullable|string|max:200',
            'formSort'    => 'required|integer|min:0',
        ], [
            'formValue.regex' => 'Value must contain only letters, numbers, underscores, or hyphens.',
        ]);

        if ($this->editId) {
            $opt = SurveyOption::findOrFail($this->editId);
            $opt->update([
                'value'      => $this->formValue,
                'label_ne'   => $this->formLabelNe,
                'label_en'   => $this->formLabelEn ?: null,
                'sort_order' => $this->formSort,
                'is_active'  => $this->formActive,
            ]);
            $this->flash = 'विकल्प अपडेट गरियो।';
        } else {
            // Super admin creates global options (null LG) — available system-wide
            SurveyOption::create([
                'category'            => $this->activeCategory,
                'value'               => $this->formValue,
                'label_ne'            => $this->formLabelNe,
                'label_en'            => $this->formLabelEn ?: null,
                'sort_order'          => $this->formSort,
                'is_active'           => $this->formActive,
                'local_government_id' => null,
            ]);
            $this->flash = 'नयाँ विकल्प थपियो।';
        }

        try {
            $this->clearAllCaches();
        } catch (\Throwable $e) {
            // Cache clear failure is non-fatal
        }
        $this->cancelForm();
        $this->loadOptions();
    }

    public function toggleActive(int $id): void
    {
        $opt = SurveyOption::findOrFail($id);
        $opt->update(['is_active' => !$opt->is_active]);
        $this->clearAllCaches();
        $this->loadOptions();
    }

    public function delete(int $id): void
    {
        $this->errorMsg = '';
        try {
            $opt = SurveyOption::findOrFail($id);
            $opt->delete();
            $this->clearAllCaches();
            $this->flash = 'विकल्प मेटाइयो।';
        } catch (\Throwable $e) {
            $this->errorMsg = 'मेटाउन सकिएन: ' . $e->getMessage();
        }
        $this->loadOptions();
    }

    public function cancelForm(): void
    {
        $this->showForm = false;
        $this->editId   = null;
        $this->reset(['formValue', 'formLabelNe', 'formLabelEn', 'formSort', 'formActive']);
        $this->resetValidation();
    }

    /** Clear caches for this category across all possible LG contexts. */
    private function clearAllCaches(): void
    {
        Cache::forget("survey_options.{$this->activeCategory}.global");
        Cache::forget("survey_options.all.global");
        // Also bust any LG-specific caches that include this category
        foreach (\App\Models\LocalGovernment::pluck('id') as $lgId) {
            Cache::forget("survey_options.{$this->activeCategory}.{$lgId}");
            Cache::forget("survey_options.all.{$lgId}");
        }
    }

    public function render()
    {
        $categories = $this->activeTab === 'institution'
            ? SurveyOption::institutionCategories()
            : SurveyOption::householdCategories();

        return view('livewire.admin.survey-options', [
            'categories' => $categories,
        ]);
    }
}
