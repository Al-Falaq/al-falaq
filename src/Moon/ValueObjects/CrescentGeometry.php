<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Moon\ValueObjects;

use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;

/**
 * Crescent geometry at local sunset: everything evaluators need, nothing else.
 *
 * Invariants are rechecked on restoration: `unserialize()` bypasses the
 * constructor, so an old payload cannot smuggle a negative width past the
 * value object into the evaluators.
 */
final readonly class CrescentGeometry
{
    /**
     * Sunset geometry bundle: everything evaluators need, nothing else.
     *
     * @param float $sunAltitude Geocentric sun altitude in degrees (the pin applies parallax to the moon only).
     * @param float $moonAltitude Topocentric moon altitude in degrees.
     * @param float $elongationDeg Sun–moon elongation (ARCL) in degrees.
     * @param float $moonAgeHours Hours since conjunction at sunset.
     * @param float|null $lagMinutes Moonset minus sunset; null = no-moonset window.
     * @param float $deltaAzimuthDeg Azimuth difference (DAZ) in degrees.
     * @param float $illuminatedFraction (1 − cos ARCL)/2; expected 0–1, stored raw.
     * @param float $arcvGeoDeg Geocentric ARCV in degrees (geocentric moon minus geocentric sun).
     * @param float $widthArcmin Crescent width in arcmin; must be ≥ 0 (evaluators clamp the sub-epsilon floor).
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
        $this->assertValid();
    }

    /**
     * Revalidate a restored instance (unserialize bypasses the constructor).
     */
    public function __wakeup(): void
    {
        $this->assertValid();
    }

    /**
     * Enforce the bundle invariants on construction and restoration.
     */
    private function assertValid(): void
    {
        foreach ([
            'sunAltitude' => $this->sunAltitude,
            'moonAltitude' => $this->moonAltitude,
            'elongationDeg' => $this->elongationDeg,
            'moonAgeHours' => $this->moonAgeHours,
            'deltaAzimuthDeg' => $this->deltaAzimuthDeg,
            'illuminatedFraction' => $this->illuminatedFraction,
            'arcvGeoDeg' => $this->arcvGeoDeg,
            'widthArcmin' => $this->widthArcmin,
        ] as $name => $value) {
            if (! is_finite($value)) {
                throw new ValidationException(sprintf('CrescentGeometry::%s must be finite.', $name));
            }
        }

        foreach (['lagMinutes' => $this->lagMinutes, 'ageAtMoonsetHours' => $this->ageAtMoonsetHours] as $name => $value) {
            if ($value !== null && ! is_finite($value)) {
                throw new ValidationException(sprintf('CrescentGeometry::%s must be finite or null.', $name));
            }
        }

        if ($this->widthArcmin < 0.0) {
            throw new ValidationException('CrescentGeometry::widthArcmin must be >= 0.');
        }

        if (($this->lagMinutes === null) !== ($this->ageAtMoonsetHours === null)) {
            throw new ValidationException('CrescentGeometry: lagMinutes and ageAtMoonsetHours must both be null (no-moonset window) or both finite.');
        }
    }

    /**
     * Wire shape: spec §6.6 order, floats/null exactly as stored.
     *
     * @return array{
     *     sun_altitude_degrees: float,
     *     moon_altitude_degrees: float,
     *     elongation_degrees: float,
     *     moon_age_hours: float,
     *     lag_minutes: float|null,
     *     delta_azimuth_degrees: float,
     *     illuminated_fraction: float,
     *     arcv_geocentric_degrees: float,
     *     width_arcminutes: float,
     *     age_at_moonset_hours: float|null,
     * }
     */
    public function toArray(): array
    {
        return [
            'sun_altitude_degrees' => $this->sunAltitude,
            'moon_altitude_degrees' => $this->moonAltitude,
            'elongation_degrees' => $this->elongationDeg,
            'moon_age_hours' => $this->moonAgeHours,
            'lag_minutes' => $this->lagMinutes,
            'delta_azimuth_degrees' => $this->deltaAzimuthDeg,
            'illuminated_fraction' => $this->illuminatedFraction,
            'arcv_geocentric_degrees' => $this->arcvGeoDeg,
            'width_arcminutes' => $this->widthArcmin,
            'age_at_moonset_hours' => $this->ageAtMoonsetHours,
        ];
    }
}
