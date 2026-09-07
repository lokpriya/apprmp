<?php

namespace Tests\Unit;

use App\Models\HouseholdMember;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for drivers_license_category.
 *
 * The same defect as residing_country, found by checking every 'array'-cast
 * column against the shape actually stored in the live database:
 *
 *   SELECT JSON_TYPE(drivers_license_category) ... -> STRING for 1,768 rows
 *
 * Those rows were written by GoogleSheetsSyncService, which stores the raw cell
 * value, while the bulk import path parses the same field into an array. A plain
 * 'array' cast reads a JSON scalar back as a string; the form's
 * `Array.isArray(x) ? x : []` then shows the field empty, and saving the
 * household writes [] over a real answer.
 *
 * The cast logic itself is covered exhaustively by ResidingCountryCastTest —
 * these tests pin the wiring for this second column and record the one thing the
 * cast deliberately does not attempt.
 */
class DriversLicenseCategoryCastTest extends TestCase
{
    private function readBack(?string $stored): array
    {
        $member = new HouseholdMember();
        $member->setRawAttributes(['drivers_license_category' => $stored], true);

        return $member->drivers_license_category;
    }

    private function storedValueFor(mixed $input): ?string
    {
        $member = new HouseholdMember();
        $member->drivers_license_category = $input;

        return $member->getAttributes()['drivers_license_category'];
    }

    /** The shape 1,768 production rows actually hold. */
    public function test_a_json_scalar_from_google_sync_reads_as_a_single_element_list(): void
    {
        $stored = json_encode('वर्ग A मोटरसाइकल, स्कुटर र मोपेड', JSON_UNESCAPED_UNICODE);

        $this->assertSame(['वर्ग A मोटरसाइकल, स्कुटर र मोपेड'], $this->readBack($stored));
    }

    /** Escaped unicode is how mysqldump and PHP's default json_encode store it. */
    public function test_an_escaped_unicode_scalar_reads_correctly(): void
    {
        $this->assertSame(['वर्ग A मोटरसाइकल'], $this->readBack('"वर्ग A मोटरसाइकल"'));
    }

    public function test_a_proper_array_from_the_form_reads_as_a_list(): void
    {
        $this->assertSame(['वर्ग A', 'वर्ग B'], $this->readBack('["वर्ग A","वर्ग B"]'));
    }

    public function test_writing_stores_an_unescaped_json_array(): void
    {
        $this->assertSame('["वर्ग A","वर्ग B"]', $this->storedValueFor(['वर्ग A', 'वर्ग B']));
    }

    /**
     * The round trip that was destroying data: read a legacy scalar, save it back
     * unchanged, and the value must survive as a proper array.
     */
    public function test_a_legacy_scalar_survives_a_read_then_save(): void
    {
        $legacy = json_encode('वर्ग A मोटरसाइकल, स्कुटर र मोपेड', JSON_UNESCAPED_UNICODE);

        $read = $this->readBack($legacy);
        $rewritten = $this->storedValueFor($read);

        $this->assertSame('["वर्ग A मोटरसाइकल, स्कुटर र मोपेड"]', $rewritten);
        $this->assertSame($read, $this->readBack($rewritten), 'Second read must match the first.');
    }

    public function test_blank_input_clears_the_column(): void
    {
        $this->assertNull($this->storedValueFor([]));
        $this->assertSame([], $this->readBack(null));
    }

    /**
     * Documents a known limitation, deliberately not fixed here.
     *
     * 201 rows hold two selections concatenated with no delimiter, e.g.
     * "वर्ग A मोटरसाइकल, स्कुटर र मोपेड वर्ग B कार, जिप र डेलिभरी भ्यान".
     * The cast preserves that as a single element rather than guessing where one
     * label ends and the next begins — splitting it correctly needs the
     * SurveyOption label list and belongs in a considered data migration, not in
     * a cast. Preserving the text is lossless; guessing is not.
     */
    public function test_concatenated_legacy_values_are_preserved_not_split(): void
    {
        $concatenated = 'वर्ग A मोटरसाइकल, स्कुटर र मोपेड वर्ग B कार, जिप र डेलिभरी भ्यान';

        $result = $this->readBack(json_encode($concatenated, JSON_UNESCAPED_UNICODE));

        $this->assertCount(1, $result);
        $this->assertSame($concatenated, $result[0], 'The full text must survive intact.');
    }
}
