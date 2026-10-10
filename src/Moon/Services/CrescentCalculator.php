<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Moon\Services;

use AlFalaq\AlFalaq\Astro\Services\CrescentEphemeris;
use AlFalaq\AlFalaq\Astro\Services\DeltaTProvider;
use AlFalaq\AlFalaq\Astro\Services\MeanSolarCalculator;
use AlFalaq\AlFalaq\Moon\Contracts\CrescentEvaluator;
use AlFalaq\AlFalaq\Moon\Strategies\Mabims1992Evaluator;
use AlFalaq\AlFalaq\Moon\Strategies\NeoMabims2021Evaluator;
use AlFalaq\AlFalaq\Moon\Strategies\OdehEvaluator;
use AlFalaq\AlFalaq\Moon\Strategies\YallopEvaluator;
use AlFalaq\AlFalaq\Moon\ValueObjects\CrescentGeometry;
use AlFalaq\AlFalaq\Moon\ValueObjects\CrescentVerdict;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\AstronomicalException;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\JulianDay;

/**
 * Moon-sighting entry point: geometry at local sunset + criterion verdicts (M3).
 *
 * Two-method orchestrator (spec §3.2): atSunset() runs the Meeus pipeline on
 * the Astro engine, evaluate() fans the geometry out to the four injected
 * criterion strategies. Strategies stay engine-free by construction.
 */
final class CrescentCalculator
{
    /**
     * Sunset depression for rise/set in degrees (pin CrescentGeometry.py:36).
     */
    private const SUNSET_ALTITUDE_DEG = -50.0 / 60.0;

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
     * Crescent semi-diameter factor: 0.27245 × parallax (pin line 252).
     */
    private const SEMI_DIAMETER_FACTOR = 0.27245;

    /**
     * Wire the solar day engine, the lunar/solar ephemeris, and the four criterion strategies explicitly (no container).
     *
     * Strategies are typed to the shared CrescentEvaluator contract so
     * doubles can substitute in tests; default() wires the specified
     * criterion set in map order.
     *
     * @param MeanSolarCalculator $solar Day-level solar geometry (sunset instants).
     * @param CrescentEphemeris $ephemeris Sun/moon ephemeris + sidereal time.
     * @param CrescentEvaluator $yallop Yallop q-criterion strategy.
     * @param CrescentEvaluator $odeh Odeh V-criterion strategy.
     * @param CrescentEvaluator $mabims1992 MABIMS-1992 strategy.
     * @param CrescentEvaluator $neoMabims2021 Neo-MABIMS-2021 strategy.
     */
    public function __construct(
        private readonly MeanSolarCalculator $solar,
        private readonly CrescentEphemeris $ephemeris,
        private readonly CrescentEvaluator $yallop,
        private readonly CrescentEvaluator $odeh,
        private readonly CrescentEvaluator $mabims1992,
        private readonly CrescentEvaluator $neoMabims2021,
    ) {}

    /**
     * Recommended wiring: mean-sun days + Meeus ephemeris + the four criteria.
     *
     * @return self Ready calculator covering the moon-sighting path.
     */
    public static function default(): self
    {
        return new self(
            new MeanSolarCalculator(),
            new CrescentEphemeris(),
            new YallopEvaluator(),
            new OdehEvaluator(),
            new Mabims1992Evaluator(),
            new NeoMabims2021Evaluator(),
        );
    }

