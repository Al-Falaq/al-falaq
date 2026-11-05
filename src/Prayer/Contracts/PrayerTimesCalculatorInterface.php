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
     * Implementations accept exactly Coordinates (parameter contravariance
     * lets the shipped calculator widen to Coordinates|array for callers).
     *
     * @param Coordinates $coordinates Observer location.
     * @param CivilDate $date Civil date to compute.
     * @param CalculationParameters $parameters Method defaults + overrides.
     * @return PrayerSchedule All instants in UTC; display via forZone().
     */
    public function calculate(Coordinates $coordinates, CivilDate $date, CalculationParameters $parameters): PrayerSchedule;

    /**
     * Maghrib instant without the full schedule (strict-config fallback).
     *
     * Runs the pipeline's own solar/maghrib computation and returns before
     * the Fajr/Isha correction phases, so strict high-latitude configs with
     * an undefined full schedule can still resolve maghrib when its own rule
     * is defined (sunset/interval, or a reachable solar angle). Throws
     * UndefinedScheduleException when the day has no sunset, or
     * AstronomicalException when an angle-based rule never reaches its angle.
     *
     * @param Coordinates $coordinates Observer location.
     * @param CivilDate $date Civil date to compute.
     * @param CalculationParameters $parameters Method defaults + overrides.
     * @return \DateTimeImmutable Maghrib instant (UTC).
     */
    public function maghribAt(Coordinates $coordinates, CivilDate $date, CalculationParameters $parameters): \DateTimeImmutable;
}
