<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Qibla\Strategies;

use AlFalaq\AlFalaq\Astro\Services\GeodesyEngine;
use AlFalaq\AlFalaq\Qibla\Contracts\GeodesyCalculator;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

/**
 * Spherical-earth geodesy: Todhunter initial bearing + haversine (default, M2).
 */
final class SphericalCalculator implements GeodesyCalculator
{
    /**
     * Bind the raw-math engine (defaults to a fresh instance).
     *
     * @param GeodesyEngine $engine Raw Todhunter/haversine engine.
     */
    public function __construct(private readonly GeodesyEngine $engine = new GeodesyEngine()) {}

    /**
     * Solve the inverse on a sphere (M2).
     *
     * Degenerate Makkah-self (distance < 1 m) clamps to direction 0.0 and
     * distance 0.0 per §5.3; the raw engine bearing there is
     * construction-defined and never leaks out.
     *
     * @param Coordinates $from Observer location.
     * @return array{direction: float, distanceKm: float} Bearing + distance.
     */
    public function inverse(Coordinates $from): array
    {
        $raw = $this->engine->inverse(
            $from->latitude,
            $from->longitude,
            GeodesyEngine::MAKKAH_LATITUDE,
            GeodesyEngine::MAKKAH_LONGITUDE,
        );

        if ($raw['distanceKm'] < 0.001) {
            return ['direction' => 0.0, 'distanceKm' => 0.0];
        }

        return $raw;
    }
}
