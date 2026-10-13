<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Prayer\Strategies;

use AlFalaq\AlFalaq\Prayer\Contracts\HighLatitudeCorrection;
use AlFalaq\AlFalaq\Prayer\ValueObjects\NightPortions;

/**
 * Seventh-of-night high-latitude correction (M1).
 */
final class SeventhOfNightCorrection implements HighLatitudeCorrection
{
    /**
     * Split the night at its seventh (M1).
     *
     * @param float $fajrAngle Method fajr angle (for TWILIGHT_ANGLE rule).
     * @param float|null $ishaAngle Method isha angle, null when interval-based.
     * @return NightPortions Fajr and Isha night fractions.
     */
    public function portions(float $fajrAngle, ?float $ishaAngle): NightPortions
    {
        return new NightPortions(1.0 / 7.0, 1.0 / 7.0);
    }
}
