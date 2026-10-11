<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Hijri\Strategies;

use AlFalaq\AlFalaq\Hijri\Contracts\HijriCalendar;
use AlFalaq\AlFalaq\Hijri\Traits\ShiftsHijriDays;
use AlFalaq\AlFalaq\Hijri\ValueObjects\HijriDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;

/**
 * Tabular (Kuwaiti algorithm) calendar. Arithmetic slice lands in M4 Task 3.
 */
final class TabularCalendar implements HijriCalendar
{
    use ShiftsHijriDays;

    /**
     * Not implemented until the arithmetic slice (M4 Task 3).
     *
     * @param CivilDate $date Gregorian day.
     * @return HijriDate Never returns.
     * @throws \LogicException Always.
     */
    public function fromGregorian(CivilDate $date): HijriDate
    {
        throw new \LogicException('TabularCalendar::fromGregorian lands in M4 Task 3.');
    }

    /**
     * Not implemented until the arithmetic slice (M4 Task 3).
     *
     * @param HijriDate $date Hijri date.
     * @return CivilDate Never returns.
     * @throws \LogicException Always.
     */
    public function toGregorian(HijriDate $date): CivilDate
    {
        throw new \LogicException('TabularCalendar::toGregorian lands in M4 Task 3.');
    }

    /**
     * Not implemented until the arithmetic slice (M4 Task 3).
     *
     * @param int $year Hijri year.
     * @param int $month Hijri month 1–12.
     * @return int Never returns.
     * @throws \LogicException Always.
     */
    public function monthLength(int $year, int $month): int
    {
        throw new \LogicException('TabularCalendar::monthLength lands in M4 Task 3.');
    }

    /**
     * Stable calendar key.
     *
     * @return string Always 'tabular'.
     */
    public function name(): string
    {
        return 'tabular';
    }
}
