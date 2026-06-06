<?php

namespace App\Imports\Sheets;

use App\Models\Household;
use App\Models\HouseholdLivestock;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class LivestockSheet implements ToCollection, WithChunkReading, WithHeadingRow, WithStartRow, SkipsEmptyRows
{
    public function startRow(): int { return 3; }
    public function chunkSize(): int { return 200; }

    private array $errors        = [];
    private int   $imported      = 0;
    private array $beeFishByCoe  = [];  // householdId => accumulated bee/fish/silk data

    public function __construct(private readonly HouseholdsSheet $householdsSheet) {}

    public function collection(Collection $rows): void
    {
        $codeMap = $this->householdsSheet->getCodeToIdMap();

        DB::transaction(function () use ($rows, $codeMap) {
            foreach ($rows as $index => $row) {
                $rowNum = $index + 2;
                try {
                    $code = trim($row['household_code'] ?? '');
                    if (!$code || !isset($codeMap[$code])) {
                        $this->errors[] = "Row {$rowNum}: household_code '{$code}' not found.";
                        continue;
                    }
                    $householdId = $codeMap[$code];

                    // ── Livestock row ──
                    $type = trim($row['livestock_type'] ?? '');
                    if ($type) {
                        HouseholdLivestock::create([
                            'household_id'       => $householdId,
                            'livestock_type'     => $type,
                            'count'              => is_numeric($row['count'] ?? '') ? (int)$row['count'] : 0,
                            'breed'              => trim($row['breed']              ?? '') ?: null,
                            'rearing_method'     => trim($row['rearing_method']    ?? '') ?: null,
                            'annual_production'  => is_numeric($row['annual_production'] ?? '') ? $row['annual_production'] : null,
                            'production_unit'    => trim($row['production_unit']   ?? '') ?: null,
                            'vet_service_access' => $this->bool($row['vet_service_access'] ?? 0),
                            'insurance'          => $this->bool($row['insurance'] ?? 0),
                        ]);
                        $this->imported++;
                    }

                    // ── Bee / Fish / Silk snapshot ──
                    $bfs = &$this->beeFishByCoe[$householdId];
                    if (!isset($bfs)) $bfs = [];

                    if ($this->bool($row['has_beekeeping'] ?? 0)) {
                        $bfs['has_bee_fish_silk']            = 'yes';
                        $bfs['bee_fish_silk_select']         = array_unique(array_merge($bfs['bee_fish_silk_select'] ?? [], ['bee']));
                        if (is_numeric($row['mudhe_hive']     ?? '')) $bfs['Mudhe_Hive']                   = (int)$row['mudhe_hive'];
                        if (is_numeric($row['modern_hive']    ?? '')) $bfs['Modern_Hive']                  = (int)$row['modern_hive'];
                        if (is_numeric($row['honey_prod_mudhe']  ?? '')) $bfs['Honey_Production_Mudhe_Hive']  = $row['honey_prod_mudhe'];
                        if (is_numeric($row['honey_prod_modern'] ?? '')) $bfs['Honey_Production_Modern_Hive'] = $row['honey_prod_modern'];
                        if (is_numeric($row['honey_sales'] ?? '')) $bfs['honey_sales'] = $row['honey_sales'];
                    }
                    if ($this->bool($row['has_fishery'] ?? 0)) {
                        $bfs['has_bee_fish_silk']    = 'yes';
                        $bfs['bee_fish_silk_select'] = array_unique(array_merge($bfs['bee_fish_silk_select'] ?? [], ['fish']));
                        if (is_numeric($row['pond_area']  ?? '')) $bfs['Pond_area']  = $row['pond_area'];
                        if (is_numeric($row['fish_prod']  ?? '')) $bfs['fish_prod']  = $row['fish_prod'];
                        if (is_numeric($row['fish_sales'] ?? '')) $bfs['fish_sales'] = $row['fish_sales'];
                    }

                } catch (\Throwable $e) {
                    $this->errors[] = "Row {$rowNum}: " . $e->getMessage();
                }
            }

            // ── Persist bee/fish/silk accumulated in this chunk ──
            foreach ($this->beeFishByCoe as $householdId => $bfsData) {
                if (empty($bfsData)) continue;
                $hh = Household::find($householdId);
                if ($hh) {
                    $sd = $hh->survey_data ?? [];
                    $sd['beeFishSilk'] = array_merge($sd['beeFishSilk'] ?? [], $bfsData);
                    $hh->update(['survey_data' => $sd]);
                }
            }
            $this->beeFishByCoe = [];
        });
    }

    private function bool(mixed $val): bool
    {
        return in_array(strtolower((string)$val), ['1', 'yes', 'true', 'y'], true);
    }

    public function getErrors(): array      { return $this->errors; }
    public function getImportedCount(): int { return $this->imported; }
}
