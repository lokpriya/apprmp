<?php

namespace Database\Seeders;

use App\Models\District;
use App\Models\LocalGovernment;
use App\Models\Province;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class NepalMasterDataSeeder extends Seeder
{
    /** Nepali names for the 7 provinces (not included in the Excel file). */
    private const PROVINCE_NEPALI = [
        'Koshi Province'         => 'कोशी प्रदेश',
        'Madhesh Province'       => 'मधेश प्रदेश',
        'Bagmati Province'       => 'बागमती प्रदेश',
        'Gandaki Province'       => 'गण्डकी प्रदेश',
        'Lumbini Province'       => 'लुम्बिनी प्रदेश',
        'Karnali Province'       => 'कर्णाली प्रदेश',
        'Sudurpashchim Province' => 'सुदूरपश्चिम प्रदेश',
    ];

    /** Province English name → short unique code (max 20 chars). */
    private const PROVINCE_CODE = [
        'Koshi Province'         => 'Koshi',
        'Madhesh Province'       => 'Madhesh',
        'Bagmati Province'       => 'Bagmati',
        'Gandaki Province'       => 'Gandaki',
        'Lumbini Province'       => 'Lumbini',
        'Karnali Province'       => 'Karnali',
        'Sudurpashchim Province' => 'Sudurpashchim',
    ];

    public function run(): void
    {
        // Primary location (deployment): database/data/nepal_geo.xlsx
        // Fallback (local dev): Reference Files/data_localgov.xlsx
        $path = base_path('database/data/nepal_geo.xlsx');
        if (! file_exists($path)) {
            $path = base_path('Reference Files/data_localgov.xlsx');
        }

        if (! file_exists($path)) {
            $this->command->error("File not found: {$path}");
            return;
        }

        $this->command->info('Cleaning up old Nepal reference data...');
        $this->cleanOldData();

        // ── Read Excel ────────────────────────────────────────────────────────
        // NOTE: Columns A (Province) and B (District) are MERGED CELLS in Excel.
        // PhpSpreadsheet with setReadDataOnly(true) returns the value only for
        // the FIRST cell of each merged region; all subsequent rows in the same
        // merge return an empty string. We therefore carry `$currentProvince`
        // and `$currentDistrict` forward as we iterate.

        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        $sheet  = $reader->load($path)->getActiveSheet();
        $maxRow = $sheet->getHighestRow();

        // ── Seed Provinces ────────────────────────────────────────────────────
        $provinces = []; // English name → Province model
        foreach (self::PROVINCE_CODE as $nameEn => $code) {
            $provinces[$nameEn] = Province::updateOrCreate(
                ['code' => $code],
                ['name_en' => $nameEn, 'name_np' => self::PROVINCE_NEPALI[$nameEn]]
            );
        }

        // ── Seed Districts & Local Governments ────────────────────────────────
        $lgSequence      = 1;
        $distCount       = 0;
        $lgCount         = 0;
        $currentProvince = null;
        $currentDistrict = null;

        for ($row = 2; $row <= $maxRow; $row++) {
            $provEn  = trim((string) $sheet->getCell('A' . $row)->getValue());
            $distRaw = trim((string) $sheet->getCell('B' . $row)->getValue());
            $lgNp    = trim((string) $sheet->getCell('C' . $row)->getValue());
            $lgEn    = trim((string) $sheet->getCell('D' . $row)->getValue());

            // Column A – update province when the merged cell changes
            if ($provEn !== '' && isset($provinces[$provEn])) {
                $currentProvince = $provinces[$provEn];
            }

            // Column B – create/update district when the merged cell changes
            if ($distRaw !== '') {
                [$distEn, $distNp] = $this->parseDistrict($distRaw);

                $currentDistrict = District::updateOrCreate(
                    ['code' => $distEn],
                    [
                        'province_id' => $currentProvince->id,
                        'name_en'     => $distEn,
                        'name_np'     => $distNp,
                    ]
                );
                $distCount++;
            }

            // Skip header/blank rows that have no LG name
            if ($lgNp === '' || $lgEn === '') {
                continue;
            }

            if (! $currentDistrict) {
                $this->command->warn("No district resolved for LG '{$lgEn}' at row {$row}, skipping.");
                continue;
            }

            $code = 'NLP' . str_pad($lgSequence, 4, '0', STR_PAD_LEFT);
            LocalGovernment::updateOrCreate(
                ['code' => $code],
                [
                    'district_id' => $currentDistrict->id,
                    'name_en'     => $lgEn,
                    'name_np'     => $lgNp,
                    'type'        => $this->inferType($lgNp),
                    'ward_count'  => 0,
                    'is_active'   => false,
                ]
            );

            $lgSequence++;
            $lgCount++;
        }

        $this->command->info(
            "Nepal master data seeded: 7 provinces, {$distCount} districts, {$lgCount} local governments."
        );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Parse district column value into [English, Nepali] pair.
     *
     * Formats encountered in the Excel:
     *   "Taplejung (ताप्लेजुङ्ग)"        → split on first "("
     *   "Bhojpur  (भोजपुर)"              → same (handles extra spaces)
     *   "Kalikot कालिकोट)"               → no "(" – split before first Devanagari
     */
    private function parseDistrict(string $raw): array
    {
        // Case 1: has an opening parenthesis
        $parenPos = strpos($raw, '(');
        if ($parenPos !== false) {
            $en = trim(substr($raw, 0, $parenPos));
            $np = trim(rtrim(trim(substr($raw, $parenPos + 1)), ')'));
            return [$en ?: trim($raw), $np ?: trim($raw)];
        }

        // Case 2: no parenthesis – split on the first Devanagari character
        if (preg_match('/^([A-Za-z\s\-]+?)\s+([\x{0900}-\x{097F}].*?)\)?\s*$/u', $raw, $m)) {
            return [trim($m[1]), trim(rtrim(trim($m[2]), ')'))];
        }

        return [trim($raw), trim($raw)];
    }

    /**
     * Infer LG type from Nepali name.
     * Order matters: उपमहानगरपालिका contains महानगरपालिका as a substring.
     */
    private function inferType(string $nameNp): string
    {
        if (str_contains($nameNp, 'उपमहानगरपालिका')) return 'sub_metropolitan';
        if (str_contains($nameNp, 'महानगरपालिका'))   return 'metropolitan';
        if (str_contains($nameNp, 'नगरपालिका'))       return 'municipality';
        return 'rural_municipality';
    }

    /**
     * Wipe previously Nepal-seeded data (all NLP-coded LGs and their parent
     * districts/provinces) without touching the MasterDataSeeder records
     * (P1 province / ILA district / ILA-MUN-01 LG) used by test users.
     *
     * FK checks are disabled for the duration so the wards→households chain
     * does not block deletes of the reference-only (no-ward) LGs.
     */
    private function cleanOldData(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            // 1. Delete NLP-coded LGs from any previous run of this seeder
            LocalGovernment::where('code', 'like', 'NLP%')->delete();

            // 2. Delete orphan LGs still in Nepal-seeded provinces (old-style codes)
            $provIds = Province::whereNotIn('code', ['P1'])->pluck('id');
            if ($provIds->isNotEmpty()) {
                $distIds = District::whereIn('province_id', $provIds)
                    ->whereNotIn('code', ['ILA'])
                    ->pluck('id');

                if ($distIds->isNotEmpty()) {
                    LocalGovernment::whereIn('district_id', $distIds)
                        ->whereNotIn('code', ['ILA-MUN-01'])
                        ->delete();

                    District::whereIn('id', $distIds)->delete();
                }

                Province::whereIn('id', $provIds)->delete();
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }
}
