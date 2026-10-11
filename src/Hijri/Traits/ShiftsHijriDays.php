<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Hijri\Traits;

use AlFalaq\AlFalaq\Hijri\ValueObjects\HijriDate;

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

    /**
     * Shift a Hijri date by whole Hijri days.
     *
     * @param HijriDate $date Starting date.
     * @param int $days Signed day count; zero is the identity with no range probe.
     * @return HijriDate Shifted date.
     */
    public function shift(HijriDate $date, int $days): HijriDate
    {
        if ($days === 0) {
            return $date;
        }

        $year = $date->year;
        $month = $date->month;
        $day = $date->day;
        $step = $days > 0 ? 1 : -1;
        $remaining = abs($days);

        while ($remaining > 0) {
            $day += $step;

            if ($day > $this->monthLength($year, $month)) {
                if ($month === 12) {
                    $year += 1;
                    $month = 1;
                } else {
                    $month += 1;
                }

                $day = 1;
            } elseif ($day < 1) {
                if ($month === 1) {
                    $year -= 1;
                    $month = 12;
                } else {
                    $month -= 1;
                }

                $day = $this->monthLength($year, $month);
            }

            $remaining -= 1;
        }

        // Final span probe. A forward shift whose last step rolls the month
        // over would otherwise return the sentinel month (e.g. 1501-01) instead
        // of raising; the loop's own probes only cover months it revisits.
        $this->monthLength($year, $month);

        return new HijriDate($year, $month, $day);
    }
}
