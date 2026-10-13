<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Qibla\Strategies;

use AlFalaq\AlFalaq\Astro\Services\KarneyInverse;
use AlFalaq\AlFalaq\Qibla\Contracts\GeodesyCalculator;
use AlFalaq\AlFalaq\Qibla\Traits\ClampsMakkahSelf;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\AstronomicalException;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Angle;
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
    use ClampsMakkahSelf;
    /**
     * Bind the raw-math engine (defaults to a fresh instance).
     *
     * @param KarneyInverse $engine Raw Karney (2013) ellipsoidal engine.
     */
    public function __construct(private readonly KarneyInverse $engine = new KarneyInverse()) {}

    /**
     * Solve the inverse on the ellipsoid (M2).
     *
     * The engine returns the azimuth in (-180, 180]; Angle::normalized() is
     * the single unwind owner (incl. the 360.0 ulp re-clamp QiblaResult
     * rejects), and the degenerate Makkah-self clamp lives once in
     * ClampsMakkahSelf (§5.3).
     *
     * @param Coordinates $from Observer location.
     * @return array{direction: float, distanceKm: float} Bearing + distance.
     */
    public function inverse(Coordinates $from): array
    {
        $solved = $this->engine->inverse(
            $from->latitude,
            $from->longitude,
            GeodesyCalculator::MAKKAH_LATITUDE,
            GeodesyCalculator::MAKKAH_LONGITUDE,
        );

        $direction = Angle::fromDegrees($solved['direction'])->normalized()->degrees;

        if (! is_finite($solved['distanceKm'])) {
            throw new AstronomicalException('Wgs84KarneyCalculator produced a non-finite distance.');
        }

        return self::clampMakkahSelf(['direction' => $direction, 'distanceKm' => $solved['distanceKm']]);
    }
}
