<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Fill survey_data.health for households imported before the sync wrote it.
 *
 * Google Sync never produced a "health" section, so the स्वास्थ्य चेतना report is
 * blank on every synced municipality. Two of its eight answers were in fact
 * collected and stored, just in the wrong place:
 *
 *   aware_handwashing     ← household_utilities.handwashing
 *   has_health_insurance  ← survey_data.social.health_insurance
 *
 * Both are already in the database, so this repairs them without a re-sync. The
 * remaining six questions have no column on the derived sheets and need those
 * columns added before a re-sync can bring them in.
 *
 * Values entered by hand through the household form are never overwritten.
 */
class BackfillHealthSection extends Command
{
    protected $signature = 'health:backfill {--dry-run : Report what would change without writing}';
    protected $description = 'Populate survey_data.health.aware_handwashing and has_health_insurance from existing data';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Dry run — nothing will be written.');
        }

        $washMap = ['soap_water' => 'yes', 'water_only' => 'yes', 'none' => 'no'];

        $filled   = ['aware_handwashing' => 0, 'has_health_insurance' => 0];
        $skipped  = 0;
        $touched  = 0;
        $unknown  = [];

        $query = DB::table('households as h')
            ->leftJoin('household_utilities as u', 'u.household_id', '=', 'h.id')
            ->whereNull('h.deleted_at')
            ->select('h.id', 'h.survey_data', 'u.handwashing');

        $this->withProgressBar((clone $query)->count(), function ($bar) use (
            $query, $washMap, $dryRun, &$filled, &$skipped, &$touched, &$unknown
        ) {
            $query->orderBy('h.id')->chunk(500, function ($rows) use (
                $washMap, $dryRun, $bar, &$filled, &$skipped, &$touched, &$unknown
            ) {
                foreach ($rows as $row) {
                    $bar->advance();

                    $sd = json_decode($row->survey_data ?? '', true);
                    if (!is_array($sd)) $sd = [];

                    $health  = $sd['health'] ?? [];
                    $changed = false;

                    // Handwashing — only where the form has not already answered it.
                    $wash = trim((string) ($row->handwashing ?? ''));
                    if ($wash !== '' && ($health['aware_handwashing'] ?? '') === '') {
                        if (isset($washMap[$wash])) {
                            $health['aware_handwashing'] = $washMap[$wash];
                            $filled['aware_handwashing']++;
                            $changed = true;
                        } else {
                            $unknown[$wash] = ($unknown[$wash] ?? 0) + 1;
                        }
                    }

                    // Health insurance, written by the sync as social.health_insurance.
                    $ins = strtolower(trim((string) ($sd['social']['health_insurance'] ?? '')));
                    if ($ins !== '' && ($health['has_health_insurance'] ?? '') === '') {
                        if (in_array($ins, ['yes', 'no'], true)) {
                            $health['has_health_insurance'] = $ins;
                            $filled['has_health_insurance']++;
                            $changed = true;
                        } else {
                            $unknown[$ins] = ($unknown[$ins] ?? 0) + 1;
                        }
                    }

                    if (!$changed) {
                        $skipped++;
                        continue;
                    }

                    $touched++;
                    if ($dryRun) continue;

                    $sd['health'] = $health;
                    DB::table('households')->where('id', $row->id)->update([
                        'survey_data' => json_encode($sd, JSON_UNESCAPED_UNICODE),
                    ]);
                }
            });
        });

        $this->newLine(2);
        $this->line("Households updated      : {$touched}");
        $this->line("  aware_handwashing     : {$filled['aware_handwashing']}");
        $this->line("  has_health_insurance  : {$filled['has_health_insurance']}");
        $this->line("Left alone              : {$skipped}");

        if ($unknown) {
            $this->newLine();
            $this->warn('Values with no mapping (left untouched):');
            foreach ($unknown as $value => $count) {
                $this->line(sprintf('  %-30s %d', $value, $count));
            }
        }

        if (!$dryRun && $touched > 0) {
            Cache::flush();
            $this->newLine();
            $this->info('Report cache cleared.');
        }

        return self::SUCCESS;
    }
}
