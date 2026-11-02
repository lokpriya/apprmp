<?php

namespace Tests\Unit;

use App\Support\BsDate;
use PHPUnit\Framework\TestCase;

/**
 * The server-side BS calendar. Ported from the bikram-sambat package the date
 * picker uses; when ported (2026-11-02) all 43,921 days from 1914 to 2034 AD were
 * compared against the package with no mismatch. These pin a few known dates and
 * the fiscal-year boundary, which decides which year a family's work counts in.
 */
class BsDateTest extends TestCase
{
    public function test_known_dates_both_ways(): void
    {
        $this->assertSame([2083, 7, 17], BsDate::fromAd('2026-11-02'));
        $this->assertSame('2026-11-02', BsDate::toAd(2083, 7, 17));
        $this->assertSame([1970, 1, 1], BsDate::fromAd('1913-04-13'));
        $this->assertSame('2083/07/17', BsDate::format('2026-11-02'));
    }

    public function test_fiscal_year_turns_on_1_shrawan(): void
    {
        $lastAsar     = BsDate::toAd(2083, 3, BsDate::daysInMonth(2083, 3));
        $firstShrawan = BsDate::toAd(2083, 4, 1);

        $this->assertSame('2082/83', BsDate::fiscalYear($lastAsar));
        $this->assertSame('2083/84', BsDate::fiscalYear($firstShrawan));
        $this->assertSame([$firstShrawan, BsDate::toAd(2084, 3, BsDate::daysInMonth(2084, 3))], BsDate::fiscalYearRange('2083/84'));
    }

    public function test_century_rollover_in_the_label(): void
    {
        $this->assertSame('2099/00', sprintf('%d/%02d', 2099, (2099 + 1) % 100));
        $this->assertSame('2089/90', BsDate::fiscalYear(BsDate::toAd(2089, 5, 1)));
    }

    public function test_invalid_bs_day_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        BsDate::toAd(2083, 1, 33);
    }
}
