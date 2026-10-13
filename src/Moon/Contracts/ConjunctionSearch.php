<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Moon\Contracts;

/**
 * Conjunction geometry math over the ephemeris (driven port, one module).
 *
 * Age search plus the great-circle separation it shares with sunset
 * assembly: two methods, one coherent job, one caller. Tests substitute
 * canned geometry math through this seam; production uses the
 * ephemeris-backed adapter in Moon\Services (which alone may import Astro).
 */
interface ConjunctionSearch
{
    /**
     * Elapsed days since the previous new moon (pin _moon_age_days verbatim).
     *
     * Youngest hourly local minimum of geocentric ARCL over the prior
     * month: sampling starts at sunset itself so a conjunction hours
     * before sunset is still caught; a sunset sample at/below its
     * neighbours plus a non-shrinking forward sample reports the floor
     * (waxing), otherwise the search falls through to the older minimum.
     *
     * @param float $julianDayTt TT Julian day of sunset.
     * @return float Age in days.
     */
    public function ageDays(float $julianDayTt): float;

    /**
     * Great-circle separation of two equatorial positions (pin verbatim).
     *
     * @param float $ra1Deg First right ascension in degrees.
     * @param float $dec1Deg First declination in degrees.
     * @param float $ra2Deg Second right ascension in degrees.
     * @param float $dec2Deg Second declination in degrees.
     * @return float Separation in degrees.
     */
    public function separation(float $ra1Deg, float $dec1Deg, float $ra2Deg, float $dec2Deg): float;
}
