<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Qibla\Strategies;

use AlFalaq\AlFalaq\Astro\Services\GeodesyEngine;
use AlFalaq\AlFalaq\Qibla\Contracts\GeodesyCalculator;
use AlFalaq\AlFalaq\Qibla\Traits\ClampsMakkahSelf;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

/**
 * Spherical-earth geodesy: Todhunter initial bearing + haversine (default, M2).
 */
final class SphericalCalculator implements GeodesyCalculator
{
    use ClampsMakkahSelf;
    /**
     * Bind the raw-math engine (defaults to a fresh instance).
     *
     * @param GeodesyEngine $engine Raw Todhunter/haversine engine.
     */
    public function __construct(private readonly GeodesyEngine $engine = new GeodesyEngine()) {}

    /**
     * Solve the inverse on a sphere (M2).
     *
     * The degenerate Makkah-self clamp lives once in ClampsMakkahSelf; the
     * raw engine bearing there is construction-defined and never leaks out.
     *
     * @param Coordinates $from Observer location.
     * @return array{direction: float, distanceKm: float} Bearing + distance.
     */
    public function inverse(Coordinates $from): array
    {
        $raw = $this->engine->inverse(
            $from->latitude,
            $from->longitude,
            GeodesyCalculator::MAKKAH_LATITUDE,
            GeodesyCalculator::MAKKAH_LONGITUDE,
        );

        return self::clampMakkahSelf($raw);
    }
}
