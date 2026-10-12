<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Prayer\Contracts;

use AlFalaq\AlFalaq\Prayer\ValueObjects\CalculationParameters;
use AlFalaq\AlFalaq\Prayer\ValueObjects\PrayerSchedule;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

/**
 * Single-day prayer-time pipeline (M1 freeze).
 *
 * Implemented once by Services\PrayerTimesCalculator; callers program to
 * this contract for testability.
 */
interface PrayerTimesCalculatorInterface
{
    /**
     * Compute all ten prayer instants for one date and place (UTC).
     *
     * @param Coordinates $coordinates Observer location.
     * @param CivilDate $date Civil date to compute.
     * @param CalculationParameters $parameters Method defaults + overrides.
     * @return PrayerSchedule All instants in UTC; display via forZone().
     */
    public function calculate(Coordinates $coordinates, CivilDate $date, CalculationParameters $parameters): PrayerSchedule;
}
