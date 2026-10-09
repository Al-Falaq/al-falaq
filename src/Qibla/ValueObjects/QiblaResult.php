<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Qibla\ValueObjects;

use AlFalaq\AlFalak\SharedKernel\Exceptions\ValidationException;

/**
 * Qibla answer: initial bearing + great-circle/ellipsoid distance.
 *
 * Makkah-self (distance < 1 m) yields direction 0.0; never throws for the
 * degenerate two (self, antipode).
 */
final readonly class QiblaResult
{
    /**
     * Validated bearing + distance pair.
     *
     * @param float $direction Initial bearing [0, 360); 0.0 at Makkah-self.
     * @param float $distanceKm Distance ≥ 0.
     */
    public function __construct(
        public float $direction,
        public float $distanceKm,
    ) {
        if (! is_finite($direction) || $direction < 0.0 || $direction >= 360.0) {
            if (! ($distanceKm === 0.0 && $direction === 0.0)) {
                throw new ValidationException('Qibla direction must be in [0, 360).');
            }
        }

        if (! is_finite($distanceKm) || $distanceKm < 0.0) {
            throw new ValidationException('Qibla distance must be finite and >= 0.');
        }
    }

    /**
     * Compass heading given caller-supplied magnetic declination.
     *
     * @param mixed $declinationEast East-positive declination in degrees.
     * @return float Heading unwound to [0, 360).
     */
    public function magneticHeading(mixed $declinationEast): float
    {
        if (is_bool($declinationEast) || ! is_int($declinationEast) && ! is_float($declinationEast)) {
            throw new ValidationException('Declination must be a finite float.');
        }

        $decl = (float) $declinationEast;

        if (! is_finite($decl)) {
            throw new ValidationException('Declination must be finite.');
        }

        $heading = fmod($this->direction - fmod($decl, 360.0), 360.0);

        return $heading < 0.0 ? $heading + 360.0 : $heading;
    }
}
