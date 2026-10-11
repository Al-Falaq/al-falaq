<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Hijri\Contracts;

use AlFalaq\AlFalaq\Hijri\ValueObjects\HijriDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;

/**
 * A Hijri calendar system: a Gregorian↔Hijri mapping plus Hijri day arithmetic.
 *
 * Implementations are stateless Strategies. Conversions never saturate and
 * never wrap: a date outside a table-backed calendar's span, or a Hijri date
 * that maps outside `CivilDate`'s 1900–2100 range, raises
 * `HijriOutOfRangeException`.
 */
interface HijriCalendar
{
    /**
     * Convert one Gregorian civil day to its Hijri date.
     *
     * @param CivilDate $date Gregorian day.
     * @return HijriDate Matching Hijri date.
     */
    public function fromGregorian(CivilDate $date): HijriDate;

    /**
     * Convert one Hijri date to the Gregorian day it starts on.
     *
     * @param HijriDate $date Hijri date; its month length is enforced here.
     * @return CivilDate Matching Gregorian day.
     */
    public function toGregorian(HijriDate $date): CivilDate;

    /**
     * Number of days in one Hijri month.
     *
     * @param int $year Hijri year.
     * @param int $month Hijri month 1–12.
     * @return int 29 or 30.
     */
    public function monthLength(int $year, int $month): int;

    /**
     * Shift a Hijri date by whole Hijri days.
     *
     * @param HijriDate $date Starting date.
     * @param int $days Signed day count; zero is the identity.
     * @return HijriDate Shifted date.
     */
    public function shift(HijriDate $date, int $days): HijriDate;

    /**
     * Stable calendar key, matching the pin's `name`.
     *
     * @return string One of 'tabular', 'uqu', 'mabims'.
     */
    public function name(): string;
}