    /**
     * Compute sun/moon geometry at sunset for one place and date (M3).
     *
     * Pin a7baa8b crescent_geometry_at_sunset verbatim: fractional-hour
     * sunset from the solar day engine (sea level, no refraction beyond
     * the depression — sub-second precision, never floored to whole
     * seconds), JD anchored on the UTC sunset instant with TT for the
     * ephemeris and UT for sidereal time, parallax-corrected topocentric
     * moon against the geocentric sun (the pin applies parallax to the
     * moon only), ARCL from geocentric RA/dec separation, analytic
     * (Meeus Ch.15) moonset, 30-day hourly ARCL-minimum age search.
     * Topocentric ARCV is NOT stored — consumers derive it as
     * moonAltitude − sunAltitude.
     *
     * @param Coordinates $coordinates Observer location.
     * @param CivilDate $date Civil date of the evening observation.
     * @return CrescentGeometry Altitudes, elongation, ages, lag, azimuth delta.
     */
    public function atSunset(Coordinates $coordinates, CivilDate $date): CrescentGeometry
    {
        $sunsetHours = $this->solar->hourAngleFloat($coordinates, $date, 0.0, self::SUNSET_ALTITUDE_DEG, true);

        if ($sunsetHours === null) {
            throw new AstronomicalException(sprintf(
                'Unable to compute crescent geometry: the sun never sets for these coordinates and date (polar night). '
                . 'coordinates=%s, date=%s.',
                (string) $coordinates,
                $date->isoformat(),
            ));
        }

        $sunsetSeconds = $sunsetHours * 3600.0;
        $jdMidnightUt = JulianDay::fromCivilDate($date)->toFloat();
        $jdSunsetUt = $jdMidnightUt + $sunsetHours / 24.0;
        $decimalYear = (float) $date->year + ($date->dayOfYear() - 1) / 365.25;
        $deltaT = DeltaTProvider::forYear($decimalYear);
        $jdSunsetTt = $jdSunsetUt + $deltaT / 86400.0;

        $lst = $this->ephemeris->siderealTime($jdSunsetUt, $coordinates->longitude);
        $sunGeo = $this->ephemeris->solarGeocentric($jdSunsetTt);
        $moonGeo = $this->ephemeris->lunarGeocentric($jdSunsetTt);
        $sunTopo = $this->ephemeris->topocentric(
            $sunGeo['ra'],
            $sunGeo['dec'],
            $sunGeo['distanceKm'],
            $coordinates,
            $lst,
        );
        $moonTopo = $this->ephemeris->topocentric(
            $moonGeo['ra'],
            $moonGeo['dec'],
            $moonGeo['distanceKm'],
            $coordinates,
            $lst,
        );

        $sunAltitude = $this->geocentricAltitude(
            $coordinates->latitude,
            $lst,
            $sunGeo['ra'],
            $sunGeo['dec'],
        );
        $moonAltitude = $moonTopo['altitudeDeg'];
        $moonGeoAltitude = $this->geocentricAltitude(
            $coordinates->latitude,
            $lst,
            $moonGeo['ra'],
            $moonGeo['dec'],
        );
        $arcl = $this->angularSeparation($sunGeo['ra'], $sunGeo['dec'], $moonGeo['ra'], $moonGeo['dec']);

        $parallax = rad2deg(asin($this->clamp(
            CrescentEphemeris::PARALLAX_RADIUS_KM / max($moonGeo['distanceKm'], 1.0),
        )));
        $azimuthDiff = abs($moonTopo['azimuthDeg'] - $sunTopo['azimuthDeg']);
        $deltaAzimuth = min($azimuthDiff, 360.0 - $azimuthDiff);

        $semiDiameter = self::SEMI_DIAMETER_FACTOR * $parallax * 60.0;
        $width = $semiDiameter * (1.0 - cos(deg2rad($arcl)));
        $illumination = (1.0 - cos(deg2rad($arcl))) / 2.0;

        $moonsetJdUt = $this->ephemeris->moonsetJulianDay($jdMidnightUt, $deltaT, $coordinates);
        $ageDays = $this->moonAgeDays($jdSunsetTt);

        if (is_nan($moonsetJdUt)) {
            // No moonset on this civil date (e.g. circumpolar Moon): no
            // sunset-to-moonset window, and the MABIMS age-at-moonset is
            // undefined (null fails loud in the strategy, never silently).
            $lagMinutes = null;
            $ageAtMoonsetHours = null;
        } else {
            // Age is a time difference from a fixed conjunction instant, so
            // the moonset age is exactly the sunset age plus the lag, with
            // the same 0.1-day reporting floor as the sunset age (pin).
            $lagMinutes = (($moonsetJdUt - $jdMidnightUt) * 86400.0 - $sunsetSeconds) / 60.0;
            $ageAtMoonsetHours = max(self::MOON_AGE_MIN_DAYS, $ageDays + $lagMinutes / 60.0 / 24.0) * 24.0;
        }

        return new CrescentGeometry(
            $sunAltitude,
            $moonAltitude,
            $arcl,
            $ageDays * 24.0,
            $lagMinutes,
            $deltaAzimuth,
            $illumination,
            $moonGeoAltitude - $sunAltitude,
            $width,
            $ageAtMoonsetHours,
        );
    }

