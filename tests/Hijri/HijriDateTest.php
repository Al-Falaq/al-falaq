<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Hijri;

use AlFalaq\AlFalaq\Hijri\ValueObjects\HijriDate;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

/**
 * HijriDate bounds and ordering (spec rev2 §3.4).
 */
final class HijriDateTest extends TestCase
{
    /**
     * Years below 1 AH are rejected (pin HijriDate.py:29-30).
     *
     * @return void
     */
    public function testRejectsYearBelowOne(): void
    {
        $this->expectException(ValidationException::class);
        new HijriDate(0, 1, 1);
    }

    /**
     * Months outside 1..12 are rejected.
     *
     * @return void
     */
    public function testRejectsMonthOutsideRange(): void
    {
        $this->expectException(ValidationException::class);
        new HijriDate(1446, 13, 1);
    }

    /**
     * Days outside the universal 1..30 bound are rejected.
     *
     * @return void
     */
    public function testRejectsDayOutsideUniversalBound(): void
    {
        $this->expectException(ValidationException::class);
        new HijriDate(1446, 9, 31);
    }

    /**
     * isoformat zero-pads year/month/day.
     *
     * @return void
     */
    public function testIsoformatIsZeroPadded(): void
    {
        self::assertSame('1446-09-01', (new HijriDate(1446, 9, 1))->isoformat());
    }

    /**
     * compareTo orders by year, then month, then day.
     *
     * @return void
     */
    public function testCompareToOrdersLexicographically(): void
    {
        $base = new HijriDate(1446, 9, 1);

        self::assertSame(-1, $base->compareTo(new HijriDate(1446, 9, 2)));
        self::assertSame(1, $base->compareTo(new HijriDate(1446, 8, 30)));
        self::assertSame(0, $base->compareTo(new HijriDate(1446, 9, 1)));
        self::assertTrue($base->equals(new HijriDate(1446, 9, 1)));
    }

    /**
     * plusDays was removed from the contract (spec §10 [A3]).
     *
     * @return void
     */
    public function testNoLongerExposesPlusDays(): void
    {
        self::assertFalse(method_exists(HijriDate::class, 'plusDays')); // @phpstan-ignore function.impossibleType (always-false is the asserted contract)
    }
}
