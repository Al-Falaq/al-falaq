<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Hijri;

use AlFalaq\AlFalaq\Hijri\Data\UmmAlQuraMonthStarts;
use AlFalaq\AlFalaq\Hijri\Exceptions\HijriOutOfRangeException;
use AlFalaq\AlFalaq\Hijri\Strategies\UmmAlQuraCalendar;
use AlFalaq\AlFalaq\Hijri\ValueObjects\HijriDate;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use TypeError;

/**
 * Umm al-Qura table calendar: anchors, spans, invariants, round-trips.
 */
final class UmmAlQuraCalendarTest extends TestCase
{
    /**
     * The pin's calendar key.
     *
     * @return void
     */
    public function testNameMatchesThePin(): void
    {
        self::assertSame('uqu', (new UmmAlQuraCalendar())->name());
    }

    /**
     * The table floor and the PRD anchor.
     *
     * @return void
     */
    public function testMatchesThePinAnchors(): void
    {
        $calendar = new UmmAlQuraCalendar();

        self::assertSame('1423-01-01', $calendar->fromGregorian(CivilDate::fromYmd(2002, 3, 15))->isoformat());
        self::assertSame('1446-09-01', $calendar->fromGregorian(CivilDate::fromYmd(2025, 3, 1))->isoformat());
    }

    /**
     * One day below the floor raises, naming the supported span.
     *
     * @return void
     */
    public function testBelowFloorThrowsWithRangeLabel(): void
    {
        try {
            (new UmmAlQuraCalendar())->fromGregorian(CivilDate::fromYmd(2002, 3, 14));
            self::fail('expected HijriOutOfRangeException');
        } catch (HijriOutOfRangeException $exception) {
            self::assertStringContainsString('uqu', $exception->getMessage());
            self::assertStringContainsString('2002-03-14', $exception->getMessage());
            self::assertStringContainsString(
                sprintf('%d–%d AH', UmmAlQuraMonthStarts::FIRST_YEAR, UmmAlQuraMonthStarts::LAST_YEAR),
                $exception->getMessage(),
            );
        }
    }

    /**
     * The sentinel day is one past the last supported month.
     *
     * @return void
     */
    public function testSentinelDayIsOutOfRange(): void
    {
        $starts = UmmAlQuraMonthStarts::STARTS;
        $sentinel = new DateTimeImmutable($starts[count($starts) - 1], new DateTimeZone('UTC'));
        $date = CivilDate::fromYmd(
            (int) $sentinel->format('Y'),
            (int) $sentinel->format('n'),
            (int) $sentinel->format('j'),
        );

        $this->expectException(HijriOutOfRangeException::class);
        (new UmmAlQuraCalendar())->fromGregorian($date);
    }

    /**
     * Month lengths are range-checked at both ends.
     *
     * @return void
     */
    public function testMonthLengthRangeChecks(): void
    {
        $calendar = new UmmAlQuraCalendar();

        self::assertContains($calendar->monthLength(UmmAlQuraMonthStarts::LAST_YEAR, 12), [29, 30]);

        foreach ([[UmmAlQuraMonthStarts::FIRST_YEAR - 1, 12], [UmmAlQuraMonthStarts::LAST_YEAR + 1, 1]] as [$year, $month]) {
            try {
                $calendar->monthLength($year, $month);
                self::fail('expected HijriOutOfRangeException');
            } catch (HijriOutOfRangeException) {
                self::addToAssertionCount(1);
            }
        }

        $this->expectException(ValidationException::class);
        $calendar->monthLength(UmmAlQuraMonthStarts::FIRST_YEAR, 13);
    }

    /**
     * A non-int month is a TypeError, not a validation error.
     *
     * @return void
     */
    public function testNonIntMonthIsATypeError(): void
    {
        $this->expectException(TypeError::class);
        /** @phpstan-ignore-next-line deliberate contract violation */
        (new UmmAlQuraCalendar())->monthLength(UmmAlQuraMonthStarts::FIRST_YEAR, true);
    }

    /**
     * toGregorian enforces the month length and its own span.
     *
     * @return void
     */
    public function testToGregorianGuards(): void
    {
        $calendar = new UmmAlQuraCalendar();

        self::assertSame('2002-03-15', $calendar->toGregorian(new HijriDate(1423, 1, 1))->isoformat());

        try {
            $calendar->toGregorian(new HijriDate(UmmAlQuraMonthStarts::LAST_YEAR + 1, 1, 1));
            self::fail('expected HijriOutOfRangeException');
        } catch (HijriOutOfRangeException) {
            self::addToAssertionCount(1);
        }

        // 1423-02 has 29 days, so day 30 passes the HijriDate constructor
        // and must be rejected by toGregorian()'s month-length enforcement.
        $this->expectException(ValidationException::class);
        $calendar->toGregorian(new HijriDate(1423, 2, 30));
    }

