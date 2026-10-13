<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Hijri;

use AlFalaq\AlFalaq\Hijri\Contracts\HijriCalendar;
use AlFalaq\AlFalaq\Hijri\Exceptions\HijriOutOfRangeException;
use AlFalaq\AlFalaq\Hijri\Strategies\TabularCalendar;
use AlFalaq\AlFalaq\Hijri\Traits\ShiftsHijriDays;
use AlFalaq\AlFalaq\Hijri\ValueObjects\HijriDate;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use PHPUnit\Framework\TestCase;
use TypeError;

/**
 * Shared Hijri day arithmetic: rollover, ranges, round-trips.
 */
final class ShiftsHijriDaysTest extends TestCase
{
    /**
     * A calendar with alternating 30/29 months and a 30-day Dhu al-Hijjah.
     *
     * @return HijriCalendar Fake implementing only what shift() touches.
     */
    private function fakeCalendar(): HijriCalendar
    {
        return new class implements HijriCalendar {
            use ShiftsHijriDays;

            /**
             * Unused by shift(); present to satisfy the contract.
             *
             * @param CivilDate $date Ignored.
             * @return HijriDate Never returns.
             * @throws \LogicException Always.
             */
            public function fromGregorian(CivilDate $date): HijriDate
            {
                throw new \LogicException('fake');
            }

            /**
             * Unused by shift(); present to satisfy the contract.
             *
             * @param HijriDate $date Ignored.
             * @return CivilDate Never returns.
             * @throws \LogicException Always.
             */
            public function toGregorian(HijriDate $date): CivilDate
            {
                throw new \LogicException('fake');
            }

            /**
             * Alternating 30/29 lengths; year 0 is out of range.
             *
             * @param int $year Hijri year.
             * @param int $month Hijri month 1–12.
             * @return int 29 or 30.
             */
            public function monthLength(int $year, int $month): int
            {
                if ($year < 1) {
                    throw HijriOutOfRangeException::forDate('fake', sprintf('%04d-%02d', $year, $month), '1 AH onward');
                }

                if ($month === 12) {
                    return 30;
                }

                return $month % 2 === 1 ? 30 : 29;
            }

            /**
             * Fake key.
             *
             * @return string Always 'fake'.
             */
            public function name(): string
            {
                return 'fake';
            }
        };
    }

    /**
     * Zero days is the identity and performs no range probe.
     *
     * @return void
     */
    public function testZeroDaysIsAProbeFreeIdentity(): void
    {
        $start = new HijriDate(9999, 12, 30);

        self::assertSame($start, $this->fakeCalendar()->shift($start, 0));
    }

    /**
     * Forward rollover crosses the month and year boundary.
     *
     * @return void
     */
    public function testForwardRollover(): void
    {
        $calendar = $this->fakeCalendar();

        self::assertSame('1446-10-01', $calendar->shift(new HijriDate(1446, 9, 30), 1)->isoformat());
        self::assertSame('1447-01-01', $calendar->shift(new HijriDate(1446, 12, 30), 1)->isoformat());
        self::assertSame('1447-01-07', $calendar->shift(new HijriDate(1446, 12, 30), 7)->isoformat());
    }

    /**
     * Backward rollover lands on the previous month's true length.
     *
     * @return void
     */
    public function testBackwardRollover(): void
    {
        $calendar = $this->fakeCalendar();

        self::assertSame('1446-08-29', $calendar->shift(new HijriDate(1446, 9, 1), -1)->isoformat());
        self::assertSame('1446-12-30', $calendar->shift(new HijriDate(1447, 1, 1), -1)->isoformat());
    }

    /**
     * Shifting out and back is lossless.
     *
     * @return void
     */
    public function testRoundTripIsLossless(): void
    {
        $calendar = $this->fakeCalendar();
        $start = new HijriDate(1446, 9, 1);

        foreach ([-400, -5, -1, 1, 7, 354] as $days) {
            $shifted = $calendar->shift($start, $days);
            self::assertSame($start->isoformat(), $calendar->shift($shifted, -$days)->isoformat(), (string) $days);
        }
    }

    /**
     * Crossing below 1 AH raises rather than wrapping.
     *
     * @return void
     */
    public function testFloorViolationThrows(): void
    {
        $this->expectException(HijriOutOfRangeException::class);
        $this->fakeCalendar()->shift(new HijriDate(1, 1, 1), -1);
    }

    /**
     * Shift-count type errors stay TypeErrors.
     *
     * @return void
     */
    public function testNonIntDayCountIsATypeError(): void
    {
        $this->expectException(TypeError::class);
        $calendar = $this->fakeCalendar();
        /** @phpstan-ignore-next-line deliberate contract violation */
        $calendar->shift(new HijriDate(1446, 9, 1), true);
    }

    public function testShiftBoundAcceptsTenYears(): void
    {
        $calendar = new TabularCalendar();
        $start = new HijriDate(1446, 9, 1);
        $shifted = $calendar->shift($start, 3650);

        self::assertSame($start->isoformat(), $calendar->shift($shifted, -3650)->isoformat());
    }

    public function testShiftBeyondBoundThrows(): void
    {
        $this->expectException(ValidationException::class);

        (new TabularCalendar())->shift(new HijriDate(1446, 9, 1), 3651);
    }

    public function testShiftIntMinThrowsFast(): void
    {
        $this->expectException(ValidationException::class);

        (new TabularCalendar())->shift(new HijriDate(1446, 9, 1), PHP_INT_MIN);
    }

    public function testShiftIntMaxThrowsFast(): void
    {
        $this->expectException(ValidationException::class);

        (new TabularCalendar())->shift(new HijriDate(1446, 9, 1), PHP_INT_MAX);
    }

    public function testMonthStepRoundTripSweep(): void
    {
        $calendar = new TabularCalendar();

        for ($month = 1; $month <= 12; $month++) {
            $length = $calendar->monthLength(1446, $month);

            foreach ([1, $length] as $day) {
                $start = new HijriDate(1446, $month, $day);

                foreach ([7, 30, 59, 365, 3650] as $days) {
                    $shifted = $calendar->shift($start, $days);

                    self::assertSame($start->isoformat(), $calendar->shift($shifted, -$days)->isoformat(), sprintf('%s +%d', $start->isoformat(), $days));
                }
            }
        }
    }
}