    /**
     * Run all four criteria on the sunset geometry (composition root).
     *
     * Strategies run on the same geometry bundle; the null (no-moonset)
     * window never reaches Mabims1992Evaluator (it throws on null age) and
     * instead takes the alt/elongation-only fallback here per spec §3.2.
     *
     * Delegates to evaluateGeometry().
     *
     * @param Coordinates $coordinates Observer location.
     * @param CivilDate $date Civil date of the evening observation.
     * @return array{yallop: CrescentVerdict, odeh: CrescentVerdict, mabims1992: CrescentVerdict, neomabims2021: CrescentVerdict} Verdicts keyed by criterion.
     */
    public function evaluate(Coordinates $coordinates, CivilDate $date): array
    {
        return $this->evaluateGeometry($this->atSunset($coordinates, $date));
    }

    /**
     * Run all four criteria on an existing sunset geometry (pure scoring).
     *
     * Same composition as evaluate(), minus the ephemeris run: the null
     * (no-moonset) window takes the alt/elongation-only 1992 fallback per
     * spec §3.2 instead of reaching Mabims1992Evaluator.
     *
     * @param CrescentGeometry $geometry Sunset geometry, e.g. from atSunset().
     * @return array{yallop: CrescentVerdict, odeh: CrescentVerdict, mabims1992: CrescentVerdict, neomabims2021: CrescentVerdict} Verdicts keyed by criterion.
     */
    public function evaluateGeometry(CrescentGeometry $geometry): array
    {
        $yallop = $this->yallop->evaluate($geometry);
        $odeh = $this->odeh->evaluate($geometry);

        if ($geometry->lagMinutes === null || $geometry->ageAtMoonsetHours === null) {
            // Composition-root fallback (spec §3.2): the 1992 age branch is
            // undefined without a moonset, so the alt/elongation half of the
            // rule is intentionally duplicated here instead of catching the
            // strategy's null-window rejection.
            $visible = $geometry->moonAltitude >= 2.0 && $geometry->elongationDeg >= 3.0;
            $mabims1992 = new CrescentVerdict(
                'mabims1992',
                $visible,
                $geometry->elongationDeg,
                $visible ? 'visible' : 'not-visible',
            );
        } else {
            $mabims1992 = $this->mabims1992->evaluate($geometry);
        }

        $neoMabims2021 = $this->neoMabims2021->evaluate($geometry);

        return [
            'yallop' => $yallop,
            'odeh' => $odeh,
            'mabims1992' => $mabims1992,
            'neomabims2021' => $neoMabims2021,
        ];
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
    private function moonAgeDays(float $julianDayTt): float
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
     * Geocentric elongation (ARCL) at one TT instant.
     *
     * @param float $julianDayTt Terrestrial-time Julian day.
     * @return float Sun–moon separation in degrees.
     */
    private function arclAt(float $julianDayTt): float
    {
        $sun = $this->ephemeris->solarGeocentric($julianDayTt);
        $moon = $this->ephemeris->lunarGeocentric($julianDayTt);

        return $this->angularSeparation($sun['ra'], $sun['dec'], $moon['ra'], $moon['dec']);
    }

    /**
     * Geocentric altitude for equatorial (ra, dec) at LST (pin _altaz).
     *
     * @param float $latitudeDeg Observer latitude in degrees.
     * @param float $lstHours Local sidereal time in hours.
     * @param float $raDeg Geocentric right ascension in degrees.
     * @param float $decDeg Geocentric declination in degrees.
     * @return float Altitude in degrees.
     */
    private function geocentricAltitude(float $latitudeDeg, float $lstHours, float $raDeg, float $decDeg): float
    {
        $hourAngle = deg2rad($lstHours * 15.0 - $raDeg);
        $latitude = deg2rad($latitudeDeg);
        $declination = deg2rad($decDeg);
        $sinAlt = sin($latitude) * sin($declination)
            + cos($latitude) * cos($declination) * cos($hourAngle);

        return rad2deg(asin($this->clamp($sinAlt)));
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
    private function angularSeparation(float $ra1Deg, float $dec1Deg, float $ra2Deg, float $dec2Deg): float
    {
        $cosSep = sin(deg2rad($dec1Deg)) * sin(deg2rad($dec2Deg))
            + cos(deg2rad($dec1Deg)) * cos(deg2rad($dec2Deg)) * cos(deg2rad($ra2Deg - $ra1Deg));

        return rad2deg(acos($this->clamp($cosSep)));
    }

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
}
