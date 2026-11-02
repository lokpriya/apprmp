<?php

namespace App\Support;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Bikram Sambat ⇄ AD on the server, and the Nepali fiscal year.
 *
 * The calendar table is copied from the `bikram-sambat` npm package (1.8.0)
 * that the NepaliDatePicker uses, so a date picked in the browser and the
 * fiscal year computed here can never disagree. tests/Unit/BsDateTest.php
 * checks this port against that library. If the package is updated, re-copy
 * ENCODED_MONTH_LENGTHS from node_modules/bikram-sambat/src/index.js.
 *
 * Each entry packs one BS year: month m has 29 + ((value >> 2(m-1)) & 3) days.
 */
final class BsDate
{
    /** 1970-01-01 BS = 1913-04-13 AD */
    private const EPOCH_AD  = '1913-04-13';
    private const YEAR_ZERO = 1970;

    private const ENCODED_MONTH_LENGTHS = [
        5315258, 5314490, 9459438, 8673005, 5315258, 5315066, 9459438, 8673005, 5315258, 5314298,
        9459438, 5327594, 5315258, 5314298, 9459438, 5327594, 5315258, 5314286, 9459438, 5315306,
        5315258, 5314286, 8673006, 5315306, 5315258, 5265134, 8673006, 5315258, 5315258, 9459438,
        8673005, 5315258, 5314298, 9459438, 8673005, 5315258, 5314298, 9459438, 8473322, 5315258,
        5314298, 9459438, 5327594, 5315258, 5314298, 9459438, 5327594, 5315258, 5314286, 8673006,
        5315306, 5315258, 5265134, 8673006, 5315306, 5315258, 9459438, 8673005, 5315258, 5314490,
        9459438, 8673005, 5315258, 5314298, 9459438, 8473325, 5315258, 5314298, 9459438, 5327594,
        5315258, 5314298, 9459438, 5327594, 5315258, 5314286, 9459438, 5315306, 5315258, 5265134,
        8673006, 5315306, 5315258, 5265134, 8673006, 5315258, 5314490, 9459438, 8673005, 5315258,
        5314298, 9459438, 8669933, 5315258, 5314298, 9459438, 8473322, 5315258, 5314298, 9459438,
        5327594, 5315258, 5314286, 9459438, 5315306, 5315258, 5265134, 8673006, 5315306, 5315258,
        5265134, 8673006, 5315258, 5527226, 5527226, 5528046, 5527277, 5528250, 5528057, 5527277,
        5527277,
    ];

    public const MONTHS = ['बैशाख', 'जेठ', 'असार', 'साउन', 'भदौ', 'असोज', 'कार्तिक', 'मंसिर', 'पौष', 'माघ', 'फाल्गुन', 'चैत'];

    public static function daysInMonth(int $year, int $month): int
    {
        if ($month < 1 || $month > 12) throw new InvalidArgumentException("Invalid BS month {$month}");
        $enc = self::ENCODED_MONTH_LENGTHS[$year - self::YEAR_ZERO] ?? null;
        if ($enc === null) throw new InvalidArgumentException("No calendar data for {$year} BS");

        return 29 + (($enc >> (($month - 1) << 1)) & 3);
    }

    /** "2026-08-01" (AD) → [2083, 4, 16] */
    public static function fromAd(string $ad): array
    {
        $utc  = new DateTimeZone('UTC');
        $days = (int) (new DateTimeImmutable(self::EPOCH_AD, $utc))->diff(new DateTimeImmutable(substr($ad, 0, 10), $utc))->format('%r%a') + 1;
        if ($days < 1) throw new InvalidArgumentException("Date before {$ad} is outside the BS table");

        for ($year = self::YEAR_ZERO; isset(self::ENCODED_MONTH_LENGTHS[$year - self::YEAR_ZERO]); $year++) {
            for ($m = 1; $m <= 12; $m++) {
                $dm = self::daysInMonth($year, $m);
                if ($days <= $dm) return [$year, $m, $days];
                $days -= $dm;
            }
        }
        throw new InvalidArgumentException("Date {$ad} is outside the BS table");
    }

    /** [2083, 4, 16] → "2026-08-01" */
    public static function toAd(int $year, int $month, int $day): string
    {
        if ($day < 1 || $day > self::daysInMonth($year, $month)) throw new InvalidArgumentException("Invalid BS day {$day}");

        $days = $day - 1;
        for ($y = self::YEAR_ZERO; $y < $year; $y++) {
            for ($m = 1; $m <= 12; $m++) $days += self::daysInMonth($y, $m);
        }
        for ($m = 1; $m < $month; $m++) $days += self::daysInMonth($year, $m);

        return (new DateTimeImmutable(self::EPOCH_AD, new DateTimeZone('UTC')))->modify("+{$days} days")->format('Y-m-d');
    }

    /** "2026-08-01" → "2083/04/16", the way it is printed */
    public static function format(string $ad): string
    {
        [$y, $m, $d] = self::fromAd($ad);
        return sprintf('%04d/%02d/%02d', $y, $m, $d);
    }

    /**
     * Nepali fiscal year (आर्थिक वर्ष) of an AD date: 1 Shrawan to the end of
     * Asar. 2083/04/16 → "2083/84"; 2083/03/31 → "2082/83".
     */
    public static function fiscalYear(string $ad): string
    {
        [$y, $m] = self::fromAd($ad);
        $start = $m >= 4 ? $y : $y - 1;

        return sprintf('%d/%02d', $start, ($start + 1) % 100);
    }

    /** AD range [first day, last day] of a fiscal year such as "2083/84". */
    public static function fiscalYearRange(string $fy): array
    {
        $start = (int) substr($fy, 0, 4);

        return [self::toAd($start, 4, 1), self::toAd($start + 1, 3, self::daysInMonth($start + 1, 3))];
    }
}
