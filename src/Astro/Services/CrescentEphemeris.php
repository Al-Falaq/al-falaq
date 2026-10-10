<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Astro\Services;

use AlFalaq\AlFalaq\SharedKernel\Exceptions\AstronomicalException;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

/**
 * Sun/moon ephemeris for crescent geometry (M3).
 *
 * Stateless Meeus engine: low-precision lunar (2nd ed. Ch.47 pp. 338-342)
 * plus the mean-sun solar theory shared with MeanSolarCalculator (Ch.25
 * pp. 163-165), the parallax-in-altitude topocentric correction, and
 * apparent sidereal time (Ch.12 p. 88). TT inputs throughout; pure
 * functions of floats, so no constructor dependencies.
 *
 * @internal Entry point is CrescentCalculator, not this class.
 */
final class CrescentEphemeris
{
    /**
     * Parallax equatorial radius in km (pin CrescentGeometry.py:35, Task 0).
     */
    public const PARALLAX_RADIUS_KM = 6378.137;

    /**
     * Mean solar distance in km (IAU 2012 exact AU).
     *
     * The pin SolarCoordinates carries no distance (the pin applies no
     * parallax correction to the sun); at 1 AU the parallax term peaks at
     * ~0.0024 deg, far below the 0.01 deg golden gate.
     */
    private const SOLAR_DISTANCE_KM = 149597870.7;

