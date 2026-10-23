<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Qibla\Contracts;

use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Kaaba;

/**
 * Geodesic inverse from an observer toward Makkah (driven port).
 *
 * Spherical (fast default) vs WGS84/Karney (precise opt-in) behind one shape.
 * Qibla domain facts live here (Kaaba + self threshold), never in Astro:
 * the engine takes arbitrary point pairs and owns only raw math.
 */
interface GeodesyCalculator
{
    /**
     * Kaaba latitude in degrees (pin Constants.py:8; goldens pin these digits).
     */
    public const MAKKAH_LATITUDE = Kaaba::LATITUDE;

    /**
     * Kaaba longitude in degrees (pin Constants.py:8; goldens pin these digits).
     */
    public const MAKKAH_LONGITUDE = Kaaba::LONGITUDE;

    /**
     * Makkah-self distance in km below which direction clamps to 0.0 (§5.3).
     */
    public const SELF_DISTANCE_KM = 0.001;

    /**
     * Solve the inverse problem for one observer location.
     *
     * @param Coordinates $from Observer location.
     * @return array{direction: float, distanceKm: float} Initial bearing [0,360) + distance.
     */
    public function inverse(Coordinates $from): array;
}
