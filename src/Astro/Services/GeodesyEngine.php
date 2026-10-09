<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Astro\Services;

/**
 * Low-level geodesic engine shared by Qibla strategies (M2).
 *
 * @internal Strategies own the models; this owns the raw math.
 */
final class GeodesyEngine
{
    /**
     * Raw inverse between two points (M2).
     *
     * @param float $lat1 From-latitude in degrees.
     * @param float $lon1 From-longitude in degrees.
     * @param float $lat2 To-latitude in degrees.
     * @param float $lon2 To-longitude in degrees.
     * @return array{direction: float, distanceKm: float} Bearing + distance.
     */
    public function inverse(float $lat1, float $lon1, float $lat2, float $lon2): array
    {
        throw new \LogicException('not implemented: M2 geodesy pending');
    }
}
