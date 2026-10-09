<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Qibla\Strategies;

use AlFalaq\AlFalak\Qibla\Contracts\GeodesyCalculator;
use AlFalaq\AlFalak\SharedKernel\ValueObjects\Coordinates;

/**
 * Spherical-earth geodesy: Todhunter initial bearing + haversine (default, M2).
 */
final class SphericalCalculator implements GeodesyCalculator
{
    /**
     * IUGG mean radius in km (pinned value, Appendix B).
     */
    public const MEAN_RADIUS_KM = 6371.0088;

    /**
     * Solve the inverse on a sphere (M2).
     *
     * @param Coordinates $from Observer location.
     * @return array{direction: float, distanceKm: float} Bearing + distance.
     */
    public function inverse(Coordinates $from): array
    {
        throw new \LogicException('not implemented: M2 qibla pending');
    }
}
