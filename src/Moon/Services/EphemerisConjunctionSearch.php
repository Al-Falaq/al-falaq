<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Moon\Services;

use AlFalaq\AlFalaq\Astro\Services\CrescentEphemeris;
use AlFalaq\AlFalaq\Astro\Traits\SharesSolarTheory;
use AlFalaq\AlFalaq\Moon\Contracts\ConjunctionSearch;

/**
 * Ephemeris-backed conjunction math: age search + great-circle separation.
 *
 * Single owner for the pin _moon_age_days search and the separation the
 * sunset assembly shares with it. Lives in Services (not Strategies) so it
 * may import the Astro engine; Moon Strategies stay engine-free.
 */
final class EphemerisConjunctionSearch implements ConjunctionSearch
{
    use SharesSolarTheory;

    /**
     * Moon-age backward search window in days (pin CrescentGeometry.py:38).
     */
    private const MOON_AGE_SEARCH_DAYS = 30.0;

    /**
     * Moon-age reporting floor in days (pin CrescentGeometry.py:39).
     */
    private const MOON_AGE_MIN_DAYS = 0.1;

    /**
     * Moon-age search step in days: hourly (pin CrescentGeometry.py:40).
     */
    private const MOON_AGE_STEP_DAYS = 1.0 / 24.0;

    /**
     * Wire the sun/moon ephemeris explicitly (no container).
     *
     * @param CrescentEphemeris $ephemeris Sun/moon ephemeris + sidereal time.
     */
    public function __construct(private readonly CrescentEphemeris $ephemeris) {}

    /**
     * Recommended wiring: the Meeus ephemeris.
     *
     * @return self Ready search covering the moon-age path.
     */
    public static function default(): self
    {
        return new self(new CrescentEphemeris());
    }

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
    public function ageDays(float $julianDayTt): float
    {
        $offsets = [];
        $offset = 0.0;

        while ($offset <= self::MOON_AGE_SEARCH_DAYS + 1e-12) {
            $offsets[] = $offset;
            $offset += self::MOON_AGE_STEP_DAYS;
        }

        $arcls = [];

        foreach ($offsets as $back) {
            $arcls[] = $this->arclAt($julianDayTt - $back);
        }

        if ($arcls[0] <= $arcls[1] && $this->arclAt($julianDayTt + self::MOON_AGE_STEP_DAYS) >= $arcls[0]) {
            return self::MOON_AGE_MIN_DAYS;
        }

        $count = count($arcls);

        for ($i = 1; $i < $count - 1; $i++) {
            if ($arcls[$i] <= $arcls[$i - 1] && $arcls[$i] <= $arcls[$i + 1]) {
                return max(self::MOON_AGE_MIN_DAYS, $offsets[$i]);
            }
        }

        return self::MOON_AGE_SEARCH_DAYS;
    }

    /**
     * Great-circle separation of two equatorial positions (pin verbatim).
     *
     * @param float $ra1Deg First right ascension in degrees.
     * @param float $dec1Deg First declination in degrees.
     * @param float $ra2Deg Second right ascension in degrees.
     * @param float $dec2Deg Second declination in degrees.
     * @return float Separation in degrees.
     */
    public function separation(float $ra1Deg, float $dec1Deg, float $ra2Deg, float $dec2Deg): float
    {
        $cosSep = sin(deg2rad($dec1Deg)) * sin(deg2rad($dec2Deg))
            + cos(deg2rad($dec1Deg)) * cos(deg2rad($dec2Deg)) * cos(deg2rad($ra2Deg - $ra1Deg));

        return rad2deg(acos($this->clamp($cosSep)));
    }

    /**
     * Geocentric elongation (ARCL) at one TT instant.
     *
     * @param float $julianDayTt Terrestrial-time Julian day.
     * @return float Sun–moon separation in degrees.
     */
    private function arclAt(float $julianDayTt): float
    {
        $sun = $this->ephemeris->solarGeocentric($julianDayTt);
        $moon = $this->ephemeris->lunarGeocentric($julianDayTt);

        return $this->separation($sun['ra'], $sun['dec'], $moon['ra'], $moon['dec']);
    }
}
