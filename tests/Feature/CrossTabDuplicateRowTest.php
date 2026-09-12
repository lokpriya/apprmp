<?php

namespace Tests\Feature;

use App\Services\CrossTabService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression tests for duplicate rows in the public report cross-tabs.
 *
 * Reported from the live Rong site: the development-index report showed
 * "राष्ट्रिय ग्रिड" three times — once carrying 4,235 households and twice as
 * empty rows. It looked like duplicated survey options, but deleting one
 * changed nothing.
 *
 * The cause is in optionLabels(), which deliberately adds Kobo alias keys
 * carrying the *same* label as the system key, so that data written by Google
 * Sync in Kobo format still finds a bucket:
 *
 *     'electricity_source' => ['National_grid' => 'national_grid',
 *                              'Grid'          => 'national_grid', ...]
 *
 * tab() then rendered one row per *key*, so each aliased answer became two or
 * three identical rows. Twelve categories carry aliases — 89 phantom rows in
 * total, worst on water_source (25 keys for 10 real answers) — so this affected
 * most report tables and their chart legends, not one page.
 *
 * These are alternative spellings of a single answer, so the fix merges rows
 * sharing a label and sums their counts. Summing matters: real data does live
 * under the alias keys wherever Google Sync wrote Kobo values.
 */
class CrossTabDuplicateRowTest extends TestCase
{
    use RefreshDatabase;

    /** Rows whose label is identical must collapse into one. */
    public function test_rows_sharing_a_label_are_merged(): void
    {
        $result = $this->tabOver(
            ['national_grid', 'National_grid', 'Grid', 'solar'],
            [
                'national_grid' => 'राष्ट्रिय ग्रिड',
                'National_grid' => 'राष्ट्रिय ग्रिड',
                'Grid' => 'राष्ट्रिय ग्रिड',
                'solar' => 'सोलार',
            ]
        );

        $labels = array_map(fn ($k) => $result['row_labels'][$k], $result['rows']);

        $this->assertSame(['राष्ट्रिय ग्रिड', 'सोलार'], array_values($labels));
        $this->assertCount(2, $result['rows'], 'Three grid spellings must render as one row.');
    }

    /**
     * The important one: merging must *sum*, never drop. Google Sync wrote real
     * records under the Kobo alias keys.
     */
    public function test_counts_from_alias_keys_are_summed_not_discarded(): void
    {
        $result = $this->tabOver(
            // 3 rows under the system key, 2 under one alias, 1 under another
            ['national_grid', 'national_grid', 'national_grid', 'National_grid', 'National_grid', 'Grid'],
            [
                'national_grid' => 'राष्ट्रिय ग्रिड',
                'National_grid' => 'राष्ट्रिय ग्रिड',
                'Grid' => 'राष्ट्रिय ग्रिड',
            ]
        );

        $this->assertCount(1, $result['rows']);
        $this->assertSame(6, $result['row_totals'][$result['rows'][0]], 'All six must be counted.');
        $this->assertSame(6, $result['grand_total']);
    }

    /** The grand total must not change — that is what proves nothing was lost. */
    public function test_the_grand_total_is_unaffected_by_merging(): void
    {
        $values = ['national_grid', 'National_grid', 'Grid', 'solar', 'Solar', 'biogas'];

        $merged = $this->tabOver($values, [
            'national_grid' => 'राष्ट्रिय ग्रिड', 'National_grid' => 'राष्ट्रिय ग्रिड',
            'Grid' => 'राष्ट्रिय ग्रिड', 'solar' => 'सोलार', 'Solar' => 'सोलार',
            'biogas' => 'बायोग्याँस',
        ]);

        $this->assertSame(count($values), $merged['grand_total']);
        $this->assertCount(3, $merged['rows'], 'Six keys, three distinct labels.');
    }

    /** Genuinely different answers must stay separate. */
    public function test_distinct_labels_are_never_merged(): void
    {
        $result = $this->tabOver(
            ['solar', 'generator', 'biogas'],
            ['solar' => 'सोलार', 'generator' => 'जेनेरेटर', 'biogas' => 'बायोग्याँस']
        );

        $this->assertCount(3, $result['rows']);
    }

    /**
     * Dynamic mode (no rowDef) labels each key as itself, so nothing should
     * merge — this guards against the fix collapsing unrelated rows.
     */
    public function test_dynamic_mode_without_labels_does_not_merge(): void
    {
        $result = $this->tabOver(['a', 'b', 'c'], []);

        $this->assertCount(3, $result['rows']);
        $this->assertSame(3, $result['grand_total']);
    }

    /**
     * Guards the source of the problem: every aliased category must still
     * produce more keys than labels (that is by design, for data matching) —
     * so the merge in tab() is doing real work rather than being dead code.
     */
    public function test_alias_categories_still_carry_duplicate_labels(): void
    {
        Cache::flush();

        foreach ([['national_grid', 'राष्ट्रिय ग्रिड'], ['solar', 'सोलार']] as [$value, $label]) {
            DB::table('survey_options')->insert([
                'category' => 'electricity_source',
                'value' => $value,
                'label_ne' => $label,
                'is_active' => true,
                'sort_order' => 1,
            ]);
        }

        $labels = CrossTabService::optionLabels('electricity_source');

        // 'National_grid' and 'Grid' are added as aliases of 'national_grid',
        // 'Solar' as an alias of 'solar' — all carrying the same labels.
        $this->assertGreaterThan(
            count(array_unique(array_values($labels))),
            count($labels),
            'Aliases are expected here — if this ever stops being true, the merge may be removable.'
        );
        $this->assertSame('राष्ट्रिय ग्रिड', $labels['Grid'] ?? null);
    }

    /**
     * Builds a cross-tab over a scratch table so the behaviour is tested through
     * the real query path rather than a stub.
     *
     * @param  string[]  $rowValues  one row of data per entry
     * @param  array<string,string>  $rowDef
     */
    private function tabOver(array $rowValues, array $rowDef): array
    {
        DB::statement('DROP TEMPORARY TABLE IF EXISTS xtab_probe');
        DB::statement('CREATE TEMPORARY TABLE xtab_probe (src VARCHAR(64), col VARCHAR(16))');

        foreach ($rowValues as $v) {
            DB::table('xtab_probe')->insert(['src' => $v, 'col' => '1']);
        }

        return CrossTabService::tab(
            \App\Models\Ward::query()->from('xtab_probe'),
            'src',
            'col',
            $rowDef,
            ['1' => 'Ward 1'],
            ''
        );
    }
}
