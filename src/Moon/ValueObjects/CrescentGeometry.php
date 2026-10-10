<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Moon\ValueObjects;

/**
 * Crescent geometry at local sunset: everything evaluators need, nothing else.
 */
final readonly class CrescentGeometry
{
    /**
     * Sunset geometry bundle (seven Appendix B inputs).
     *
     * @param float $sunAltitude Sun altitude in degrees.
     * @param float $moonAltitude Moon altitude in degrees.
     * @param float $elongationDeg Sun–moon elongation (ARCV input) in degrees.
     * @param float $moonAgeHours Hours since conjunction.
     * @param float $lagMinutes Moonset minus sunset.
     * @param float $deltaAzimuthDeg Azimuth difference (DAZ input) in degrees.
     * @param float $illuminatedFraction Moon width W input, 0–1.
     */
    public function __construct(
        public float $sunAltitude,
        public float $moonAltitude,
        public float $elongationDeg,
        public float $moonAgeHours,
        public float $lagMinutes,
        public float $deltaAzimuthDeg,
        public float $illuminatedFraction,
    ) {}
}
