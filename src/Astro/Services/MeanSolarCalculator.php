<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Astro\Services;

use AlFalaq\AlFalaq\Astro\Contracts\SolarModel;
use AlFalaq\AlFalaq\Astro\ValueObjects\SolarPosition;
use AlFalaq\AlFalaq\Astro\ValueObjects\SolarTime;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\AstronomicalException;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\JulianDay;

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
     * Core solar day: transit, sunrise, sunset in UTC (Meeus Ch.15).
     *
     * Pin a7baa8b SolarTime.__init__ + Astronomical.corrected_transit /
     * corrected_hour_angle; Meeus 2nd ed. pp. 102/165.
     *
     * @param Coordinates $coordinates Observer location.
     * @param CivilDate $date Civil date whose solar day is computed.
     * @param mixed $elevationM Observer height in metres; finite and >= 0.
     * @return SolarTime Transit, sunrise, sunset in UTC.
     */
    public function solarTime(Coordinates $coordinates, CivilDate $date, mixed $elevationM): SolarTime
    {
        $elevation = $this->assertElevation($elevationM);
        $julianDay = JulianDay::fromCivilDate($date)->toFloat();

        $prev = $this->solarTriplet($julianDay - 1.0);
        $curr = $this->solarTriplet($julianDay);
        $next = $this->solarTriplet($julianDay + 1.0);

        $m0 = $this->approximateTransit($coordinates->longitude, $curr['ra'], $curr['st']);
        $altitude = $this->solarAltitude($elevation);

        $transitHours = $this->transitHours(
            $m0,
            $coordinates->longitude,
            $curr['st'],
            $curr['ra'],
            $prev['ra'],
            $next['ra'],
        );
        $sunriseHours = $this->hourAngleHours(
            $m0,
            $altitude,
            $coordinates->latitude,
            $coordinates->longitude,
            false,
            $curr['st'],
            $curr['ra'],
            $prev['ra'],
            $next['ra'],
            $curr['dec'],
            $prev['dec'],
            $next['dec'],
        );
        $sunsetHours = $this->hourAngleHours(
            $m0,
            $altitude,
            $coordinates->latitude,
            $coordinates->longitude,
            true,
            $curr['st'],
            $curr['ra'],
            $prev['ra'],
            $next['ra'],
            $curr['dec'],
            $prev['dec'],
            $next['dec'],
        );

        $transit = $this->hoursToUtc($date, $transitHours);
        $sunrise = $this->hoursToUtc($date, $sunriseHours);
        $sunset = $this->hoursToUtc($date, $sunsetHours);

        if ($transit === null || $sunrise === null || $sunset === null) {
            throw new AstronomicalException(sprintf(
                'Unable to compute solar times: sunrise, sunset, or transit is undefined '
                . 'for these coordinates and date (polar day/night). coordinates=%s, date=%s.',
                (string) $coordinates,
                $date->isoformat(),
            ));
        }

        return new SolarTime($transit, $sunrise, $sunset);
    }

    /**
     * Instant the sun reaches a depression angle, or null when unreachable.
     *
     * Pin a7baa8b SolarTime.hour_angle + Astronomical.corrected_hour_angle;
     * Meeus 2nd ed. p. 102. Geometry misses return null, never NaN.
     *
     * @param Coordinates $coordinates Observer location.
     * @param CivilDate $date Civil date owning the event.
     * @param mixed $elevationM Observer height in metres; finite and >= 0.
     * @param float $angleDeg Solar altitude in degrees (e.g. -18.0 for fajr).
     * @param bool $afterTransit True for evening, false for morning.
     * @return \DateTimeImmutable|null Event instant in UTC, or null when the sun never reaches the angle.
     */
    public function hourAngle(
        Coordinates $coordinates,
        CivilDate $date,
        mixed $elevationM,
        float $angleDeg,
        bool $afterTransit,
    ): ?\DateTimeImmutable {
        $this->assertElevation($elevationM);
        $julianDay = JulianDay::fromCivilDate($date)->toFloat();

        $prev = $this->solarTriplet($julianDay - 1.0);
        $curr = $this->solarTriplet($julianDay);
        $next = $this->solarTriplet($julianDay + 1.0);

        $m0 = $this->approximateTransit($coordinates->longitude, $curr['ra'], $curr['st']);
        $hours = $this->hourAngleHours(
            $m0,
            $angleDeg,
            $coordinates->latitude,
            $coordinates->longitude,
            $afterTransit,
            $curr['st'],
            $curr['ra'],
            $prev['ra'],
            $next['ra'],
            $curr['dec'],
            $prev['dec'],
            $next['dec'],
        );

        return $this->hoursToUtc($date, $hours);
    }

    /**
     * Asr instant for a shadow-length factor, or null when unreachable.
     *
     * Pin a7baa8b SolarTime.afternoon; shadow angle from the noon-shadow
     * geometry, then an evening hour angle. Null when the sun never climbs
     * high enough (polar-near boundary), never NaN.
     *
     * @param Coordinates $coordinates Observer location.
     * @param CivilDate $date Civil date owning the event.
     * @param mixed $elevationM Observer height in metres; finite and >= 0.
     * @param mixed $shadowLength Shadow factor (1.0 Shafi, 2.0 Hanafi); finite and > 0.
     * @return \DateTimeImmutable|null Asr instant in UTC, or null when unreachable.
     */
    public function afternoon(
        Coordinates $coordinates,
        CivilDate $date,
        mixed $elevationM,
        mixed $shadowLength,
    ): ?\DateTimeImmutable {
        $this->assertElevation($elevationM);
        $shadow = $this->assertShadowLength($shadowLength);
        $julianDay = JulianDay::fromCivilDate($date)->toFloat();

        $prev = $this->solarTriplet($julianDay - 1.0);
        $curr = $this->solarTriplet($julianDay);
        $next = $this->solarTriplet($julianDay + 1.0);

        $m0 = $this->approximateTransit($coordinates->longitude, $curr['ra'], $curr['st']);
        $angle = $this->afternoonAngle($coordinates->latitude, $curr['dec'], $shadow);
        $hours = $this->hourAngleHours(
            $m0,
            $angle,
            $coordinates->latitude,
            $coordinates->longitude,
            true,
            $curr['st'],
            $curr['ra'],
            $prev['ra'],
            $next['ra'],
            $curr['dec'],
            $prev['dec'],
            $next['dec'],
        );

        return $this->hoursToUtc($date, $hours);
    }

    /**
     * Whether a full solar schedule exists for this date.
     *
     * Exact mirror of pin PrayerTimes._schedule_defined: today rise+set
     * non-null AND tomorrow rise non-null. Probes strictly less than full
     * solarTime (no transit), hence this separate method, not try/catch.
     * Dates at the 1900/2100 edges propagate ValidationException from
     * plusDays() when probing tomorrow — acceptable, not swallowed.
     *
     * @param Coordinates $coordinates Observer location.
     * @param CivilDate $date Civil date to probe.
     * @param mixed $elevationM Observer height in metres; finite and >= 0.
     * @return bool True when today rise+set and tomorrow rise are defined.
     */
    public function isSolarDefined(Coordinates $coordinates, CivilDate $date, mixed $elevationM): bool
    {
        $elevation = $this->assertElevation($elevationM);
        $julianDay = JulianDay::fromCivilDate($date)->toFloat();

        $prev = $this->solarTriplet($julianDay - 1.0);
        $curr = $this->solarTriplet($julianDay);
        $next = $this->solarTriplet($julianDay + 1.0);
        $nextNext = $this->solarTriplet($julianDay + 2.0);

        $m0 = $this->approximateTransit($coordinates->longitude, $curr['ra'], $curr['st']);
        $altitude = $this->solarAltitude($elevation);

        $sunriseHours = $this->hourAngleHours(
            $m0,
            $altitude,
            $coordinates->latitude,
            $coordinates->longitude,
            false,
            $curr['st'],
            $curr['ra'],
            $prev['ra'],
            $next['ra'],
            $curr['dec'],
            $prev['dec'],
            $next['dec'],
        );
        $sunsetHours = $this->hourAngleHours(
            $m0,
            $altitude,
            $coordinates->latitude,
            $coordinates->longitude,
            true,
            $curr['st'],
            $curr['ra'],
            $prev['ra'],
            $next['ra'],
            $curr['dec'],
            $prev['dec'],
            $next['dec'],
        );

        if ($this->hoursToUtc($date, $sunriseHours) === null) {
            return false;
        }

        if ($this->hoursToUtc($date, $sunsetHours) === null) {
            return false;
        }

        $tomorrow = $date->plusDays(1);
        $m0Tomorrow = $this->approximateTransit($coordinates->longitude, $next['ra'], $next['st']);
        $tomorrowRiseHours = $this->hourAngleHours(
            $m0Tomorrow,
            $altitude,
            $coordinates->latitude,
            $coordinates->longitude,
            false,
            $next['st'],
            $next['ra'],
            $curr['ra'],
            $nextNext['ra'],
            $next['dec'],
            $curr['dec'],
            $nextNext['dec'],
        );

        return $this->hoursToUtc($tomorrow, $tomorrowRiseHours) !== null;
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
     * Right ascension, declination, and sidereal time for one Julian Day.
     *
     * Pin a7baa8b SolarCoordinates.__init__; Meeus 2nd ed. pp. 165/88.
     * Declination reuses the public method for a single source of truth.
     *
     * @param float $julianDay Julian Day number.
     * @return array{ra: float, dec: float, st: float} Right ascension, declination, apparent sidereal time.
     */
    private function solarTriplet(float $julianDay): array
    {
        $century = $this->julianCentury($julianDay);
        $lambda = $this->apparentLongitude($century);
        $epsilon0 = $this->meanObliquity($century);
        $epsilonApp = $this->apparentObliquity($century);
        $deltaPsi = $this->nutationLongitude($century);
        $deltaEpsilon = $this->nutationObliquity($century);

        $lambdaRad = deg2rad($lambda);
        $epsilonAppRad = deg2rad($epsilonApp);
        $rightAscension = $this->unwindAngle(rad2deg(atan2(
            cos($epsilonAppRad) * sin($lambdaRad),
            cos($lambdaRad),
        )));

        // Pin multiplies by 3600 then divides by 3600; the factors cancel.
        $siderealTime = $this->meanSiderealTime($century)
            + $deltaPsi * cos(deg2rad($epsilon0 + $deltaEpsilon));

        return ['ra' => $rightAscension, 'dec' => $this->declination($julianDay), 'st' => $siderealTime];
    }

    /**
     * Apparent solar altitude for rise/set with dip of the horizon.
     *
     * Pin a7baa8b SolarTime.__init__ solar_altitude verbatim (-50/60, not
     * the PRD-rounded -0.833); dip 0.0293·sqrt(h), h in metres.
     *
     * @param float $elevationM Observer height in metres.
     * @return float Altitude in degrees.
     */
    private function solarAltitude(float $elevationM): float
    {
        return -50.0 / 60.0 - 0.0293 * sqrt($elevationM);
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
     * Approximate transit fraction m0 for one solar day.
     *
     * Pin a7baa8b Astronomical.approximate_transit; Meeus 2nd ed. p. 102.
     *
     * @param float $longitude Observer longitude in degrees.
     * @param float $rightAscension Current right ascension in degrees.
     * @param float $siderealTime Current sidereal time in degrees.
     * @return float Day fraction in [0, 1).
     */
    private function approximateTransit(float $longitude, float $rightAscension, float $siderealTime): float
    {
        return $this->normalizeBound(($rightAscension + (-$longitude) - $siderealTime) / 360.0, 1.0);
    }

    /**
     * Corrected transit in float hours (Meeus Ch.15 interpolation).
     *
     * Pin a7baa8b Astronomical.corrected_transit; Meeus 2nd ed. p. 102.
     *
     * @param float $m0 Approximate transit fraction.
     * @param float $longitude Observer longitude in degrees.
     * @param float $siderealTime Current sidereal time in degrees.
     * @param float $raCurr Current right ascension in degrees.
     * @param float $raPrev Previous-day right ascension in degrees.
     * @param float $raNext Next-day right ascension in degrees.
     * @return float Transit in float hours.
     */
    private function transitHours(
        float $m0,
        float $longitude,
        float $siderealTime,
        float $raCurr,
        float $raPrev,
        float $raNext,
    ): float {
        $theta = $this->unwindAngle($siderealTime + 360.985647 * $m0);
        $alpha = $this->unwindAngle($this->interpAngles($raCurr, $raPrev, $raNext, $m0));
        $hour = $this->closestAngle($theta - (-$longitude) - $alpha);

        return ($m0 + $hour / -360.0) * 24.0;
    }

    /**
     * Corrected hour angle in float hours, NaN when unreachable.
     *
     * Pin a7baa8b Astronomical.corrected_hour_angle; Meeus 2nd ed. p. 102.
     * The pin catches acos-domain ValueError and ZeroDivisionError; PHP
     * returns NaN via explicit guards (acos returns NaN, no throw).
     *
     * @param float $m0 Approximate transit fraction.
     * @param float $altitudeDeg Target solar altitude in degrees.
     * @param float $latitude Observer latitude in degrees.
     * @param float $longitude Observer longitude in degrees.
     * @param bool $afterTransit True for evening, false for morning.
     * @param float $siderealTime Current sidereal time in degrees.
     * @param float $raCurr Current right ascension in degrees.
     * @param float $raPrev Previous-day right ascension in degrees.
     * @param float $raNext Next-day right ascension in degrees.
     * @param float $decCurr Current declination in degrees.
     * @param float $decPrev Previous-day declination in degrees.
     * @param float $decNext Next-day declination in degrees.
     * @return float Event in float hours, or NaN when the sun never reaches the altitude.
     */
    private function hourAngleHours(
        float $m0,
        float $altitudeDeg,
        float $latitude,
        float $longitude,
        bool $afterTransit,
        float $siderealTime,
        float $raCurr,
        float $raPrev,
        float $raNext,
        float $decCurr,
        float $decPrev,
        float $decNext,
    ): float {
        $ratio = (sin(deg2rad($altitudeDeg)) - sin(deg2rad($latitude)) * sin(deg2rad($decCurr)))
            / (cos(deg2rad($latitude)) * cos(deg2rad($decCurr)));

        if (! is_finite($ratio) || abs($ratio) > 1.0) {
            return NAN;
        }

        $halfHour = rad2deg(acos($ratio));
        $m = $afterTransit ? $m0 + $halfHour / 360.0 : $m0 - $halfHour / 360.0;

        $theta = $this->unwindAngle($siderealTime + 360.985647 * $m);
        $alpha = $this->unwindAngle($this->interpAngles($raCurr, $raPrev, $raNext, $m));
        $declination = $this->interp($decCurr, $decPrev, $decNext, $m);
        $hour = $theta - (-$longitude) - $alpha;

        $altitude = rad2deg(asin(
            sin(deg2rad($latitude)) * sin(deg2rad($declination))
            + cos(deg2rad($latitude)) * cos(deg2rad($declination)) * cos(deg2rad($hour)),
        ));

        $denominator = 360.0 * cos(deg2rad($declination)) * cos(deg2rad($latitude)) * sin(deg2rad($hour));

        if ($denominator === 0.0) {
            return NAN;
        }

        return ($m + ($altitude - $altitudeDeg) / $denominator) * 24.0;
    }

    /**
     * Lagrange interpolation for declination across three days.
     *
     * Pin a7baa8b Astronomical.interpolate; Meeus 2nd ed. p. 24.
     *
     * @param float $yCurr Current-day value.
     * @param float $yPrev Previous-day value.
     * @param float $yNext Next-day value.
     * @param float $n Interpolation fraction.
     * @return float Interpolated value.
     */
    private function interp(float $yCurr, float $yPrev, float $yNext, float $n): float
    {
        $a = $yCurr - $yPrev;
        $b = $yNext - $yCurr;
        $c = $b - $a;

        return $yCurr + ($n / 2.0) * ($a + $b + $n * $c);
    }

    /**
     * Angle-aware interpolation for right ascension across three days.
     *
     * Pin a7baa8b Astronomical.interpolate_angles; Meeus 2nd ed. p. 24.
     * Deltas are unwound but c is NOT unwound — verbatim.
     *
     * @param float $yCurr Current-day angle.
     * @param float $yPrev Previous-day angle.
     * @param float $yNext Next-day angle.
     * @param float $n Interpolation fraction.
     * @return float Interpolated angle.
     */
    private function interpAngles(float $yCurr, float $yPrev, float $yNext, float $n): float
    {
        $a = $this->unwindAngle($yCurr - $yPrev);
        $b = $this->unwindAngle($yNext - $yCurr);
        $c = $b - $a;

        return $yCurr + ($n / 2.0) * ($a + $b + $n * $c);
    }

    /**
     * Float hours to a UTC instant on the civil date.
     *
     * Floor-based total seconds, proven equivalent to pin
     * TimeComponents divmod chain (incl. negatives). Non-finite maps to
     * null (unreachable geometry), never NaN.
     *
     * @param CivilDate $date Civil date owning midnight UTC.
     * @param float $hours Float hours since midnight.
     * @return \DateTimeImmutable|null UTC instant, or null when hours is non-finite.
     */
    private function hoursToUtc(CivilDate $date, float $hours): ?\DateTimeImmutable
    {
        if (! is_finite($hours)) {
            return null;
        }

        $totalSeconds = (int) floor($hours * 3600.0);
        $moved = $date->toUtcMidnight()->modify(sprintf(
            '%s%d seconds',
            $totalSeconds < 0 ? '-' : '+',
            abs($totalSeconds),
        ));

        if ($moved === false) {
            throw new ValidationException('Unable to convert hours to UTC.');
        }

        return $moved;
    }

    /**
     * Asr shadow angle for a latitude, declination, and shadow factor.
     *
     * Pin a7baa8b SolarTime.afternoon (TODO cites Swift original).
     *
     * @param float $latitude Observer latitude in degrees.
     * @param float $declination Current declination in degrees.
     * @param float $shadowLength Shadow factor (1.0 Shafi, 2.0 Hanafi).
     * @return float Solar altitude in degrees.
     */
    private function afternoonAngle(float $latitude, float $declination, float $shadowLength): float
    {
        $tangent = abs($latitude - $declination);
        $inverse = $shadowLength + tan(deg2rad($tangent));

        return rad2deg(atan(1.0 / $inverse));
    }

    /**
     * Validate observer elevation in metres.
     *
     * Pin a7baa8b SolarTime.__init__ elevation guard (message style mirrored).
     *
     * @param mixed $value Candidate elevation.
     * @return float Validated elevation.
     */
    private function assertElevation(mixed $value): float
    {
        if (is_bool($value) || (! is_int($value) && ! is_float($value))) {
            throw new ValidationException(sprintf(
                'Elevation must be a finite non-negative number of metres, got %s.',
                var_export($value, true),
            ));
        }

        $elevation = (float) $value;

        if (! is_finite($elevation) || $elevation < 0.0) {
            throw new ValidationException(sprintf(
                'Elevation must be a finite non-negative number of metres, got %s.',
                var_export($value, true),
            ));
        }

        return $elevation;
    }

    /**
     * Validate an Asr shadow-length factor.
     *
     * Pin ShadowLength is construct-validated; PHP takes a raw float here
     * so the guard lives at this boundary.
     *
     * @param mixed $value Candidate shadow factor.
     * @return float Validated factor.
     */
    private function assertShadowLength(mixed $value): float
    {
        if (is_bool($value) || (! is_int($value) && ! is_float($value))) {
            throw new ValidationException(sprintf(
                'Shadow length must be a finite number greater than zero, got %s.',
                var_export($value, true),
            ));
        }

        $shadow = (float) $value;

        if (! is_finite($shadow) || $shadow <= 0.0) {
            throw new ValidationException(sprintf(
                'Shadow length must be a finite number greater than zero, got %s.',
                var_export($value, true),
            ));
        }

        return $shadow;
    }
}
