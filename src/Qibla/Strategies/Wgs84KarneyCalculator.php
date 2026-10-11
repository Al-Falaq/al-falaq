<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Qibla\Strategies;

use AlFalaq\AlFalaq\Astro\Services\GeodesyEngine;
use AlFalaq\AlFalaq\Astro\Services\KarneyInverse;
use AlFalaq\AlFalaq\Qibla\Contracts\GeodesyCalculator;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\AstronomicalException;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

/**
 * WGS84-ellipsoid geodesy via Karney (2013) inverse (precise opt-in, M2).
 *
 * The Karney engine (vendored GeographicLib 2.1 subset, Karney 2013
 * "Algorithms for geodesics", https://doi.org/10.1007/s00190-012-0578-z)
 * owns the WGS84 defining constants and the raw series; this strategy owns
 * only the public contract: unwinding the engine's (-180, 180] azimuth into
 * [0, 360) and the degenerate Makkah-self clamp.
 */
final class Wgs84KarneyCalculator implements GeodesyCalculator
{
    /**
     * Bind the raw-math engine (defaults to a fresh instance).
     *
     * @param KarneyInverse $engine Raw Karney (2013) ellipsoidal engine.
     */
    public function __construct(private readonly KarneyInverse $engine = new KarneyInverse()) {}

    /**
     * Solve the inverse on the ellipsoid (M2).
     *
     * The engine returns the azimuth unwound to (-180, 180]; it is wound
     * here into [0, 360). The re-clamp to 0.0 after the unwind is not
     * cosmetic: ``fmod(-1e-15, 360.0) + 360.0`` rounds to exactly 360.0
     * (ulp(360) ≈ 5.7e-14), which QiblaResult rejects.
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
        $solved = $this->engine->inverse(
            $from->latitude,
            $from->longitude,
            GeodesyEngine::MAKKAH_LATITUDE,
            GeodesyEngine::MAKKAH_LONGITUDE,
        );

        $direction = fmod($solved['direction'], 360.0);

        if ($direction < 0.0) {
            $direction += 360.0;
        }

        if ($direction >= 360.0) {
            $direction = 0.0;
        }

        if ($solved['distanceKm'] < 0.001) {
            return ['direction' => 0.0, 'distanceKm' => 0.0];
        }

        if (! is_finite($direction) || ! is_finite($solved['distanceKm'])) {
            throw new AstronomicalException('Wgs84KarneyCalculator produced a non-finite result.');
        }

        return ['direction' => $direction, 'distanceKm' => $solved['distanceKm']];
    }
}
