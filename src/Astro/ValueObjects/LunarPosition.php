<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Astro\ValueObjects;

/**
 * Lunar position bundle feeding crescent geometry (M3).
 *
 * @internal Built by Astro internals; userland reads CrescentGeometry.
 */
final readonly class LunarPosition
{
    /**
     * Internal lunar bundle.
     *
     * @param float $altitudeDeg Topocentric altitude in degrees.
     * @param float $azimuthDeg Topocentric azimuth in degrees.
     * @param float $ageHours Hours since conjunction.
     */
    public function __construct(
        public float $altitudeDeg,
        public float $azimuthDeg,
        public float $ageHours,
    ) {}
}
