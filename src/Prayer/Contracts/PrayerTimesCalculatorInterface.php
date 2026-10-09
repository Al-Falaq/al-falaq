<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Prayer\Contracts;

use AlFalaq\AlFalak\Prayer\ValueObjects\CalculationParameters;
use AlFalaq\AlFalak\Prayer\ValueObjects\PrayerSchedule;
use AlFalaq\AlFalak\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalak\SharedKernel\ValueObjects\Coordinates;

/**
 * Single-day prayer-time pipeline (M1; M0 throws — see Appendix A freeze).
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
