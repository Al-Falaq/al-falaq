<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Prayer\Contracts;

use AlFalaq\AlFalaq\Prayer\ValueObjects\NightPortions;

/**
 * High-latitude night splitting (Middle/Seventh of night, twilight angle).
 *
 * Used when twilight never reaches the method angles; returns Fajr/Isha
 * portions of the maghrib→sunrise night. The seam carries only the angles
 * adapters read — place/date were dropped (no adapter used them); re-widen
 * if a place-dependent correction appears.
 */
interface HighLatitudeCorrection
{
    /**
     * Split the night into Fajr and Isha portions.
     *
     * @param float $fajrAngle Method fajr angle (for TWILIGHT_ANGLE rule).
     * @param float|null $ishaAngle Method isha angle, null when interval-based.
     * @return NightPortions Fajr and Isha fractions.
     */
    public function portions(float $fajrAngle, ?float $ishaAngle): NightPortions;
}
