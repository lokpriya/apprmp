<?php

namespace App\Support;

use Illuminate\Database\QueryException;
use Throwable;

/**
 * Retries a write that failed because two records claimed the same unique_code.
 *
 * UniqueCodeService derives the next code by reading the highest existing one
 * and adding 1. That read is not locked, so two enumerators saving in the same
 * ward at the same moment both read the same value and compute the same code.
 * The UNIQUE index keeps the data correct, but the loser's insert throws and the
 * enumerator loses the form they just filled in — and several enumerators
 * working one ward simultaneously is the app's normal operating mode, not an
 * edge case.
 *
 * Locking the read would only work inside a transaction, and the bulk-import
 * sheets generate codes outside one. Retrying works in both contexts: the code
 * is regenerated from a fresh read on each attempt, so the second writer simply
 * picks up the next number.
 */
trait RetriesUniqueCode
{
    /**
     * @template T
     * @param  callable():T  $callback
     * @return T
     */
    protected function retryOnDuplicateCode(callable $callback, int $attempts = 5)
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return $callback();
            } catch (Throwable $e) {
                if ($attempt >= $attempts || ! $this->isDuplicateUniqueCode($e)) {
                    throw $e;
                }

                // Jitter, so two colliding writers do not retry in lockstep and
                // collide again on the same regenerated code.
                usleep(random_int(10_000, 60_000));
            }
        }
    }

    /**
     * A unique_code collision specifically — never a different constraint.
     * Retrying an unrelated integrity violation would just repeat it.
     */
    protected function isDuplicateUniqueCode(Throwable $e): bool
    {
        if (! $e instanceof QueryException) {
            return false;
        }

        return (string) $e->getCode() === '23000'
            && str_contains($e->getMessage(), 'unique_code');
    }
}
