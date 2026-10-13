<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Astro\Traits;

/**
 * Shared Meeus solar theory for the Astro engines (single owner).
 *
 * Stateless low-precision solar theory (2nd ed. Ch.22/25 pp. 143-147/163-165
 * plus Ch.12 sidereal time and the Ch.15/p.24 interpolation helpers) used by
 * both MeanSolarCalculator (day-level solar times) and CrescentEphemeris
 * (sun/moon positions, sidereal time, moonset), plus the trig-overshoot clamp
 * and the rise/set depression shared with the Moon composition root. Bodies
 * are pin-verbatim (a7baa8b); engines keep only their distinct behavior.
 */
trait SharesSolarTheory
{
    /**
     * Standard rise/set depression in degrees (pin CrescentGeometry.py:36).
     */
    public const SUNSET_DEPRESSION_DEG = -50.0 / 60.0;

    /**
     * Clamp an asin/acos argument to [-1, 1] (float overshoot guard).
     *
     * Non-finite values pass through untouched so bad inputs surface as
     * NaN downstream, exactly like CrescentEphemeris::clamp().
     *
     * @param float $value Candidate trig argument.
     * @return float Value clamped to [-1, 1].
     */
    private function clamp(float $value): float
    {
        if (! is_finite($value)) {
            return $value;
        }

        return max(-1.0, min(1.0, $value));
    }

    /**
     * Julian centuries since J2000.0.
     *
     * Meeus Astronomical Algorithms 2nd ed., Ch. 25 p. 163 (T definition);
     * pin a7baa8b CalendricalHelper.julian_century (T).
     *
     * @param float $julianDay Julian Day number.
     * @return float Centuries since 2451545.0.
     */
    private function julianCentury(float $julianDay): float
    {
        return ($julianDay - 2451545.0) / 36525.0;
    }

    /**
     * Mean solar longitude L0 in degrees.
     *
     * Meeus 2nd ed. p. 163 eq. 25.2; pin a7baa8b
     * Astronomical.mean_solar_longitude (L0).
     *
     * @param float $century Julian centuries since J2000.0.
     * @return float Longitude unwound to [0, 360).
     */
    private function meanSolarLongitude(float $century): float
    {
        return $this->unwindAngle(
            280.4664567 + 36000.76983 * $century + 0.0003032 * $century * $century,
        );
    }

    /**
     * Mean lunar longitude Lp in degrees (nutation-series argument).
     *
     * Meeus 2nd ed. p. 144 (nutation arguments); pin a7baa8b
     * Astronomical.mean_lunar_longitude (Lp).
     *
     * @param float $century Julian centuries since J2000.0.
     * @return float Longitude unwound to [0, 360).
     */
    private function meanLunarLongitude(float $century): float
    {
        return $this->unwindAngle(218.3165 + 481267.8813 * $century);
    }

    /**
     * Ascending lunar node Omega in degrees (nutation-series argument).
     *
     * Meeus 2nd ed. p. 144 (nutation arguments); pin a7baa8b
     * Astronomical.ascending_lunar_node_longitude (Om).
     *
     * @param float $century Julian centuries since J2000.0.
     * @return float Node longitude unwound to [0, 360).
     */
    private function ascendingNode(float $century): float
    {
        return $this->unwindAngle(
            125.04452 - 1934.136261 * $century
            + 0.0020708 * $century * $century
            + $century * $century * $century / 450000.0,
        );
    }

    /**
     * Solar mean anomaly M in degrees.
     *
     * Meeus 2nd ed. p. 163 eq. 25.3; pin a7baa8b
     * Astronomical.mean_solar_anomaly (M).
     *
     * @param float $century Julian centuries since J2000.0.
     * @return float Anomaly unwound to [0, 360).
     */
    private function solarAnomaly(float $century): float
    {
        return $this->unwindAngle(
            357.52911 + 35999.05029 * $century - 0.0001537 * $century * $century,
        );
    }

