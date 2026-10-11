<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Hijri;

use AlFalaq\AlFalaq\Hijri\Data\MabimsMonthStarts;
use AlFalaq\AlFalaq\Hijri\Enums\MabimsCountry;
use AlFalaq\AlFalaq\Hijri\Exceptions\HijriOutOfRangeException;
use AlFalaq\AlFalaq\Hijri\Strategies\MabimsCalendar;
use AlFalaq\AlFalaq\Hijri\ValueObjects\HijriDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use PHPUnit\Framework\TestCase;

/**
 * Per-country MABIMS table calendar: anchors, span, invariants, round-trips.
 */
final class MabimsCalendarTest extends TestCase
{
    /** The Gregorian day of 1 Muharram 1445 AH for every member state. */
    private const ANCHOR = '2023-07-19';

    /**
     * The pin's calendar key, independent of country.
     *
     * @return void
     */
    public function testNameMatchesThePin(): void
    {
        foreach (MabimsCountry::cases() as $country) {
            self::assertSame('mabims', (new MabimsCalendar($country))->name());
        }
    }

    /**
     * Every member state's table starts at the same pinned anchor.
     *
     * @return void
     */
    public function testAnchorHoldsForEveryCountry(): void
    {
        self::assertSame('2023-07-19', self::ANCHOR);

        foreach (MabimsCountry::cases() as $country) {
            $calendar = new MabimsCalendar($country);

            self::assertSame(
                '1445-01-01',
                $calendar->fromGregorian(CivilDate::fromYmd(2023, 7, 19))->isoformat(),
                $country->value,
            );
        }
    }

    /**
     * One day before the anchor is out of span for every country.
     *
     * @return void
     */
    public function testBelowFloorThrows(): void
    {
        $this->expectException(HijriOutOfRangeException::class);
        (new MabimsCalendar(MabimsCountry::MY))->fromGregorian(CivilDate::fromYmd(2023, 7, 18));
    }

    /**
     * Each country carries its own digest, and each matches its table.
     *
     * @return void
     */
    public function testDigestsMatchPerCountryTables(): void
    {
        foreach (MabimsCountry::cases() as $country) {
            self::assertSame(
                MabimsMonthStarts::digestFor($country),
                hash('sha256', implode("\n", MabimsMonthStarts::forCountry($country))),
                $country->value,
            );
        }
    }

    /**
     * Every country's table is contiguous, 29/30 days, 354/355-day years.
     *
     * @return void
     */
    public function testTableInvariantsForEveryCountry(): void
    {
        foreach (MabimsCountry::cases() as $country) {
            $calendar = new MabimsCalendar($country);
            $starts = MabimsMonthStarts::forCountry($country);

            self::assertCount(
                (MabimsMonthStarts::LAST_YEAR - MabimsMonthStarts::FIRST_YEAR + 1) * 12 + 1,
                $starts,
                $country->value,
            );

            $year = MabimsMonthStarts::FIRST_YEAR;
            $month = MabimsMonthStarts::FIRST_MONTH;
            $yearTotal = 0;

            for ($index = 0; $index < count($starts) - 1; $index++) {
                $yearTotal += $calendar->monthLength($year, $month);

                if ($month === 12) {
                    self::assertContains($yearTotal, [354, 355], $country->value);
                    $yearTotal = 0;
                }

                [$year, $month] = $month === 12 ? [$year + 1, 1] : [$year, $month + 1];
            }
        }
    }

    /**
     * Shifting off either end of a country's span raises.
     *
     * @return void
     */
    public function testShiftSpanThrows(): void
    {
        $calendar = new MabimsCalendar(MabimsCountry::SG);

        try {
            $calendar->shift(new HijriDate(MabimsMonthStarts::FIRST_YEAR, 1, 1), -1);
            self::fail('expected HijriOutOfRangeException');
        } catch (HijriOutOfRangeException) {
            self::addToAssertionCount(1);
        }

        $year = MabimsMonthStarts::LAST_YEAR;
        $month = MabimsMonthStarts::LAST_MONTH;
        $length = $calendar->monthLength($year, $month);

        $this->expectException(HijriOutOfRangeException::class);
        $calendar->shift(new HijriDate($year, $month, $length), 1);
    }

    /**
     * Day 1, day 15 and the last day of every month round-trip, per country.
     *
     * @return void
     */
    public function testRoundTripsForEveryCountry(): void
    {
        $checked = 0;

        foreach (MabimsCountry::cases() as $country) {
            $calendar = new MabimsCalendar($country);
            $year = MabimsMonthStarts::FIRST_YEAR;
            $month = MabimsMonthStarts::FIRST_MONTH;
            $months = count(MabimsMonthStarts::forCountry($country)) - 1;

            for ($index = 0; $index < $months; $index++) {
                $length = $calendar->monthLength($year, $month);

                foreach ([1, 15, $length] as $day) {
                    $hijri = new HijriDate($year, $month, $day);
                    self::assertSame(
                        $hijri->isoformat(),
                        $calendar->fromGregorian($calendar->toGregorian($hijri))->isoformat(),
                        $country->value,
                    );
                    $checked++;
                }

                [$year, $month] = $month === 12 ? [$year + 1, 1] : [$year, $month + 1];
            }
        }

        self::assertGreaterThan(7500, $checked);
    }
}
