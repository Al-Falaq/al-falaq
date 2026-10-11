<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Hijri\Strategies;

use AlFalaq\AlFalaq\Hijri\Contracts\HijriCalendar;
use AlFalaq\AlFalaq\Hijri\Exceptions\HijriOutOfRangeException;
use AlFalaq\AlFalaq\Hijri\Traits\ShiftsHijriDays;
use AlFalaq\AlFalaq\Hijri\ValueObjects\HijriDate;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\JulianDay;

/**
 * Arithmetic Type IIa ("Kuwaiti algorithm") Hijri calendar — closed form, no data.
 *
 * Ported from the pin's `TabularCalendar`. Tabular dates routinely differ from
 * observed (sighting-based) months by ±1–2 days; never present them as observed.
 * `adjustmentDays` plays the pin's `adjustment_days` role (the .NET
 * `HijriAdjustment` nudge): it shifts the whole conversion by N days.
 */
final class TabularCalendar implements HijriCalendar
{
    use ShiftsHijriDays;

    /** 1 Muharram 1 AH (civil day start) = 622-07-19 proleptic Gregorian. */
    private const EPOCH_JD = 1948439.5;

    /** Leap-year remainders mod 30 (Type IIa: 11 leaps of 355 days per cycle). */
    private const LEAP_YEARS_IN_CYCLE = [2, 5, 7, 10, 13, 16, 18, 21, 24, 26, 29];

    /** Supported span, used in range errors. */
    private const RANGE = '1 Muharram 1 AH onward';

    /** Whole-day conversion nudge in [-2, 2]. */
    private readonly int $adjustmentDays;

    /**
     * Bind an optional whole-day adjustment.
     *
     * @param int $adjustmentDays Signed days in [-2, 2]; default 0.
     */
    public function __construct(int $adjustmentDays = 0)
    {
        if ($adjustmentDays < -2 || $adjustmentDays > 2) {
            throw new ValidationException(sprintf(
                "TabularCalendar 'adjustmentDays' must be within [-2, 2], got %d.",
                $adjustmentDays,
            ));
        }

        $this->adjustmentDays = $adjustmentDays;
    }

    /**
     * Stable calendar key.
     *
     * @return string Always 'tabular' (the pin's key).
     */
    public function name(): string
    {
        return 'tabular';
    }

    /**
     * Tabular month length: odd 30, even 29, Dhu al-Hijjah 30 in leap years.
     *
     * @param int $year Hijri year, >= 1.
     * @param int $month Hijri month 1–12.
     * @return int 29 or 30.
     */
    public function monthLength(int $year, int $month): int
    {
        if ($year < 1) {
            throw HijriOutOfRangeException::forDate('tabular', sprintf('%04d-%02d', $year, $month), self::RANGE);
        }

        if ($month < 1 || $month > 12) {
            throw new ValidationException(sprintf('TabularCalendar month must be within [1, 12], got %d.', $month));
        }

        if ($month === 12) {
            return in_array($year % 30, self::LEAP_YEARS_IN_CYCLE, true) ? 30 : 29;
        }

        return $month % 2 === 1 ? 30 : 29;
    }

    /**
     * Convert a Gregorian day to its tabular Hijri date.
     *
     * @param CivilDate $date Gregorian day.
     * @return HijriDate Matching tabular date.
     */
    public function fromGregorian(CivilDate $date): HijriDate
    {
        $jd = JulianDay::fromCivilDate($date)->toFloat() + (float) $this->adjustmentDays;

        if ($jd < self::EPOCH_JD) {
            throw HijriOutOfRangeException::forDate('tabular', $date->isoformat(), self::RANGE);
        }

        return $this->jdToHijri($jd);
    }

    /**
     * Convert a tabular Hijri date to the Gregorian day it starts on.
     *
     * @param HijriDate $date Tabular Hijri date; month length enforced here.
     * @return CivilDate Matching Gregorian day.
     */
    public function toGregorian(HijriDate $date): CivilDate
    {
        $length = $this->monthLength($date->year, $date->month);

        if ($date->day > $length) {
            throw new ValidationException(sprintf(
                'TabularCalendar: day %d exceeds the length (%d days) of tabular month %d in year %d.',
                $date->day,
                $length,
                $date->month,
                $date->year,
            ));
        }

        $jd = $this->hijriToJd($date->year, $date->month, $date->day) - (float) $this->adjustmentDays;

        try {
            return $this->jdToGregorian($jd);
        } catch (ValidationException) {
            throw HijriOutOfRangeException::forDate('tabular', $date->isoformat(), self::RANGE);
        }
    }

    /**
     * Julian Date of a tabular Hijri date (midnight start, .5 fraction).
     *
     * @param int $year Hijri year, >= 1.
     * @param int $month Hijri month 1–12.
     * @param int $day Hijri day 1–30.
     * @return float Julian Date.
     */
    private function hijriToJd(int $year, int $month, int $day): float
    {
        return (float) $day
            + ceil(29.5 * ($month - 1))
            + (float) (($year - 1) * 354)
            + floor((3 + 11 * $year) / 30)
            + self::EPOCH_JD
            - 1.0;
    }

    /**
     * Tabular Hijri date for a Julian Date.
     *
     * @param float $jd Julian Date.
     * @return HijriDate Matching tabular date.
     */
    private function jdToHijri(float $jd): HijriDate
    {
        $jd = floor($jd) + 0.5;
        $year = (int) floor((30 * ($jd - self::EPOCH_JD) + 10646) / 10631);
        $month = min(12, (int) ceil(($jd - (29 + $this->hijriToJd($year, 1, 1))) / 29.5) + 1);
        $day = (int) ($jd - $this->hijriToJd($year, $month, 1) + 1);

        return new HijriDate($year, $month, $day);
    }

    /**
     * Proleptic Gregorian civil day for a Julian Date (Fliegel–Van Flandern).
     *
     * @param float $jd Julian Date.
     * @return CivilDate Matching Gregorian day.
     */
    private function jdToGregorian(float $jd): CivilDate
    {
        $jdn = (int) floor($jd + 0.5);
        $v = $jdn + 68569;
        $n = intdiv(4 * $v, 146097);
        $v -= intdiv(146097 * $n + 3, 4);
        $i = intdiv(4000 * ($v + 1), 1461001);
        $v = $v - intdiv(1461 * $i, 4) + 31;
        $j = intdiv(80 * $v, 2447);
        $day = $v - intdiv(2447 * $j, 80);
        $v = intdiv($j, 11);
        $month = $j + 2 - 12 * $v;
        $year = 100 * ($n - 49) + $i + $v;

        return CivilDate::fromYmd($year, $month, $day);
    }
}
