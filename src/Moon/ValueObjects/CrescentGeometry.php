<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Moon\ValueObjects;

use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;

/**
 * Crescent geometry at local sunset: everything evaluators need, nothing else.
 */
final readonly class CrescentGeometry
{
    /**
     * Sunset geometry bundle (spec §5.1 field contract).
     *
     * @param float $sunAltitude Geocentric sun altitude in degrees (the pin applies parallax to the moon only).
     * @param float $moonAltitude Topocentric moon altitude in degrees.
     * @param float $elongationDeg Sun–moon elongation (ARCL) in degrees.
     * @param float $moonAgeHours Hours since conjunction at sunset.
     * @param float|null $lagMinutes Moonset minus sunset; null = no-moonset window.
     * @param float $deltaAzimuthDeg Azimuth difference (DAZ) in degrees.
     * @param float $illuminatedFraction (1 − cos ARCL)/2; expected 0–1, stored raw.
     * @param float $arcvGeoDeg Geocentric ARCV in degrees (geocentric moon minus geocentric sun).
     * @param float $widthArcmin Raw width in arcmin (stored; clamped only in evaluators).
     * @param float|null $ageAtMoonsetHours Age at moonset; null iff lagMinutes null.
     */
    public function __construct(
        public float $sunAltitude,
        public float $moonAltitude,
        public float $elongationDeg,
        public float $moonAgeHours,
        public ?float $lagMinutes,
        public float $deltaAzimuthDeg,
        public float $illuminatedFraction,
        public float $arcvGeoDeg,
        public float $widthArcmin,
        public ?float $ageAtMoonsetHours,
    ) {
        foreach ([
            'sunAltitude' => $sunAltitude,
            'moonAltitude' => $moonAltitude,
            'elongationDeg' => $elongationDeg,
            'moonAgeHours' => $moonAgeHours,
            'deltaAzimuthDeg' => $deltaAzimuthDeg,
            'illuminatedFraction' => $illuminatedFraction,
            'arcvGeoDeg' => $arcvGeoDeg,
            'widthArcmin' => $widthArcmin,
        ] as $name => $value) {
            if (! is_finite($value)) {
                throw new ValidationException(sprintf('CrescentGeometry::%s must be finite.', $name));
            }
        }

        foreach (['lagMinutes' => $lagMinutes, 'ageAtMoonsetHours' => $ageAtMoonsetHours] as $name => $value) {
            if ($value !== null && ! is_finite($value)) {
                throw new ValidationException(sprintf('CrescentGeometry::%s must be finite or null.', $name));
            }
        }

        if (($lagMinutes === null) !== ($ageAtMoonsetHours === null)) {
            throw new ValidationException('CrescentGeometry: lagMinutes and ageAtMoonsetHours must both be null (no-moonset window) or both finite.');
        }
    }
}
