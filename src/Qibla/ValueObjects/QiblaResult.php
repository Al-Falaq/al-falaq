<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Qibla\ValueObjects;

use AlFalaq\AlFalak\SharedKernel\Exceptions\ValidationException;

final readonly class QiblaResult
{
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