    /**
     * Meeus 2nd ed. Tables 47.A + 47.B merged (pin LunarCoordinates.py:28-151).
     *
     * Schema per row: (D, M, Mp, F, Sl, Sb, Sr) with angles in 1e-6 degree
     * and radius in metres. Rows absent from one table carry 0 there.
     *
     * @var list<array{0: int, 1: int, 2: int, 3: int, 4: int, 5: int, 6: int}>
     */
    private const TABLE_47_TERMS = [
        [0, 0, 1, 0, 6288774, 0, -20905355],
        [2, 0, -1, 0, 1274027, 0, -3699111],
        [2, 0, 0, 0, 658314, 0, -2955968],
        [0, 0, 2, 0, 213618, 0, -569925],
        [0, 1, 0, 0, -185116, 0, 48888],
        [0, 0, 0, 2, -114332, 0, -3149],
        [2, 0, -2, 0, 58793, 0, 246158],
        [2, -1, -1, 0, 57066, 0, -152138],
        [2, 0, 1, 0, 53322, 0, -170733],
        [2, -1, 0, 0, 45758, 0, -204586],
        [0, 1, -1, 0, -40923, 0, -129620],
        [1, 0, 0, 0, -34720, 0, 108743],
        [0, 1, 1, 0, -30383, 0, 104755],
        [2, 0, 0, -2, 15327, 0, 10321],
        [0, 0, 1, 2, -12528, 0, 0],
        [0, 0, 1, -2, 10980, 0, 79661],
        [4, 0, -1, 0, 10675, 0, -34782],
        [0, 0, 3, 0, 10034, 0, -23210],
        [4, 0, -2, 0, 8548, 0, -21636],
        [2, 1, -1, 0, -7888, 0, 24208],
        [2, 1, 0, 0, -6766, 0, 30824],
        [1, 0, -1, 0, -5163, 0, -8379],
        [1, 1, 0, 0, 4987, 0, -16675],
        [2, -1, 1, 0, 4036, 0, -12831],
        [2, 0, 2, 0, 3994, 0, -10445],
        [4, 0, 0, 0, 3861, 0, -11650],
        [2, 0, -3, 0, 3665, 0, 14403],
        [0, 1, -2, 0, -2689, 0, -7003],
        [2, 0, -1, 2, -2602, 0, 0],
        [2, -1, -2, 0, 2390, 0, 10056],
        [1, 0, 1, 0, -2348, 0, 6322],
        [2, -2, 0, 0, 2236, 0, -9884],
        [0, 1, 2, 0, -2120, 0, 5751],
        [0, 2, 0, 0, -2069, 0, 0],
        [2, -2, -1, 0, 2048, 0, -4950],
        [2, 0, 1, -2, -1773, 0, 4130],
        [2, 0, 0, 2, -1595, 0, 0],
        [4, -1, -1, 0, 1215, 0, -3958],
        [0, 0, 2, 2, -1110, 0, 0],
        [3, 0, -1, 0, -892, 0, 3258],
        [2, 1, 1, 0, -810, 0, 2616],
        [4, -1, -2, 0, 759, 0, -1897],
        [0, 2, -1, 0, -713, 0, -2117],
        [2, 2, -1, 0, -700, 0, 2354],
        [2, 1, -2, 0, 691, 0, 0],
        [2, -1, 0, -2, 596, 0, 0],
        [4, 0, 1, 0, 549, 0, -1423],
        [0, 0, 4, 0, 537, 0, -1117],
        [4, -1, 0, 0, 520, 0, -1571],
        [1, 0, -2, 0, -487, 0, -1739],
        [2, 1, 0, -2, -399, 0, 0],
        [0, 0, 2, -2, -381, 0, -4421],
        [1, 1, 1, 0, 351, 0, 0],
        [3, 0, -2, 0, -340, 0, 0],
        [4, 0, -3, 0, 330, 0, 0],
        [2, -1, 2, 0, 327, 0, 0],
        [0, 2, 1, 0, -323, 0, 1165],
        [1, 1, -1, 0, 299, 0, 0],
        [2, 0, 3, 0, 294, 0, 0],
        [2, 0, -1, -2, 0, 0, 8752],
        [0, 0, 0, 1, 0, 5128122, 0],
        [0, 0, 1, 1, 0, 280602, 0],
        [0, 0, 1, -1, 0, 277693, 0],
        [2, 0, 0, -1, 0, 173237, 0],
        [2, 0, -1, 1, 0, 55413, 0],
        [2, 0, -1, -1, 0, 46271, 0],
        [2, 0, 0, 1, 0, 32573, 0],
        [0, 0, 2, 1, 0, 17198, 0],
        [2, 0, 1, -1, 0, 9266, 0],
        [0, 0, 2, -1, 0, 8822, 0],
        [2, -1, 0, -1, 0, 8216, 0],
        [2, 0, -2, -1, 0, 4324, 0],
        [2, 0, 1, 1, 0, 4200, 0],
        [2, 1, 0, -1, 0, -3359, 0],
        [2, -1, -1, 1, 0, 2463, 0],
        [2, -1, 0, 1, 0, 2211, 0],
        [2, -1, -1, -1, 0, 2065, 0],
        [0, 1, -1, -1, 0, -1870, 0],
        [4, 0, -1, -1, 0, 1828, 0],
        [0, 1, 0, 1, 0, -1794, 0],
        [0, 0, 0, 3, 0, -1749, 0],
        [0, 1, -1, 1, 0, -1565, 0],
        [1, 0, 0, 1, 0, -1491, 0],
        [0, 1, 1, 1, 0, -1475, 0],
        [0, 1, 1, -1, 0, -1410, 0],
        [0, 1, 0, -1, 0, -1344, 0],
        [1, 0, 0, -1, 0, -1335, 0],
        [0, 0, 3, 1, 0, 1107, 0],
        [4, 0, 0, -1, 0, 1021, 0],
        [4, 0, -1, 1, 0, 833, 0],
        [0, 0, 1, -3, 0, 777, 0],
        [4, 0, -2, 1, 0, 671, 0],
        [2, 0, 0, -3, 0, 607, 0],
        [2, 0, 2, -1, 0, 596, 0],
        [2, -1, 1, -1, 0, 491, 0],
        [2, 0, -2, 1, 0, -451, 0],
        [0, 0, 3, -1, 0, 439, 0],
        [2, 0, 2, 1, 0, 422, 0],
        [2, 0, -3, -1, 0, 421, 0],
        [2, 1, -1, 1, 0, -366, 0],
        [2, 1, 0, 1, 0, -351, 0],
        [4, 0, 0, 1, 0, 331, 0],
        [2, -1, 1, 1, 0, 315, 0],
        [2, -2, 0, -1, 0, 302, 0],
        [0, 0, 1, 3, 0, -283, 0],
        [2, 1, 1, -1, 0, -229, 0],
        [1, 1, 0, -1, 0, 223, 0],
        [1, 1, 0, 1, 0, 223, 0],
        [0, 1, -2, -1, 0, -220, 0],
        [2, 1, -1, -1, 0, -220, 0],
        [1, 0, 1, 1, 0, -185, 0],
        [2, -1, -2, -1, 0, 181, 0],
        [0, 1, 2, 1, 0, -177, 0],
        [4, 0, -2, -1, 0, 176, 0],
        [4, -1, -1, -1, 0, 166, 0],
        [1, 0, 1, -1, 0, -164, 0],
        [4, 0, 1, -1, 0, 132, 0],
        [1, 0, -1, -1, 0, -119, 0],
        [4, -1, 0, -1, 0, 115, 0],
        [2, -2, 0, 1, 0, 107, 0],
    ];

