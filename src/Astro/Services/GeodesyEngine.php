<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Astro\Services;

use AlFalaq\AlFalaq\SharedKernel\Exceptions\AstronomicalException;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Angle;

/**
 * Low-level geodesic engine shared by Qibla strategies (M2).
 *
 * @internal Strategies own the models; this owns the raw math.
 */
final class GeodesyEngine
{
    /**
     * IUGG mean radius R1 = (2a+b)/3 in km (pin Constants.py:12).
     */
    public const MEAN_RADIUS_KM = 6371.0088;

    /**
     * Raw inverse between two points (M2).
     *
     * Todhunter p.50 initial bearing + haversine atan2 distance; haversine
     * term clamped to [0,1] per determinism rule; bearing unwound to [0,360).
     * Inputs are validated at the engine boundary exactly like
     * KarneyInverse::inverse, so a direct call can never turn out-of-range
     * or non-finite coordinates into silent finite garbage.
     *
     * @param float $lat1 From-latitude in degrees, within [-90, 90].
     * @param float $lon1 From-longitude in degrees, within [-180, 180].
     * @param float $lat2 To-latitude in degrees, within [-90, 90].
     * @param float $lon2 To-longitude in degrees, within [-180, 180].
     * @return array{direction: float, distanceKm: float} Bearing + distance.
     */
    public function inverse(float $lat1, float $lon1, float $lat2, float $lon2): array
    {
        foreach (['lat1' => $lat1, 'lat2' => $lat2] as $name => $value) {
            if (! is_finite($value) || ! (-90.0 <= $value && $value <= 90.0)) {
                throw new ValidationException(sprintf(
                    'Latitude must be within [-90.0, 90.0], got %s (%s).',
                    var_export($value, true),
                    $name,
                ));
            }
        }

        foreach (['lon1' => $lon1, 'lon2' => $lon2] as $name => $value) {
            if (! is_finite($value) || ! (-180.0 <= $value && $value <= 180.0)) {
                throw new ValidationException(sprintf(
                    'Longitude must be within [-180.0, 180.0], got %s (%s).',
                    var_export($value, true),
                    $name,
                ));
            }
        }

        $fromLat = deg2rad($lat1);
        $toLat = deg2rad($lat2);
        $deltaLon = deg2rad($lon2 - $lon1);

        // Single normalization owner is Angle::normalized() (incl. the 360.0
        // ulp re-clamp that range checks reject).
        $bearing = Angle::fromDegrees(rad2deg(atan2(
            sin($deltaLon),
            cos($fromLat) * tan($toLat) - sin($fromLat) * cos($deltaLon),
        )))->normalized()->degrees;

        $deltaLat = deg2rad($lat2 - $lat1);
        $haversine = sin($deltaLat / 2.0) ** 2
            + cos($fromLat) * cos($toLat) * sin($deltaLon / 2.0) ** 2;
        $haversine = min(1.0, max(0.0, $haversine));
        $distanceKm = self::MEAN_RADIUS_KM * 2.0 * atan2(sqrt($haversine), sqrt(1.0 - $haversine));

        if (! is_finite($distanceKm)) {
            throw new AstronomicalException('GeodesyEngine::inverse produced a non-finite distance.');
        }

        return ['direction' => $bearing, 'distanceKm' => $distanceKm];
    }
}
