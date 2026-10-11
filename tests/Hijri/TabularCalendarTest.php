<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Hijri;

use AlFalaq\AlFalaq\Hijri\Exceptions\HijriOutOfRangeException;
use AlFalaq\AlFalaq\Hijri\Strategies\TabularCalendar;
use AlFalaq\AlFalaq\Hijri\ValueObjects\HijriDate;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use PHPUnit\Framework\TestCase;
use TypeError;

/**
 * Tabular (Type IIa) calendar: anchors, leap cycle, spans, adjustments.
 */
final class TabularCalendarTest extends TestCase
{
    /** Leap years within one 30-year Type IIa cycle. */
    private const LEAP_REMAINDERS = [2, 5, 7, 10, 13, 16, 18, 21, 24, 26, 29];

    /**
     * The two pin anchors the PRD quotes.
     *
     * @return void
     */
    public function testMatchesThePinAnchors(): void
    {
        $calendar = new TabularCalendar();

        self::assertSame('1446-09-01', $calendar->fromGregorian(CivilDate::fromYmd(2025, 3, 1))->isoformat());
        self::assertSame('1356-01-01', $calendar->fromGregorian(CivilDate::fromYmd(1937, 3, 14))->isoformat());
        self::assertSame('1937-02-06', $calendar->toGregorian(new HijriDate(1355, 11, 25))->isoformat());
        self::assertSame('1937-03-14', $calendar->toGregorian(new HijriDate(1356, 1, 1))->isoformat());
    }

    /**
     * The pin's calendar key.
     *
     * @return void
     */
    public function testNameMatchesThePin(): void
    {
        self::assertSame('tabular', (new TabularCalendar())->name());
    }

    /**
     * Odd months are 30 days, even months 29, and month 12 follows the cycle.
     *
     * @return void
     */
    public function testMonthLengthsFollowTheTypeIiaPattern(): void
    {
        $calendar = new TabularCalendar();

        for ($month = 1; $month <= 11; $month++) {
            self::assertSame($month % 2 === 1 ? 30 : 29, $calendar->monthLength(1446, $month));
        }

        self::assertSame(29, $calendar->monthLength(1446, 12));
        self::assertSame(30, $calendar->monthLength(1445, 12));
    }

    /**
     * Cycle starts 1, 31 and 1411 produce the same 30-year pattern with 11 leaps.
     *
     * @return void
     */
    public function testLeapSetIsStableAcrossCycles(): void
    {
        $calendar = new TabularCalendar();
        $patterns = [];

        foreach ([1, 31, 1411] as $start) {
            $pattern = [];

            for ($offset = 0; $offset < 30; $offset++) {
                $pattern[] = $calendar->monthLength($start + $offset, 12);
            }

            self::assertCount(11, array_filter($pattern, static fn(int $length): bool => $length === 30));
            $patterns[] = $pattern;
        }

        self::assertSame($patterns[0], $patterns[1]);
        self::assertSame($patterns[0], $patterns[2]);

        $expected = [];

        foreach (range(1, 30) as $year) {
            $expected[] = in_array($year % 30, self::LEAP_REMAINDERS, true) ? 30 : 29;
        }

        self::assertSame($expected, $patterns[0]);
    }

    /**
     * A positive adjustment moves the result one day forward; negative one back.
     *
     * @return void
     */
    public function testAdjustmentDaysShiftTheConversion(): void
    {
        $date = CivilDate::fromYmd(2025, 3, 1);

        self::assertSame('1446-09-02', (new TabularCalendar(1))->fromGregorian($date)->isoformat());
        self::assertSame('1446-08-29', (new TabularCalendar(-1))->fromGregorian($date)->isoformat());
        self::assertSame('1446-09-01', (new TabularCalendar(0))->fromGregorian($date)->isoformat());
    }

