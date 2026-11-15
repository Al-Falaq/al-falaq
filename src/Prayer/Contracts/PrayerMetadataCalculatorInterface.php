<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Prayer\Contracts;

use AlFalaq\AlFalaq\Prayer\ValueObjects\CalculationParameters;
use AlFalaq\AlFalaq\Prayer\ValueObjects\PrayerCalculationOutcome;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

/**
 * Optional calculator capability: return the schedule plus its provenance.
 *
 * Extends the legacy driving port so a metadata-capable calculator remains a
 * `PrayerTimesCalculatorInterface`. `PrayerTimesCalculatorInterface` itself is
 * unchanged; callers opt in by type-checking this interface.
 */
interface PrayerMetadataCalculatorInterface extends PrayerTimesCalculatorInterface
{
    /**
     * Compute the schedule and the metadata that explains how it was produced.
     *
     * Implementations widen `$coordinates` to `Coordinates|array` by parameter
     * contravariance, exactly like `calculate()`.
     *
     * @param Coordinates $coordinates Observer location.
     * @param CivilDate $date Civil date to compute.
     * @param CalculationParameters $parameters Method defaults + overrides.
     * @return PrayerCalculationOutcome Schedule plus provenance.
     */
    public function calculateWithMetadata(
        Coordinates $coordinates,
        CivilDate $date,
        CalculationParameters $parameters,
    ): PrayerCalculationOutcome;
}