    /**
     * Geocentric solar position at a TT Julian day.
     *
     * Pin a7baa8b SolarCoordinates (mean longitude/anomaly, equation of
     * the center, apparent longitude, RA/dec); Meeus 2nd ed. pp. 163-165.
     * Distance is the exact-AU constant (the pin carries none).
     *
     * @param float $julianDayTT Terrestrial-time Julian day.
     * @return array{ra: float, dec: float, distanceKm: float} RA/dec in degrees, distance in km.
     */
    public function solarGeocentric(float $julianDayTT): array
    {
        $century = $this->julianCentury($julianDayTT);
        $lambda = $this->apparentLongitude($century);
        $epsilonApp = $this->apparentObliquity($century);

        $lambdaRad = deg2rad($lambda);
        $epsilonRad = deg2rad($epsilonApp);
        $dec = rad2deg(asin($this->clamp(sin($epsilonRad) * sin($lambdaRad))));
        $ra = $this->unwindAngle(rad2deg(atan2(
            cos($epsilonRad) * sin($lambdaRad),
            cos($lambdaRad),
        )));

        return $this->finiteGeo('solarGeocentric', $ra, $dec, self::SOLAR_DISTANCE_KM);
    }

    /**
     * Geocentric lunar position at a TT Julian day (Meeus Ch.47 low precision).
     *
     * Pin a7baa8b LunarCoordinates verbatim: mean elements Lp/D/M/Mp/F,
     * merged Table 47.A+47.B series with the E^|m| factor (eq. 47.6), the
     * additive Venus/Jupiter/Earth-figure corrections (p. 338), and the
     * ecliptic-to-equatorial conversion; Meeus 2nd ed. pp. 338-342.
     *
     * @param float $julianDayTT Terrestrial-time Julian day.
     * @return array{ra: float, dec: float, distanceKm: float} RA/dec in degrees, distance in km.
     */
    public function lunarGeocentric(float $julianDayTT): array
    {
        $century = $this->julianCentury($julianDayTT);
        $lonMean = $this->unwindAngle(218.3164477 + 481267.88123421 * $century - 0.0015786 * $century * $century);
        $elongation = $this->unwindAngle(297.8501921 + 445267.1114034 * $century - 0.0018819 * $century * $century);
        $anomaly = $this->unwindAngle(357.5291092 + 35999.0502909 * $century - 0.0001536 * $century * $century);
        $lunarAnomaly = $this->unwindAngle(134.9633964 + 477198.8675055 * $century + 0.0087414 * $century * $century);
        $argument = $this->unwindAngle(93.2720950 + 483202.0175233 * $century - 0.0036539 * $century * $century);

        $eBase = 1.0 - 0.002516 * $century - 0.0000074 * $century * $century;
        $eSquared = $eBase * $eBase;

        $lonSum = 0.0;
        $latSum = 0.0;
        $radiusSum = 0.0;

        foreach (self::TABLE_47_TERMS as [$dCoef, $mCoef, $mpCoef, $fCoef, $lonCoef, $latCoef, $radiusCoef]) {
            $term = deg2rad($dCoef * $elongation + $mCoef * $anomaly + $mpCoef * $lunarAnomaly + $fCoef * $argument);
            $mAbs = abs($mCoef);
            $eFactor = $mAbs === 1 ? $eBase : ($mAbs === 2 ? $eSquared : 1.0);
            $lonSum += $eFactor * $lonCoef * sin($term);
            $latSum += $eFactor * $latCoef * sin($term);
            $radiusSum += $eFactor * $radiusCoef * cos($term);
        }

        $a1 = deg2rad($this->unwindAngle(119.75 + 131.849 * $century));
        $a2 = deg2rad($this->unwindAngle(53.09 + 479264.290 * $century));
        $a3 = deg2rad($this->unwindAngle(313.45 + 481266.484 * $century));
        $lonRad = deg2rad($lonMean);
        $argRad = deg2rad($argument);
        $mpRad = deg2rad($lunarAnomaly);
        $lonSum += 3958.0 * sin($a1) + 1962.0 * sin($lonRad - $argRad) + 318.0 * sin($a2);
        $latSum += -2235.0 * sin($lonRad) + 382.0 * sin($a3)
            + 175.0 * sin($a1 - $argRad) + 175.0 * sin($a1 + $argRad)
            + 127.0 * sin($lonRad - $mpRad) - 115.0 * sin($lonRad + $mpRad);

        $longitude = $this->unwindAngle($lonMean + $lonSum / 1e6);
        $latitude = $latSum / 1e6;
        $distanceKm = 385000.56 + $radiusSum / 1000.0;

        $obliquity = deg2rad($this->apparentObliquity($century));
        $lambda = deg2rad($longitude);
        $beta = deg2rad($latitude);
        $dec = rad2deg(asin($this->clamp(
            sin($beta) * cos($obliquity) + cos($beta) * sin($obliquity) * sin($lambda),
        )));
        $ra = $this->unwindAngle(rad2deg(atan2(
            sin($lambda) * cos($obliquity) - tan($beta) * sin($obliquity),
            cos($lambda),
        )));

        return $this->finiteGeo('lunarGeocentric', $ra, $dec, $distanceKm);
    }

