<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Hijri\Strategies;

use AlFalaq\AlFalaq\Hijri\Contracts\HijriCalendar;
use AlFalaq\AlFalaq\Hijri\Traits\ShiftsHijriDays;
use AlFalaq\AlFalaq\Hijri\ValueObjects\HijriDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;

/**
 * Umm al-Qura (Saudi) calendar. Table-backed slice lands in M4 Task 4.
 */
final class UmmAlQuraCalendar implements HijriCalendar
{
    use ShiftsHijriDays;

    /**
     * Not implemented until the table slice (M4 Task 4).
     *
     * @param CivilDate $date Gregorian day.
     * @return HijriDate Never returns.
     * @throws \LogicException Always.
     */
    public function fromGregorian(CivilDate $date): HijriDate
    {
        throw new \LogicException('UmmAlQuraCalendar::fromGregorian lands in M4 Task 4.');
    }

    /**
     * Not implemented until the table slice (M4 Task 4).
     *
     * @param HijriDate $date Hijri date.
     * @return CivilDate Never returns.
     * @throws \LogicException Always.
     */
    public function toGregorian(HijriDate $date): CivilDate
    {
        throw new \LogicException('UmmAlQuraCalendar::toGregorian lands in M4 Task 4.');
    }

    /**
     * Not implemented until the table slice (M4 Task 4).
     *
     * @param int $year Hijri year.
     * @param int $month Hijri month 1–12.
     * @return int Never returns.
     * @throws \LogicException Always.
     */
    public function monthLength(int $year, int $month): int
    {
        throw new \LogicException('UmmAlQuraCalendar::monthLength lands in M4 Task 4.');
    }

    /**
     * Stable calendar key.
     *
     * @return string Always 'uqu' (the pin's key).
     */
    public function name(): string
    {
        return 'uqu';
    }
}
