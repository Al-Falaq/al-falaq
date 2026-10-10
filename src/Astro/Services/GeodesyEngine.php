<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Astro\Services;

use AlFalaq\AlFalaq\SharedKernel\Exceptions\AstronomicalException;

/**
 * Low-level geodesic engine shared by Qibla strategies (M2).
 *
 * @internal Strategies own the models; this owns the raw math.
 */
final class GeodesyEngine
{
    /**
     * Kaaba latitude in degrees (pin Constants.py:8; goldens pin these digits).
     */
    public const MAKKAH_LATITUDE = 21.4225241;

    /**
     * Kaaba longitude in degrees (pin Constants.py:8; goldens pin these digits).
     */
    public const MAKKAH_LONGITUDE = 39.8261818;

    /**
     * IUGG mean radius R1 = (2a+b)/3 in km (pin Constants.py:12).
     */
    public const MEAN_RADIUS_KM = 6371.0088;

    /**
     * Raw inverse between two points (M2).
     *
     * Todhunter p.50 initial bearing + haversine atan2 distance; haversine
     * term clamped to [0,1] per determinism rule; bearing unwound to [0,360).
     *
     * @param float $lat1 From-latitude in degrees.
     * @param float $lon1 From-longitude in degrees.
     * @param float $lat2 To-latitude in degrees.
     * @param float $lon2 To-longitude in degrees.
     * @return array{direction: float, distanceKm: float} Bearing + distance.
     */
    public function inverse(float $lat1, float $lon1, float $lat2, float $lon2): array
    {
        $fromLat = deg2rad($lat1);
        $toLat = deg2rad($lat2);
        $deltaLon = deg2rad($lon2 - $lon1);

        $bearing = rad2deg(atan2(
            sin($deltaLon),
            cos($fromLat) * tan($toLat) - sin($fromLat) * cos($deltaLon),
        ));
        $bearing = fmod($bearing, 360.0);

        if ($bearing < 0.0) {
            $bearing += 360.0;
        }

        $deltaLat = deg2rad($lat2 - $lat1);
        $haversine = sin($deltaLat / 2.0) ** 2
            + cos($fromLat) * cos($toLat) * sin($deltaLon / 2.0) ** 2;
        $haversine = min(1.0, max(0.0, $haversine));
        $distanceKm = self::MEAN_RADIUS_KM * 2.0 * atan2(sqrt($haversine), sqrt(1.0 - $haversine));

        if (! is_finite($bearing) || ! is_finite($distanceKm)) {
            throw new AstronomicalException('GeodesyEngine::inverse produced a non-finite result.');
        }

        return ['direction' => $bearing, 'distanceKm' => $distanceKm];
    }
}