    /**
     * Solar equation of the center C in degrees.
     *
     * Meeus 2nd ed. p. 164 eq. 25.4; pin a7baa8b
     * Astronomical.solar_equation_of_the_center (C).
     *
     * @param float $century Julian centuries since J2000.0.
     * @return float Center correction in degrees.
     */
    private function equationOfCenter(float $century): float
    {
        $anomalyRad = deg2rad($this->solarAnomaly($century));

        return (1.914602 - 0.004817 * $century - 0.000014 * $century * $century) * sin($anomalyRad)
            + (0.019993 - 0.000101 * $century) * sin(2.0 * $anomalyRad)
            + 0.000289 * sin(3.0 * $anomalyRad);
    }

    /**
     * Apparent solar longitude (lambda) in degrees.
     *
     * True longitude corrected for aberration (-0.00569) and nutation
     * (-0.00478 sin); Meeus 2nd ed. p. 164 eq. 25.6-25.7; pin a7baa8b
     * Astronomical.apparent_solar_longitude (lam).
     *
     * @param float $century Julian centuries since J2000.0.
     * @return float Longitude unwound to [0, 360).
     */
    private function apparentLongitude(float $century): float
    {
        return $this->unwindAngle(
            $this->meanSolarLongitude($century)
            + $this->equationOfCenter($century)
            - 0.00569
            // Truncated form is pin-verbatim (Meeus); do NOT replace with ascendingNode().
            - 0.00478 * sin(deg2rad(125.04 - 1934.136 * $century)),
        );
    }

    /**
     * Mean obliquity of the ecliptic (epsilon0) in degrees.
     *
     * Meeus 2nd ed. p. 147 eq. 22.2; pin a7baa8b
     * Astronomical.mean_obliquity_of_the_ecliptic (eps0). Note the T^3
     * term is ADDED (term1 - term2 - term3 + term4), matching the pin
     * and Meeus — not subtracted.
     *
     * @param float $century Julian centuries since J2000.0.
     * @return float Mean obliquity in degrees.
     */
    private function meanObliquity(float $century): float
    {
        return 23.439291 - 0.013004167 * $century
            - 0.0000001639 * $century * $century
            + 0.0000005036 * $century * $century * $century;
    }

    /**
     * Apparent obliquity of the ecliptic in degrees.
     *
     * Meeus 2nd ed. p. 165 (pin comment; +0.00256 cos term); pin a7baa8b
     * Astronomical.apparent_obliquity_of_the_ecliptic (epsApp).
     *
     * @param float $century Julian centuries since J2000.0.
     * @return float Apparent obliquity in degrees.
     */
    private function apparentObliquity(float $century): float
    {
        return $this->meanObliquity($century)
            // Truncated form is pin-verbatim (Meeus); do NOT replace with ascendingNode().
            + 0.00256 * cos(deg2rad(125.04 - 1934.136 * $century));
    }

    /**
     * Nutation in longitude (delta-Psi) in degrees, truncated series.
     *
     * Meeus 2nd ed. p. 144 Table 22.A (four dominant terms); pin a7baa8b
     * Astronomical.nutation_in_longitude (dPsi).
     *
     * @param float $century Julian centuries since J2000.0.
     * @return float Nutation in longitude in degrees.
     */
    private function nutationLongitude(float $century): float
    {
        $node = $this->ascendingNode($century);
        $meanSolar = $this->meanSolarLongitude($century);
        $meanLunar = $this->meanLunarLongitude($century);

        return -(17.2 / 3600.0) * sin(deg2rad($node))
            - (1.32 / 3600.0) * sin(deg2rad(2.0 * $meanSolar))
            - (0.23 / 3600.0) * sin(deg2rad(2.0 * $meanLunar))
            + (0.21 / 3600.0) * sin(deg2rad(2.0 * $node));
    }

    /**
     * Nutation in obliquity (delta-epsilon) in degrees, truncated series.
     *
     * Meeus 2nd ed. p. 144 Table 22.A (four dominant terms); pin a7baa8b
     * Astronomical.nutation_in_obliquity (dEps).
     *
     * @param float $century Julian centuries since J2000.0.
     * @return float Nutation in obliquity in degrees.
     */
    private function nutationObliquity(float $century): float
    {
        $node = $this->ascendingNode($century);
        $meanSolar = $this->meanSolarLongitude($century);
        $meanLunar = $this->meanLunarLongitude($century);

        return (9.2 / 3600.0) * cos(deg2rad($node))
            + (0.57 / 3600.0) * cos(deg2rad(2.0 * $meanSolar))
            + (0.10 / 3600.0) * cos(deg2rad(2.0 * $meanLunar))
            - (0.09 / 3600.0) * cos(deg2rad(2.0 * $node));
    }

