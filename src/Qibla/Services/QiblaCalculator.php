<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Qibla\Services;

use AlFalaq\AlFalaq\Qibla\Contracts\GeodesyCalculator;
use AlFalaq\AlFalaq\Qibla\Strategies\SphericalCalculator;
use AlFalaq\AlFalaq\Qibla\Strategies\Wgs84KarneyCalculator;
use AlFalaq\AlFalaq\Qibla\ValueObjects\QiblaResult;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

/**
 * Qibla entry point; geodesy injected, default = spherical (M2).
 */
final class QiblaCalculator
{
    /**
     * Inject the geodesic algorithm (factories are the easy path).
     *
     * @param GeodesyCalculator $geodesy Algorithm; see default()/wgs84().
     */
    public function __construct(private readonly GeodesyCalculator $geodesy) {}

    /**
     * Spherical-earth calculator (fast default, Todhunter/haversine).
     *
     * @return self Wired with SphericalCalculator.
     */
    public static function default(): self
    {
        return new self(new SphericalCalculator());
    }

    /**
     * WGS84/Karney calculator (precise opt-in, M2 spike).
     *
     * @return self Wired with Wgs84KarneyCalculator.
     */
    public static function wgs84(): self
    {
        return new self(new Wgs84KarneyCalculator());
    }

    /**
     * Expose the injected geodesy (introspection/testing).
     *
     * @return GeodesyCalculator The wired algorithm.
     */
    public function geodesy(): GeodesyCalculator
    {
        return $this->geodesy;
    }

    /**
     * Compute Qibla direction + distance for one location (M2).
     *
     * @param Coordinates|array{0: mixed, 1: mixed} $coordinates VO or [lat, lon] pair.
     * @return QiblaResult Bearing [0,360) and distance in km.
     */
    public function calculate(Coordinates|array $coordinates): QiblaResult
    {
        $observer = $coordinates instanceof Coordinates ? $coordinates : Coordinates::fromArray($coordinates);

        $solved = $this->geodesy->inverse($observer);

        return new QiblaResult($solved['direction'], $solved['distanceKm']);
    }
}
