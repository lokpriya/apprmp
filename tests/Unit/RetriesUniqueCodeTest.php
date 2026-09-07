<?php

namespace Tests\Unit;

use App\Support\RetriesUniqueCode;
use Illuminate\Database\QueryException;
use PHPUnit\Framework\TestCase;
use PDOException;
use RuntimeException;

/**
 * Regression tests for unique_code collision recovery.
 *
 * UniqueCodeService derives the next code by reading the highest existing one
 * and adding 1, with no lock. Two enumerators saving in the same ward at the
 * same moment therefore compute the same code; the UNIQUE index rejects the
 * second insert and, before this, the enumerator lost the form they had just
 * filled in. Several enumerators per ward is normal operation for this app.
 */
class RetriesUniqueCodeTest extends TestCase
{
    private object $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new class {
            use RetriesUniqueCode {
                retryOnDuplicateCode as public;
            }
        };
    }

    private function duplicateCodeException(): QueryException
    {
        return new QueryException(
            'mysql',
            'insert into `households` (`unique_code`) values (?)',
            ['HH-0001-W05-00042'],
            new PDOException("SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'HH-0001-W05-00042' for key 'households_unique_code_unique'", 23000)
        );
    }

    private function otherConstraintException(): QueryException
    {
        return new QueryException(
            'mysql',
            'insert into `households` (`ward_id`) values (?)',
            [99],
            new PDOException("SQLSTATE[23000]: Integrity constraint violation: 1452 Cannot add or update a child row: a foreign key constraint fails (`households_ward_id_foreign`)", 23000)
        );
    }

    public function test_it_returns_the_value_when_nothing_fails(): void
    {
        $this->assertSame('ok', $this->subject->retryOnDuplicateCode(fn () => 'ok'));
    }

    /** The whole point: a collision is retried, and the second attempt wins. */
    public function test_it_retries_after_a_duplicate_code_and_succeeds(): void
    {
        $calls = 0;

        $result = $this->subject->retryOnDuplicateCode(function () use (&$calls) {
            $calls++;
            if ($calls === 1) {
                throw $this->duplicateCodeException();
            }

            return 'saved on attempt '.$calls;
        });

        $this->assertSame('saved on attempt 2', $result);
        $this->assertSame(2, $calls);
    }

    public function test_it_keeps_retrying_across_several_collisions(): void
    {
        $calls = 0;

        $result = $this->subject->retryOnDuplicateCode(function () use (&$calls) {
            $calls++;
            if ($calls < 4) {
                throw $this->duplicateCodeException();
            }

            return 'saved';
        });

        $this->assertSame('saved', $result);
        $this->assertSame(4, $calls);
    }

    /** It must give up rather than loop forever against a persistent collision. */
    public function test_it_gives_up_after_the_attempt_limit(): void
    {
        $calls = 0;

        $this->expectException(QueryException::class);

        try {
            $this->subject->retryOnDuplicateCode(function () use (&$calls) {
                $calls++;
                throw $this->duplicateCodeException();
            }, 3);
        } finally {
            $this->assertSame(3, $calls, 'Should stop at the configured attempt limit.');
        }
    }

    /**
     * A foreign-key violation is also SQLSTATE 23000 but retrying just repeats
     * it. Only a unique_code collision may be retried.
     */
    public function test_it_does_not_retry_a_different_integrity_violation(): void
    {
        $calls = 0;

        try {
            $this->subject->retryOnDuplicateCode(function () use (&$calls) {
                $calls++;
                throw $this->otherConstraintException();
            });
            $this->fail('Expected the exception to propagate.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('foreign key', $e->getMessage());
        }

        $this->assertSame(1, $calls, 'A foreign-key failure must not be retried.');
    }

    public function test_it_does_not_retry_an_unrelated_exception(): void
    {
        $calls = 0;

        try {
            $this->subject->retryOnDuplicateCode(function () use (&$calls) {
                $calls++;
                throw new RuntimeException('something else broke');
            });
            $this->fail('Expected the exception to propagate.');
        } catch (RuntimeException $e) {
            $this->assertSame('something else broke', $e->getMessage());
        }

        $this->assertSame(1, $calls);
    }
}