    /**
     * Shifting below the floor raises.
     *
     * @return void
     */
    public function testShiftFloorThrows(): void
    {
        $this->expectException(HijriOutOfRangeException::class);
        (new UmmAlQuraCalendar())->shift(new HijriDate(1423, 1, 1), -1);
    }

    /**
     * A forward shift off the ceiling raises instead of returning 1501-01-01.
     *
     * @return void
     */
    public function testShiftCeilingThrows(): void
    {
        $calendar = new UmmAlQuraCalendar();
        $year = UmmAlQuraMonthStarts::LAST_YEAR;
        $month = UmmAlQuraMonthStarts::LAST_MONTH;
        $length = $calendar->monthLength($year, $month);

        $this->expectException(HijriOutOfRangeException::class);
        $calendar->shift(new HijriDate($year, $month, $length), 1);
    }

    /**
     * Every supported month is contiguous, 29/30 days, and year totals are 354/355 — except 1430 AH, a genuine 353-day year in the pin's rule.
     *
     * @return void
     */
    public function testTableInvariants(): void
    {
        $calendar = new UmmAlQuraCalendar();
        $starts = UmmAlQuraMonthStarts::STARTS;

        $previous = new DateTimeImmutable($starts[0], new DateTimeZone('UTC'));

        for ($index = 1; $index < count($starts); $index++) {
            $current = new DateTimeImmutable($starts[$index], new DateTimeZone('UTC'));
            self::assertContains((int) $previous->diff($current)->days, [29, 30]);
            $previous = $current;
        }

        $year = UmmAlQuraMonthStarts::FIRST_YEAR;
        $month = UmmAlQuraMonthStarts::FIRST_MONTH;
        $months = 0;
        $yearTotal = 0;
        $yearTotals = [];

        for ($index = 0; $index < count($starts) - 1; $index++) {
            $yearTotal += $calendar->monthLength($year, $month);
            $months++;

            if ($month === 12) {
                $yearTotals[] = $yearTotal;
                $yearTotal = 0;
            }

            [$year, $month] = $month === 12 ? [$year + 1, 1] : [$year, $month + 1];
        }

        self::assertSame(count($starts) - 1, $months);
        self::assertEqualsWithDelta(
            (UmmAlQuraMonthStarts::LAST_YEAR - UmmAlQuraMonthStarts::FIRST_YEAR + 1) * 12,
            $months,
            0,
        );
        self::assertCount(UmmAlQuraMonthStarts::LAST_YEAR - UmmAlQuraMonthStarts::FIRST_YEAR + 1, $yearTotals);

        // 1430 AH is a genuine 353-day year in the pin's Umm al-Qura rule
        // (five 30-day months) — verified against the pin, not a table
        // defect. It must be the only such year; every other supported year
        // is 354/355 days.
        $shortYears = array_keys(array_filter(
            $yearTotals,
            static fn(int $total): bool => $total === 353,
        ));
        self::assertSame([1430 - UmmAlQuraMonthStarts::FIRST_YEAR], $shortYears, 'only 1430 AH totals 353 days');

        foreach ($yearTotals as $total) {
            self::assertContains($total, [353, 354, 355]);
        }
    }

    /**
     * The embedded digest matches the canonical recomputation.
     *
     * @return void
     */
    public function testCanonicalDigestMatches(): void
    {
        self::assertSame(
            UmmAlQuraMonthStarts::SHA256,
            hash('sha256', implode("\n", UmmAlQuraMonthStarts::STARTS)),
        );
    }

    /**
     * Day 1, day 15 and the last day of every month round-trip exactly.
     *
     * @return void
     */
    public function testRoundTripsForEverySupportedMonth(): void
    {
        $calendar = new UmmAlQuraCalendar();
        $year = UmmAlQuraMonthStarts::FIRST_YEAR;
        $month = UmmAlQuraMonthStarts::FIRST_MONTH;
        $checked = 0;

        for ($index = 0; $index < count(UmmAlQuraMonthStarts::STARTS) - 1; $index++) {
            $length = $calendar->monthLength($year, $month);

            foreach ([1, 15, $length] as $day) {
                $hijri = new HijriDate($year, $month, $day);
                self::assertSame($hijri->isoformat(), $calendar->fromGregorian($calendar->toGregorian($hijri))->isoformat());
                $checked++;
            }

            [$year, $month] = $month === 12 ? [$year + 1, 1] : [$year, $month + 1];
        }

        self::assertGreaterThan(2500, $checked);
    }
}
