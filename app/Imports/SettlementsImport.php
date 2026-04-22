<?php

namespace App\Imports;

use App\Models\Settlement;
use App\Models\Ward;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class SettlementsImport implements ToCollection, WithHeadingRow
{
    public int   $inserted = 0;
    public int   $updated  = 0;
    public int   $skipped  = 0;
    public array $errors   = [];

    /** ward_number → Ward model, scoped to this LG */
    private array $wardMap = [];

    public function __construct(private readonly int $lgId) {}

    public function collection(Collection $rows): void
    {
        // Build ward map once
        $wards = Ward::where('local_government_id', $this->lgId)->get();
        foreach ($wards as $ward) {
            $this->wardMap[(int) $ward->ward_number] = $ward;
        }

        foreach ($rows as $index => $row) {
            $rowNum = $index + 2;

            $data = [
                'ward_number'     => trim((string) ($row['ward_number']     ?? '')),
                'name_np'         => trim((string) ($row['name_np']         ?? '')),
                'name_en'         => trim((string) ($row['name_en']         ?? '')),
                'settlement_type' => strtolower(trim((string) ($row['settlement_type'] ?? 'tole'))),
            ];

            // Skip fully empty rows
            if ($data['ward_number'] === '' && $data['name_np'] === '') {
                continue;
            }

            $validator = Validator::make($data, [
                'ward_number'     => 'required|integer|min:1',
                'name_np'         => 'required|string|max:200',
                'name_en'         => 'required|string|max:200',
                'settlement_type' => 'required|in:tole,gau,chowk,basti',
            ], [
                'settlement_type.in' => 'settlement_type must be: tole, gau, chowk, or basti.',
            ]);

            if ($validator->fails()) {
                $this->skipped++;
                $this->errors[] = "Row {$rowNum}: " . implode('; ', $validator->errors()->all());
                continue;
            }

            $wardNo = (int) $data['ward_number'];
            if (! isset($this->wardMap[$wardNo])) {
                $this->skipped++;
                $this->errors[] = "Row {$rowNum}: वडा नम्बर {$wardNo} यस स्थानीय तहमा छैन।";
                continue;
            }

            $ward = $this->wardMap[$wardNo];

            // Upsert by ward_id + name_np
            $existing = Settlement::where('ward_id', $ward->id)
                ->where('name_np', $data['name_np'])
                ->first();

            if ($existing) {
                $existing->update([
                    'name_en'         => $data['name_en'],
                    'settlement_type' => $data['settlement_type'],
                ]);
                $this->updated++;
            } else {
                Settlement::create([
                    'ward_id'         => $ward->id,
                    'name_np'         => $data['name_np'],
                    'name_en'         => $data['name_en'],
                    'settlement_type' => $data['settlement_type'],
                ]);
                $this->inserted++;
            }
        }
    }
}
