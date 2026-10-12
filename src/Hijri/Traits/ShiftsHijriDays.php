<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Hijri\Traits;

use AlFalaq\AlFalaq\Hijri\ValueObjects\HijriDate;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;

/**
 * Shared `HijriCalendar::shift()` — one port of the pin's day walk.
 *
 * Requires the using class to implement `monthLength(int, int): int`; every
 * step is range-checked through it, so a shift off either end of a calendar's
 * span raises instead of wrapping.
 */
trait ShiftsHijriDays
{
    /**
     * Number of days in one Hijri month, supplied by the using calendar.
     *
     * @param int $year Hijri year.
     * @param int $month Hijri month 1–12.
     * @return int 29 or 30.
     */
    abstract public function monthLength(int $year, int $month): int;

    /** Maximum shift magnitude in days (~10 years); beyond it shift() raises instead of walking. */
    private const MAX_SHIFT_DAYS = 3650;

    /**
     * Shift a Hijri date by whole Hijri days.
     *
     * The starting date must be valid in the using calendar (day within the month's length); results for impossible start dates are undefined.
     *
     * @param HijriDate $date Starting date.
     * @param int $days Signed day count within [-3650, 3650]; zero is the identity with no range probe.
     * @return HijriDate Shifted date.
     */
    public function shift(HijriDate $date, int $days): HijriDate
    {
        if ($days === 0) {
            return $date;
        }

        if ($days > self::MAX_SHIFT_DAYS || $days < -self::MAX_SHIFT_DAYS) {
            throw new ValidationException(sprintf(
                'Shift magnitude must be within [-%d, %d] days (~10 years), got %d.',
                self::MAX_SHIFT_DAYS,
                self::MAX_SHIFT_DAYS,
                $days,
            ));
        }

        $year = $date->year;
        $month = $date->month;
        $day = $date->day;
        $remaining = $days > 0 ? $days : -$days;

        if ($days > 0) {
            while ($remaining > 0) {
                $toMonthEnd = $this->monthLength($year, $month) - $day;

                if ($remaining > $toMonthEnd) {
                    $remaining -= ($toMonthEnd + 1);

                    if ($month === 12) {
                        $year += 1;
                        $month = 1;
                    } else {
                        $month += 1;
                    }

                    $this->monthLength($year, $month); // Probe: raises off-span.
                    $day = 1;
                } else {
                    $day += $remaining;
                    $remaining = 0;
                }
            }
        } else {
            while ($remaining > 0) {
                $toMonthStart = $day - 1;

                if ($remaining > $toMonthStart) {
                    $remaining -= ($toMonthStart + 1);

                    if ($month === 1) {
                        $year -= 1;
                        $month = 12;
                    } else {
                        $month -= 1;
                    }

                    $day = $this->monthLength($year, $month);
                } else {
                    $day -= $remaining;
                    $remaining = 0;
                }
            }
        }

        // Final span probe. A forward shift whose last step rolls the month
        // over would otherwise return the sentinel month (e.g. 1501-01) instead
        // of raising; the loop's own probes only cover months it revisits.
        $this->monthLength($year, $month);

        return new HijriDate($year, $month, $day);
    }
}
