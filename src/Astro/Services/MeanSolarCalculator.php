<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Astro\Services;

use AlFalaq\AlFalak\Astro\Contracts\SolarModel;
use AlFalaq\AlFalak\Astro\ValueObjects\SolarPosition;

/**
 * Mean-sun solar calculator (Meeus transcription, M1).
 *
 * @internal Entry point is SolarTime, not this class.
 */
final class MeanSolarCalculator implements SolarModel
{
    /**
     * Solar declination in degrees (M1).
     *
     * @param float $julianDay Julian Day number.
     * @return float Declination in degrees.
     */
    public function declination(float $julianDay): float
    {
        throw new \LogicException('not implemented: M1 solar pending Meeus transcription');
    }

    /**
     * Equation of time in minutes (M1).
     *
     * @param float $julianDay Julian Day number.
     * @return float Minutes (apparent minus mean solar time).
     */
    public function equationOfTime(float $julianDay): float
    {
        throw new \LogicException('not implemented: M1 solar pending');
    }

    /**
     * Full solar position bundle for one day number (M1).
     *
     * @param float $julianDay Julian Day number.
     * @return SolarPosition Declination + equation of time.
     */
    public function position(float $julianDay): SolarPosition
    {
        throw new \LogicException('not implemented: M1 solar pending');
    }
}
