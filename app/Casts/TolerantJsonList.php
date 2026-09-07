<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Casts a multi-select column to an array, tolerating every shape the live
 * databases actually contain.
 *
 * Laravel's built-in 'array' cast is `json_decode($value, true)` and returns
 * whatever that yields — so a column holding the JSON *scalar* `"भारत"` reads
 * back as a string, not an array. The frontend then hits
 * `Array.isArray(x) ? x : []`, shows the field as empty, and the next save
 * writes `[]` over the real answer. That is not hypothetical: it destroyed
 * residing_country data, and drivers_license_category holds 1,768 scalar rows
 * written by the Google Sheets sync.
 *
 * Every shape found in production normalises to a list:
 *   ["भारत","कतार"]  → ['भारत','कतार']   (proper array)
 *   "भारत"           → ['भारत']          (JSON scalar — the common legacy case)
 *   भारत             → ['भारत']          (bare string, never valid JSON)
 *   null / "" / "[]" → []
 *
 * Writing always produces a JSON array, so a value only has to survive one save
 * to be stored correctly from then on. Unicode is left unescaped because these
 * are Devanagari labels and escaped output is unreadable in the database.
 */
class TolerantJsonList implements CastsAttributes
{
    /**
     * @return string[]
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        // Not JSON at all — a bare string written directly to the column.
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [$value];
        }

        if (is_array($decoded)) {
            return array_values(array_filter($decoded, fn ($v) => $v !== null && $v !== ''));
        }

        return ($decoded === null || $decoded === '') ? [] : [(string) $decoded];
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        // A JSON string arriving from a form post or an import row.
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $value = $decoded;
            }
        }

        $list = array_values(array_filter(
            is_array($value) ? $value : [$value],
            fn ($v) => $v !== null && $v !== ''
        ));

        // Store NULL rather than "[]" so "no answer" and "empty answer" agree.
        return $list ? json_encode($list, JSON_UNESCAPED_UNICODE) : null;
    }
}
