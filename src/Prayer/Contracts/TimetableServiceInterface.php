<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Prayer\Contracts;

use AlFalaq\AlFalaq\Prayer\ValueObjects\CalculationParameters;
use AlFalaq\AlFalaq\Prayer\ValueObjects\PrayerSchedule;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

/**
 * Monthly timetable orchestration over the single-day calculator (M1).
 */
interface TimetableServiceInterface
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
    public function monthly(Coordinates $coordinates, int $year, int $month, CalculationParameters $parameters): array;
}