    /**
     * Topocentric altitude/azimuth with parallax (asin(R/d) form per spec).
     *
     * Pin a7baa8b CrescentGeometry._altaz (hour-angle alt/az) plus the
     * parallax-in-altitude correction (crescent_geometry_at_sunset):
     * parallax from asin(R/d) with the asin argument clamped, then
     * topo_alt = geo_alt − parallax·cos(geo_alt); azimuth unchanged.
     *
     * @param float $raDeg Geocentric right ascension in degrees.
     * @param float $decDeg Geocentric declination in degrees.
     * @param float $distanceKm Geocentric distance in km.
     * @param Coordinates $observer Observer location.
     * @param float $lstHours Local sidereal time in hours.
     * @return array{altitudeDeg: float, azimuthDeg: float} Topocentric horizontal coordinates.
     */
    public function topocentric(float $raDeg, float $decDeg, float $distanceKm, Coordinates $observer, float $lstHours): array
    {
        $hourAngle = deg2rad($lstHours * 15.0 - $raDeg);
        $latitude = deg2rad($observer->latitude);
        $declination = deg2rad($decDeg);

        $sinAlt = sin($latitude) * sin($declination)
            + cos($latitude) * cos($declination) * cos($hourAngle);
        $altGeo = rad2deg(asin($this->clamp($sinAlt)));
        $azimuth = $this->unwindAngle(rad2deg(atan2(
            sin($hourAngle),
            cos($hourAngle) * sin($latitude) - tan($declination) * cos($latitude),
        )) + 180.0);

        // Pin-verbatim max() intent (CrescentGeometry.py:237), written
        // NaN-safe: a non-finite distance falls back to 1.0 km and the
        // finite-output guard below still raises on bad angles.
        $safeDistance = $distanceKm > 1.0 ? $distanceKm : 1.0;
        $parallax = rad2deg(asin($this->clamp(self::PARALLAX_RADIUS_KM / $safeDistance)));
        $altitude = $altGeo - $parallax * cos(deg2rad($altGeo));

        foreach (['altitudeDeg' => $altitude, 'azimuthDeg' => $azimuth] as $name => $value) {
            if (! is_finite($value)) {
                throw new AstronomicalException(sprintf('CrescentEphemeris::topocentric produced a non-finite %s.', $name));
            }
        }

        return ['altitudeDeg' => $altitude, 'azimuthDeg' => $azimuth];
    }

    /**
     * Local sidereal time in hours (Meeus Ch.12; confirm formula via pin refs).
     *
     * Pin a7baa8b SolarCoordinates.apparent_sidereal_time (mean sidereal
     * time plus the nutation term, p. 88) shifted by east-positive
     * longitude and scaled to hours; Meeus 2nd ed. pp. 87-88.
     *
     * @param float $julianDayUT UT Julian day.
     * @param float $longitudeDeg East-positive longitude in degrees.
     * @return float LST in hours, unwound to [0, 24).
     */
    public function siderealTime(float $julianDayUT, float $longitudeDeg): float
    {
        $century = $this->julianCentury($julianDayUT);
        $obliquity = $this->meanObliquity($century) + $this->nutationObliquity($century);
        $greenwich = $this->meanSiderealTime($century)
            + $this->nutationLongitude($century) * cos(deg2rad($obliquity));
        $lst = $this->unwindAngle($greenwich + $longitudeDeg) / 15.0;

        if (! is_finite($lst)) {
            throw new AstronomicalException('CrescentEphemeris::siderealTime produced a non-finite LST.');
        }

        return $lst;
    }

    /**
     * Pack a geocentric triple, refusing non-finite engine residuals.
     *
     * Non-finite inputs propagate through the trig chain and surface here,
     * so no separate input guard is needed: nothing non-finite escapes.
     *
     * @param string $caller Calling method name for the message.
     * @param float $ra Right ascension in degrees.
     * @param float $dec Declination in degrees.
     * @param float $distanceKm Geocentric distance in km.
     * @return array{ra: float, dec: float, distanceKm: float} Validated triple.
     */
    private function finiteGeo(string $caller, float $ra, float $dec, float $distanceKm): array
    {
        foreach (['ra' => $ra, 'dec' => $dec, 'distanceKm' => $distanceKm] as $name => $value) {
            if (! is_finite($value)) {
                throw new AstronomicalException(sprintf('CrescentEphemeris::%s produced a non-finite %s.', $caller, $name));
            }
        }

        return ['ra' => $ra, 'dec' => $dec, 'distanceKm' => $distanceKm];
    }

    /**
     * Clamp an asin/acos argument to [-1, 1] (float overshoot guard).
     *
     * Non-finite values pass through untouched so the finite-output guard
     * downstream still raises AstronomicalException on bad inputs.
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
     * Meeus 2nd ed. p. 165; pin a7baa8b Astronomical.mean_sidereal_time.
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
}
