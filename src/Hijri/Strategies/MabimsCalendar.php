<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Hijri\Strategies;

use AlFalaq\AlFalaq\Hijri\Contracts\HijriCalendar;
use AlFalaq\AlFalaq\Hijri\Enums\MabimsCountry;
use AlFalaq\AlFalaq\Hijri\Traits\ShiftsHijriDays;
use AlFalaq\AlFalaq\Hijri\ValueObjects\HijriDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;

/**
 * Neo-MABIMS (MY/ID/SG/BN) calendar. Table slice lands in M4 Task 5.
 */
final class MabimsCalendar implements HijriCalendar
{
    use ShiftsHijriDays;

    /**
     * Bind the member state whose table this instance resolves.
     *
     * @param MabimsCountry $country Supported member state.
     */
    public function __construct(public readonly MabimsCountry $country) {}

    /**
     * Not implemented until the table slice (M4 Task 5).
     *
     * @param CivilDate $date Gregorian day.
     * @return HijriDate Never returns.
     * @throws \LogicException Always.
     */
    public function fromGregorian(CivilDate $date): HijriDate
    {
        throw new \LogicException('MabimsCalendar::fromGregorian lands in M4 Task 5.');
    }

    /**
     * Not implemented until the table slice (M4 Task 5).
     *
     * @param HijriDate $date Hijri date.
     * @return CivilDate Never returns.
     * @throws \LogicException Always.
     */
    public function toGregorian(HijriDate $date): CivilDate
    {
        throw new \LogicException('MabimsCalendar::toGregorian lands in M4 Task 5.');
    }

    /**
     * Not implemented until the table slice (M4 Task 5).
     *
     * @param int $year Hijri year.
     * @param int $month Hijri month 1–12.
     * @return int Never returns.
     * @throws \LogicException Always.
     */
    public function monthLength(int $year, int $month): int
    {
        throw new \LogicException('MabimsCalendar::monthLength lands in M4 Task 5.');
    }

    /**
     * Stable calendar key.
     *
     * @return string Always 'mabims' (the pin's key).
     */
    public function name(): string
    {
        return 'mabims';
    }
}
