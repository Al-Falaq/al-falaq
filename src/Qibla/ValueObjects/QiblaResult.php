<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Qibla\ValueObjects;

use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Angle;

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
     * Angle::normalized() is the single unwind owner (incl. the 360.0 ulp
     * re-clamp, which applies here exactly as for bearings).
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

        return Angle::fromDegrees($this->direction - fmod($decl, 360.0))->normalized()->degrees;
    }
}