    /**
     * Out-of-contract adjustments are rejected, including bools.
     *
     * @return void
     */
    public function testAdjustmentDaysIsRangeChecked(): void
    {
        // REVIEW CORRECTION: the plan text only probes the upper bound (+3).
        // Probe both ends of the [-2, 2] contract.
        try {
            new TabularCalendar(3);
            self::fail('expected ValidationException for +3');
        } catch (ValidationException) {
            self::addToAssertionCount(1);
        }

        try {
            new TabularCalendar(-3);
            self::fail('expected ValidationException for -3');
        } catch (ValidationException) {
            self::addToAssertionCount(1);
        }
    }

    /**
     * A bool is not an int: the typed ctor parameter raises a TypeError.
     *
     * @return void
     */
    public function testBoolAdjustmentIsATypeError(): void
    {
        $this->expectException(TypeError::class);
        /** @phpstan-ignore-next-line deliberate bool-for-int */
        new TabularCalendar(true);
    }

    /**
     * Year 0 is out of span; an out-of-range month is a plain validation error.
     *
     * @return void
     */
    public function testMonthLengthRangeChecks(): void
    {
        $calendar = new TabularCalendar();

        self::assertSame(30, $calendar->monthLength(2000, 1));

        try {
            $calendar->monthLength(0, 1);
            self::fail('expected HijriOutOfRangeException');
        } catch (HijriOutOfRangeException $exception) {
            self::assertStringContainsString('tabular', $exception->getMessage());
        }

        $this->expectException(ValidationException::class);
        $calendar->monthLength(1446, 13);
    }

    /**
     * Typed parameters reject non-ints with a TypeError.
     *
     * @return void
     */
    public function testNonIntMonthIsATypeError(): void
    {
        $this->expectException(TypeError::class);
        /** @phpstan-ignore-next-line deliberate contract violation */
        (new TabularCalendar())->monthLength(1446, true);
    }

    /**
     * A day beyond the month's length is rejected.
     *
     * @return void
     */
    public function testToGregorianRejectsOverlongDay(): void
    {
        $this->expectException(ValidationException::class);
        (new TabularCalendar())->toGregorian(new HijriDate(1446, 10, 30));
    }

    /**
     * 1 Muharram 1 AH is outside CivilDate's 1900–2100 span, so it raises.
     *
     * @return void
     */
    public function testToGregorianOutsideCivilDateSpanThrows(): void
    {
        $this->expectException(HijriOutOfRangeException::class);
        (new TabularCalendar())->toGregorian(new HijriDate(1, 1, 1));
    }

    /**
     * Shifting below 1 AH raises rather than wrapping.
     *
     * @return void
     */
    public function testShiftFloorThrows(): void
    {
        $this->expectException(HijriOutOfRangeException::class);
        (new TabularCalendar())->shift(new HijriDate(1, 1, 1), -1);
    }

    /**
     * Tabular has no upper ceiling: far-future years still shift.
     *
     * @return void
     */
    public function testShiftHasNoUpperCeiling(): void
    {
        $calendar = new TabularCalendar();

        self::assertSame('2000-01-02', $calendar->shift(new HijriDate(2000, 1, 1), 1)->isoformat());
    }

    /**
     * Every Hijri month whose first day lands in 1900–2100 round-trips exactly.
     *
     * @return void
     */
    public function testRoundTripsAcrossTheGoldenWindow(): void
    {
        $calendar = new TabularCalendar();
        $checked = 0;

        for ($year = 1317; $year <= 1524; $year++) {
            for ($month = 1; $month <= 12; $month++) {
                $length = $calendar->monthLength($year, $month);

                foreach ([1, 15, $length] as $day) {
                    $hijri = new HijriDate($year, $month, $day);

                    try {
                        $gregorian = $calendar->toGregorian($hijri);
                    } catch (HijriOutOfRangeException) {
                        continue;
                    }

                    self::assertSame(
                        $hijri->isoformat(),
                        $calendar->fromGregorian($gregorian)->isoformat(),
                    );
                    $checked++;
                }
            }
        }

        self::assertGreaterThan(6000, $checked);
    }
}
