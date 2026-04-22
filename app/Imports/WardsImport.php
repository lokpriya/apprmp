<?php

namespace App\Imports;

use App\Models\Ward;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class WardsImport implements ToCollection, WithHeadingRow
{
    public int   $inserted = 0;
    public int   $updated  = 0;
    public int   $skipped  = 0;
    public array $errors   = [];

    public function __construct(private readonly int $lgId) {}

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $rowNum = $index + 2;

            $data = [
                'ward_number'      => trim((string) ($row['ward_number']      ?? '')),
                'ward_chairperson' => trim((string) ($row['ward_chairperson'] ?? '')) ?: null,
                'contact_phone'    => trim((string) ($row['contact_phone']    ?? '')) ?: null,
            ];

            if ($data['ward_number'] === '') continue;

            $validator = Validator::make($data, [
                'ward_number'      => 'required|integer|min:1|max:35',
                'ward_chairperson' => 'nullable|string|max:100',
                'contact_phone'    => 'nullable|string|max:20',
            ]);

            if ($validator->fails()) {
                $this->skipped++;
                $this->errors[] = "Row {$rowNum}: " . implode('; ', $validator->errors()->all());
                continue;
            }

            $wardNo = (int) $data['ward_number'];

            $existing = Ward::where('local_government_id', $this->lgId)
                ->where('ward_number', $wardNo)
                ->first();

            if ($existing) {
                $existing->update([
                    'ward_chairperson' => $data['ward_chairperson'],
                    'contact_phone'    => $data['contact_phone'],
                ]);
                $this->updated++;
            } else {
                Ward::create([
                    'local_government_id' => $this->lgId,
                    'ward_number'         => $wardNo,
                    'ward_chairperson'    => $data['ward_chairperson'],
                    'contact_phone'       => $data['contact_phone'],
                ]);
                $this->inserted++;
            }
        }
    }
}
