<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Astro\ValueObjects;

/**
 * Solar position bundle feeding SolarTime (transit/sunrise/sunset).
 *
 * @internal Built by Astro internals from declination + equation of time.
 */
final readonly class SolarPosition
{
    /**
     * Internal solar bundle.
     *
     * @param float $declinationDeg Solar declination in degrees.
     * @param float $equationOfTimeMinutes Minutes (apparent minus mean).
     */
    public function __construct(
        public float $declinationDeg,
        public float $equationOfTimeMinutes,
    ) {}
}
