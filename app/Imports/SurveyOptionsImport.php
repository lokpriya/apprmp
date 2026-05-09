<?php

namespace App\Imports;

use App\Models\SurveyOption;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\SkipsUnknownSheets;

class SurveyOptionsImport implements WithMultipleSheets, SkipsUnknownSheets
{
    /** @var SurveyOptionSheetImport[] */
    private array $sheets;

    public function __construct()
    {
        $validCategories = array_keys(SurveyOption::categories());
        $this->sheets = [
            new SurveyOptionSheetImport($validCategories),
            new SurveyOptionSheetImport($validCategories),
            new SurveyOptionSheetImport($validCategories),
        ];
    }

    public function sheets(): array
    {
        // Sheets 0–2 are data (घरधुरी, संस्था, व्यवसाय).
        // Sheet 3 (Categories reference) is skipped via SkipsUnknownSheets.
        return [
            0 => $this->sheets[0],
            1 => $this->sheets[1],
            2 => $this->sheets[2],
        ];
    }

    public function __get(string $name): mixed
    {
        return match ($name) {
            'inserted' => array_sum(array_map(fn($s) => $s->inserted, $this->sheets)),
            'updated'  => array_sum(array_map(fn($s) => $s->updated,  $this->sheets)),
            'skipped'  => array_sum(array_map(fn($s) => $s->skipped,  $this->sheets)),
            'errors'   => array_merge(...array_map(fn($s) => $s->errors, $this->sheets)),
            default    => null,
        };
    }
}

class SurveyOptionSheetImport implements ToCollection, WithHeadingRow
{
    public int   $inserted = 0;
    public int   $updated  = 0;
    public int   $skipped  = 0;
    public array $errors   = [];

    public function __construct(private array $validCategories) {}

    public function collection(Collection $rows): void
    {
        $affectedCategories = [];

        foreach ($rows as $index => $row) {
            $rowNum = $index + 2;

            $data = [
                'category'   => trim((string) ($row['category']   ?? '')),
                'value'      => trim((string) ($row['value']      ?? '')),
                'label_ne'   => trim((string) ($row['label_ne']   ?? '')),
                'label_en'   => trim((string) ($row['label_en']   ?? '')) ?: null,
                'sort_order' => (int) ($row['sort_order'] ?? 0),
                'is_active'  => isset($row['is_active']) && $row['is_active'] !== ''
                                    ? (int) $row['is_active']
                                    : 1,
            ];

            if ($data['category'] === '' && $data['value'] === '') {
                continue;
            }

            $validator = Validator::make($data, [
                'category'   => 'required|string|in:' . implode(',', $this->validCategories),
                'value'      => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9_\-]+$/'],
                'label_ne'   => 'required|string|max:200',
                'label_en'   => 'nullable|string|max:200',
                'sort_order' => 'integer|min:0',
                'is_active'  => 'integer|in:0,1',
            ]);

            if ($validator->fails()) {
                $this->skipped++;
                $this->errors[] = "Row {$rowNum}: " . implode('; ', $validator->errors()->all());
                continue;
            }

            $existing = SurveyOption::where('category', $data['category'])
                ->where('value', $data['value'])
                ->first();

            if ($existing) {
                $existing->update([
                    'label_ne'   => $data['label_ne'],
                    'label_en'   => $data['label_en'],
                    'sort_order' => $data['sort_order'],
                    'is_active'  => $data['is_active'],
                ]);
                $this->updated++;
            } else {
                SurveyOption::create([
                    'category'            => $data['category'],
                    'value'               => $data['value'],
                    'label_ne'            => $data['label_ne'],
                    'label_en'            => $data['label_en'],
                    'sort_order'          => $data['sort_order'],
                    'is_active'           => $data['is_active'],
                    'local_government_id' => null,
                ]);
                $this->inserted++;
            }

            $affectedCategories[$data['category']] = true;
        }

        foreach (array_keys($affectedCategories) as $cat) {
            Cache::forget("survey_options.{$cat}.global");
            Cache::forget("survey_options.all.global");
        }
    }
}
