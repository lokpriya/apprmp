<?php

namespace Tests\Unit;

use App\Models\HouseholdMember;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for the `residing_country` accessor/mutator.
 *
 * History: this column was once cast with `'residing_country' => 'array'`. When
 * that cast was dropped the column kept its stored JSON, but every read started
 * returning the raw string — silently corrupting migration reports. The live
 * databases contain *scalars* ("भारत"), not arrays, because that is what the
 * old cast wrote for a single selection, so any replacement must read both
 * shapes losslessly and must never widen a scalar into a per-character list.
 *
 * These run without a database: Attribute casts are pure functions of the
 * stored value, so exercising them directly is both faster and stricter.
 */
class ResidingCountryCastTest extends TestCase
{
    private function storedValueFor(mixed $input): ?string
    {
        $member = new HouseholdMember();
        $member->residing_country = $input;

        return $member->getAttributes()['residing_country'];
    }

    private function readBack(?string $stored): array
    {
        $member = new HouseholdMember();
        $member->setRawAttributes(['residing_country' => $stored], true);

        return $member->residing_country;
    }

    /** The shape that 1,669 production rows actually hold. */
    public function test_reads_a_legacy_json_scalar_as_a_single_element_list(): void
    {
        $this->assertSame(['भारत'], $this->readBack(json_encode('भारत', JSON_UNESCAPED_UNICODE)));
    }

    public function test_reads_a_json_array_as_a_list(): void
    {
        $stored = json_encode(['भारत', 'कतार'], JSON_UNESCAPED_UNICODE);

        $this->assertSame(['भारत', 'कतार'], $this->readBack($stored));
    }

    /** A bare string was never valid JSON, but tolerate it rather than lose it. */
    public function test_reads_an_unencoded_bare_string_without_losing_it(): void
    {
        $this->assertSame(['भारत'], $this->readBack('भारत'));
    }

    #[DataProvider('emptyStoredValues')]
    public function test_empty_storage_reads_as_an_empty_list(?string $stored): void
    {
        $this->assertSame([], $this->readBack($stored));
    }

    public static function emptyStoredValues(): array
    {
        return [
            'null column' => [null],
            'empty string' => [''],
            'json null' => ['null'],
            'json empty string' => ['""'],
            'json empty array' => ['[]'],
        ];
    }

    public function test_writing_a_list_stores_unescaped_json(): void
    {
        $stored = $this->storedValueFor(['भारत', 'कतार']);

        $this->assertSame('["भारत","कतार"]', $stored);
        $this->assertStringNotContainsString('\u', (string) $stored, 'Devanagari must not be \u-escaped.');
    }

    public function test_writing_a_bare_string_stores_a_list_not_a_scalar(): void
    {
        $this->assertSame('["भारत"]', $this->storedValueFor('भारत'));
    }

    /** Blank selections must clear the column, not store "[]" or '[""]'. */
    #[DataProvider('emptyWrittenValues')]
    public function test_blank_input_clears_the_column(mixed $input): void
    {
        $this->assertNull($this->storedValueFor($input));
    }

    public static function emptyWrittenValues(): array
    {
        return [
            'null' => [null],
            'empty string' => [''],
            'empty array' => [[]],
            'array of blanks' => [['', null]],
        ];
    }

    /**
     * The important one: read -> write -> read must be a fixed point for every
     * shape found in production. A regression here loses migration data with no
     * error, which is exactly how the original bug went unnoticed.
     */
    #[DataProvider('productionShapes')]
    public function test_round_trip_is_lossless(?string $stored, array $expected): void
    {
        $first = $this->readBack($stored);
        $this->assertSame($expected, $first);

        $rewritten = $this->storedValueFor($first);
        $this->assertSame($expected, $this->readBack($rewritten), 'Second read diverged from the first.');
    }

    public static function productionShapes(): array
    {
        return [
            'json scalar' => ['"भारत"', ['भारत']],
            'json array of one' => ['["भारत"]', ['भारत']],
            'json array of two' => ['["भारत","कतार"]', ['भारत', 'कतार']],
            'bare string' => ['भारत', ['भारत']],
            'null' => [null, []],
        ];
    }
}
