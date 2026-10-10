<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Astro\Contracts;

/**
 * Solar model port: declination + equation of time per Julian Day.
 *
 * @internal Userland must use Moon/ or Prayer/ facades, never this.
 */
interface SolarModel
{
    /**
     * Solar declination in degrees (Meeus, M1).
     *
     * @param float $julianDay Julian Day number.
     * @return float Declination in degrees.
     */
    public function declination(float $julianDay): float;

    /**
     * Equation of time in minutes (Meeus, M1).
     *
     * @param float $julianDay Julian Day number.
     * @return float Minutes (apparent minus mean solar time).
     */
    public function equationOfTime(float $julianDay): float;
}
