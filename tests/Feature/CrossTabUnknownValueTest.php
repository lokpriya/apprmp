<?php

namespace Tests\Feature;

use App\Services\CrossTabService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression tests for answers silently missing from the public reports.
 *
 * tab() used to discard any value that had no matching survey option:
 *
 *     } elseif (!isset($rowDef[$r]) && !empty($rowDef)) {
 *         // skip unknown rows if rowDef was explicitly provided
 *     }
 *
 * Those are not malformed rows — they are real answers whose option is simply
 * missing, and dropping them understated official statistics with nothing to
 * indicate it. Measured on the live Rong data before the fix:
 *
 *   irrigation_source   373 of 1,178   31.7%  ('other', 'Canal,RainHarvesting')
 *   ethnicity         1,527 of 19,102   8.0%  (864 Bhujel, 629 Sunuwar, …)
 *   religion            195 of 19,102   1.0%  (Prakriti)
 *   electricity_source   40 of  4,367   0.9%  (Kerosene)
 *
 * 864 Bhujel and 629 Sunuwar people were absent from the published ethnic
 * composition of the municipality. Unknown answers are now counted and shown
 * under a prettified raw value, matching the fallback AnalyticsController
 * already used — which also tells an administrator which option to add.
 */
class CrossTabUnknownValueTest extends TestCase
{
    use RefreshDatabase;

    /** The core guarantee: a real answer is never dropped from a report. */
    public function test_an_answer_with_no_matching_option_is_still_counted(): void
    {
        $result = $this->tabOver(
            ['brahmin', 'brahmin', 'Bhujel', 'Bhujel', 'Bhujel'],
            ['brahmin' => 'ब्राह्मण']
        );

        $this->assertSame(5, $result['grand_total'], 'All five members must be counted.');
        $this->assertCount(2, $result['rows']);
    }

    /**
     * Note the non-empty $rowDef: an empty one puts tab() into auto-discover
     * mode, where labels are the raw keys. The prettified fallback applies only
     * when a label map was supplied and the answer is missing from it — which is
     * the real-world case, since the reports always pass optionLabels().
     */
    public function test_the_unknown_value_is_shown_with_a_readable_label(): void
    {
        $result = $this->tabOver(['micro_hydro_plant'], ['known' => 'ज्ञात']);

        $this->assertContains('Micro hydro plant', array_values($result['row_labels']),
            'Underscores become spaces and the value is capitalised, as AnalyticsController does.');
    }

    /** With no label map at all, tab() keeps its existing auto-discover behaviour. */
    public function test_auto_discover_mode_is_unchanged(): void
    {
        $result = $this->tabOver(['alpha', 'beta'], []);

        $this->assertSame(['alpha', 'beta'], array_values($result['rows']));
        $this->assertSame(2, $result['grand_total']);
    }

    /**
     * The number that matters to a statistics office: the report total must
     * equal the number of records, not the number that happened to match.
     */
    public function test_the_grand_total_equals_the_record_count(): void
    {
        $values = array_merge(
            array_fill(0, 7, 'known'),
            array_fill(0, 3, 'Prakriti'),
            array_fill(0, 2, 'Kerosene')
        );

        $result = $this->tabOver($values, ['known' => 'ज्ञात']);

        $this->assertSame(12, $result['grand_total']);
    }

    /** Blank answers are not an answer and must stay out of the table. */
    public function test_blank_values_are_still_excluded(): void
    {
        $result = $this->tabOver(['known', '', 'known'], ['known' => 'ज्ञात']);

        $this->assertCount(1, $result['rows']);
        $this->assertSame(2, $result['grand_total']);
    }

    /**
     * Adding the missing survey option must fold the answer into its proper
     * label rather than leaving a second English row beside it — that is the
     * whole point of surfacing it.
     */
    public function test_supplying_the_option_moves_it_under_its_proper_label(): void
    {
        $values = ['Bhujel', 'Bhujel', 'brahmin'];

        $without = $this->tabOver($values, ['brahmin' => 'ब्राह्मण']);
        $with = $this->tabOver($values, ['brahmin' => 'ब्राह्मण', 'Bhujel' => 'भुजेल']);

        $this->assertSame($without['grand_total'], $with['grand_total'], 'Total is unchanged either way.');
        $this->assertContains('भुजेल', array_values($with['row_labels']));
        $this->assertNotContains('Bhujel', array_values($with['row_labels']));
    }

    /**
     * Builds a cross-tab over a scratch table, so behaviour is exercised through
     * the real query path rather than a stub.
     *
     * @param  string[]  $rowValues
     * @param  array<string,string>  $rowDef
     */
    private function tabOver(array $rowValues, array $rowDef): array
    {
        DB::statement('DROP TEMPORARY TABLE IF EXISTS xtab_unknown');
        DB::statement('CREATE TEMPORARY TABLE xtab_unknown (src VARCHAR(64), col VARCHAR(16))');

        foreach ($rowValues as $v) {
            DB::table('xtab_unknown')->insert(['src' => $v, 'col' => '1']);
        }

        return CrossTabService::tab(
            \App\Models\Ward::query()->from('xtab_unknown'),
            'src',
            'col',
            $rowDef,
            ['1' => 'Ward 1'],
            ''
        );
    }
}
