<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Astro\Services;

use AlFalaq\AlFalak\Astro\Contracts\SolarModel;
use AlFalaq\AlFalak\Astro\ValueObjects\SolarPosition;

/** @internal */
final class MeanSolarCalculator implements SolarModel
{
    public function declination(float $julianDay): float
    {
        throw new \LogicException('not implemented: M1 solar pending Meeus transcription');
    }

    public function equationOfTime(float $julianDay): float
    {
        throw new \LogicException('not implemented: M1 solar pending');
    }

    public function position(float $julianDay): SolarPosition
    {
        throw new \LogicException('not implemented: M1 solar pending');
    }
}
