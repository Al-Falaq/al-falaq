<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Qibla\Contracts;

use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

/**
 * Geodesic inverse from an observer toward Makkah (driven port).
 *
 * Spherical (fast default) vs WGS84/Karney (precise opt-in) behind one shape.
 */
interface GeodesyCalculator
{
    /**
     * Solve the inverse problem for one observer location.
     *
     * @param Coordinates $from Observer location.
     * @return array{direction: float, distanceKm: float} Initial bearing [0,360) + distance.
     */
    public function inverse(Coordinates $from): array;
}
