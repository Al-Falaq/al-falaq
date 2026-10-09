<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Qibla\Strategies;

use AlFalaq\AlFalak\Qibla\Contracts\GeodesyCalculator;
use AlFalaq\AlFalak\SharedKernel\ValueObjects\Coordinates;

/**
 * WGS84-ellipsoid geodesy via Karney (2013) inverse (precise opt-in, M2).
 *
 * M2 spike decides Karney vs Vincenty fallback behind this same interface.
 */
final class Wgs84KarneyCalculator implements GeodesyCalculator
{
    /**
     * WGS84 semi-major axis in km (pinned value, Appendix B).
     */
    public const SEMI_MAJOR_KM = 6378.137;

    /**
     * WGS84 flattening 1/298.257223563 (pinned value, Appendix B).
     */
    public const FLATTENING = 1.0 / 298.257223563;

    /**
     * Solve the inverse on the ellipsoid (M2).
     *
     * @param Coordinates $from Observer location.
     * @return array{direction: float, distanceKm: float} Bearing + distance.
     */
    public function inverse(Coordinates $from): array
    {
        throw new \LogicException('not implemented: M2 Karney spike pending (fallback Vincenty allowed)');
    }
}