    /**
     * Mean sidereal time at Greenwich in degrees.
     *
     * Meeus 2nd ed. Ch.12 pp. 87-88; pin a7baa8b Astronomical.mean_sidereal_time.
     *
     * @param float $century Julian centuries since J2000.0.
     * @return float Sidereal angle unwound to [0, 360).
     */
    private function meanSiderealTime(float $century): float
    {
        $square = $century * $century;
        $cube = $square * $century;
        $julianDay = $century * 36525.0 + 2451545.0;

        return $this->unwindAngle(
            280.46061837
            + 360.98564736629 * ($julianDay - 2451545.0)
            + 0.000387933 * $square
            - $cube / 38710000.0,
        );
    }

    /**
     * Reduce an angle to [0, 360) degrees.
     *
     * Pin a7baa8b FloatUtil.unwind_angle; no Meeus page (bookkeeping).
     *
     * @param float $angle Angle in degrees.
     * @return float Equivalent angle in [0, 360).
     */
    private function unwindAngle(float $angle): float
    {
        return $angle - 360.0 * floor($angle / 360.0);
    }

    /**
     * Reduce a value into [0, max) via floor (handles negatives).
     *
     * Pin a7baa8b FloatUtil.normalize_with_bound; no Meeus page.
     *
     * @param float $value Value to wrap.
     * @param float $max Exclusive upper bound.
     * @return float Wrapped value.
     */
    private function normalizeBound(float $value, float $max): float
    {
        return $value - $max * floor($value / $max);
    }

    /**
     * Approximate transit fraction for one body (Meeus Ch.15 p. 102).
     *
     * Pin a7baa8b Astronomical.approximate_transit verbatim.
     *
     * @param float $longitudeDeg Observer longitude in degrees.
     * @param float $rightAscensionDeg Right ascension in degrees.
     * @param float $siderealDeg Greenwich sidereal time in degrees.
     * @return float Day fraction in [0, 1).
     */
    private function approximateTransit(float $longitudeDeg, float $rightAscensionDeg, float $siderealDeg): float
    {
        return $this->normalizeBound(($rightAscensionDeg + (-$longitudeDeg) - $siderealDeg) / 360.0, 1.0);
    }

    /**
     * Lagrange interpolation for declination across three days.
     *
     * Pin a7baa8b Astronomical.interpolate; Meeus 2nd ed. p. 24.
     *
     * @param float $yDay Current-day value.
     * @param float $yPrev Previous-day value.
     * @param float $yNext Next-day value.
     * @param float $n Interpolation fraction.
     * @return float Interpolated value.
     */
    private function interp(float $yDay, float $yPrev, float $yNext, float $n): float
    {
        $a = $yDay - $yPrev;
        $b = $yNext - $yDay;
        $c = $b - $a;

        return $yDay + ($n / 2.0) * ($a + $b + $n * $c);
    }

    /**
     * Angle-aware interpolation for right ascension across three days.
     *
     * Pin a7baa8b Astronomical.interpolate_angles; Meeus 2nd ed. p. 24.
     * Deltas are unwound but c is NOT unwound — verbatim.
     *
     * @param float $yDay Current-day angle.
     * @param float $yPrev Previous-day angle.
     * @param float $yNext Next-day angle.
     * @param float $n Interpolation fraction.
     * @return float Interpolated angle.
     */
    private function interpAngles(float $yDay, float $yPrev, float $yNext, float $n): float
    {
        $a = $this->unwindAngle($yDay - $yPrev);
        $b = $this->unwindAngle($yNext - $yDay);
        $c = $b - $a;

        return $yDay + ($n / 2.0) * ($a + $b + $n * $c);
    }
}
