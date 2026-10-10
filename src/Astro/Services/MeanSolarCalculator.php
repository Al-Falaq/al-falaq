<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Astro\Services;

use AlFalaq\AlFalaq\Astro\Contracts\SolarModel;
use AlFalaq\AlFalaq\Astro\ValueObjects\SolarPosition;

/**
 * Mean-sun solar calculator (Meeus transcription, M1).
 *
 * @internal Entry point is SolarTime, not this class.
 */
final class MeanSolarCalculator implements SolarModel
{
    /**
     * Solar declination in degrees (M1).
     *
     * Pin a7baa8b Astronomical.solar_declination; Meeus 2nd ed. p. 165.
     *
     * @param float $julianDay Julian Day number.
     * @return float Declination in degrees.
     */
    public function declination(float $julianDay): float
    {
        $century = $this->julianCentury($julianDay);
        $lambda = $this->apparentLongitude($century);
        $epsilonApp = $this->apparentObliquity($century);

        return rad2deg(asin(sin(deg2rad($epsilonApp)) * sin(deg2rad($lambda))));
    }

    /**
     * Equation of time in minutes (M1).
     *
     * Pin a7baa8b Astronomical.equation_of_time; Meeus 2nd ed. p. 183.
     * The 0.0057183 term is the aberration-related constant from Meeus p. 183.
     *
     * @param float $julianDay Julian Day number.
     * @return float Minutes (apparent minus mean solar time).
     */
    public function equationOfTime(float $julianDay): float
    {
        $century = $this->julianCentury($julianDay);
        $meanLongitude = $this->meanSolarLongitude($century);
        $lambda = $this->apparentLongitude($century);
        $epsilonApp = $this->apparentObliquity($century);
        $epsilon0 = $this->meanObliquity($century);
        $deltaPsi = $this->nutationLongitude($century);
        $deltaEpsilon = $this->nutationObliquity($century);

        $rightAscension = $this->unwindAngle(rad2deg(atan2(
            cos(deg2rad($epsilonApp)) * sin(deg2rad($lambda)),
            cos(deg2rad($lambda)),
        )));

        return $this->closestAngle(
            $meanLongitude - 0.0057183 - $rightAscension
            + $deltaPsi * cos(deg2rad($epsilon0 + $deltaEpsilon)),
        ) * 4.0;
    }

    /**
     * Full solar position bundle for one day number (M1).
     *
     * @param float $julianDay Julian Day number.
     * @return SolarPosition Declination + equation of time.
     */
    public function position(float $julianDay): SolarPosition
    {
        return new SolarPosition(
            $this->declination($julianDay),
            $this->equationOfTime($julianDay),
        );
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
     * Wrap a signed angle to (-180, 180] degrees.
     *
     * Pin a7baa8b FloatUtil.closest_angle; no Meeus page (bookkeeping).
     * Banker's rounding (PHP_ROUND_HALF_EVEN) matches Python round().
     *
     * @param float $angle Signed angle in degrees.
     * @return float Equivalent angle in (-180, 180].
     */
    private function closestAngle(float $angle): float
    {
        if (abs($angle) <= 180.0) {
            return $angle;
        }

        return $angle - 360.0 * round($angle / 360.0, 0, PHP_ROUND_HALF_EVEN);
    }
}
