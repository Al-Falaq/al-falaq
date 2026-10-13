<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Astro\Services;

use AlFalaq\AlFalaq\Astro\Contracts\SolarDayEngine;
use AlFalaq\AlFalaq\Astro\Traits\SharesSolarTheory;
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
 * The 4 day-level methods (solarTime/hourAngle/afternoon/isSolarDefined) are
 * exposed through the SolarDayEngine port; the narrow SolarModel contract
 * keeps only declination/equationOfTime.
 *
 * Default SolarDayEngine adapter: safe to construct for explicit service
 * wiring per docs/integrations.md (userland otherwise goes through Moon/
 * or the relevant Prayer service, never Astro internals).
 */
final class MeanSolarCalculator implements SolarDayEngine
{
    use SharesSolarTheory;

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
     * Fractional hours since midnight UTC for a solar depression angle, or null when unreachable.
     *
     * Full-precision twin of hourAngle(): the same Meeus Ch.15 corrected
     * hour angle (pin Astronomical.corrected_hour_angle) without the
     * whole-second floor that hoursToUtc applies for DateTime consumers.
     * The crescent pipeline anchors its Julian-day math on this value;
     * prayer keeps the DateTime path untouched.
     *
     * @param Coordinates $coordinates Observer location.
     * @param CivilDate $date Civil date owning the event.
     * @param mixed $elevationM Observer height in metres; finite and >= 0.
     * @param float $angleDeg Solar altitude in degrees (e.g. -18.0 for fajr).
     * @param bool $afterTransit True for evening, false for morning.
     * @return float|null Fractional hours since midnight UTC, or null when the sun never reaches the angle.
     */
    public function hourAngleFloat(
        Coordinates $coordinates,
        CivilDate $date,
        mixed $elevationM,
        float $angleDeg,
        bool $afterTransit,
    ): ?float {
        if (! is_finite($angleDeg)) {
            throw new ValidationException(sprintf(
                'Angle must be a finite number of degrees, got %s.',
                var_export($angleDeg, true),
            ));
        }

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

        if (! is_finite($hours)) {
            return null;
        }

        return $hours;
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
        $hours = $this->hourAngleFloat($coordinates, $date, $elevationM, $angleDeg, $afterTransit);

        if ($hours === null) {
            return null;
        }

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
     * Pin a7baa8b SolarTime.__init__ solar_altitude verbatim (-50/60, not -0.833); dip 0.0293·sqrt(h), h in metres.
     *
     * @param float $elevationM Observer height in metres.
     * @return float Altitude in degrees.
     */
    private function solarAltitude(float $elevationM): float
    {
        return self::SUNSET_DEPRESSION_DEG - 0.0293 * sqrt($elevationM);
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
     * Pin a7baa8b `SolarTime.afternoon` verbatim: cotangent form
     * arccot(shadow + tan|lat - dec|); the pin's TODO cites the Swift
     * original (adhan-swift `SolarTime.afternoonShadowAngle`), kept here
     * as provenance — no independent derivation.
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
     * Accepts any finite value >= 0 (no 8848 cap): pin-verbatim, the cap lives in CalculationParameters per PRD.
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
