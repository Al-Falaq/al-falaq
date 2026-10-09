<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Prayer\Services;

use AlFalaq\AlFalak\Prayer\Contracts\TimetableServiceInterface;
use AlFalaq\AlFalak\Prayer\ValueObjects\CalculationParameters;
use AlFalaq\AlFalak\Prayer\ValueObjects\PrayerSchedule;
use AlFalaq\AlFalak\SharedKernel\ValueObjects\Coordinates;

/**
 * Monthly timetable orchestration over the single-day calculator (M1).
 */
final class TimetableService implements TimetableServiceInterface
{
    /**
     * Compute one schedule per day of the month (UTC, constant memory).
     *
     * @param Coordinates $coordinates Observer location.
     * @param int $year Gregorian year.
     * @param int $month 1–12.
     * @param CalculationParameters $parameters Method defaults + overrides.
     * @return list<PrayerSchedule> One entry per calendar day, in order.
     */
    public function monthly(Coordinates $coordinates, int $year, int $month, CalculationParameters $parameters): array
    {
        throw new \LogicException('not implemented: M1 timetable pending');
    }
}
