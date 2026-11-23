<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Moon\Services;

use AlFalaq\AlFalaq\Astro\Contracts\SolarDayEngine;
use AlFalaq\AlFalaq\Astro\Services\CrescentEphemeris;
use AlFalaq\AlFalaq\Astro\Services\DeltaTProvider;
use AlFalaq\AlFalaq\Astro\Services\MeanSolarCalculator;
use AlFalaq\AlFalaq\Astro\Traits\SharesSolarTheory;
use AlFalaq\AlFalaq\Moon\Contracts\ConjunctionSearch;
use AlFalaq\AlFalaq\Moon\Contracts\CrescentEvaluator;
use AlFalaq\AlFalaq\Moon\Strategies\Mabims1992Evaluator;
use AlFalaq\AlFalaq\Moon\Strategies\NeoMabims2021Evaluator;
use AlFalaq\AlFalaq\Moon\Strategies\OdehEvaluator;
use AlFalaq\AlFalaq\Moon\Strategies\YallopEvaluator;
use AlFalaq\AlFalaq\Moon\ValueObjects\CrescentGeometry;
use AlFalaq\AlFalaq\Moon\ValueObjects\CrescentEvaluationOutcome;
use AlFalaq\AlFalaq\Moon\ValueObjects\CrescentGeometryOutcome;
use AlFalaq\AlFalaq\Moon\ValueObjects\CrescentVerdict;
use AlFalaq\AlFalaq\Moon\ValueObjects\MoonCalculationMetadata;
use AlFalaq\AlFalaq\SharedKernel\Enums\CalculationContext;
use AlFalaq\AlFalaq\SharedKernel\Enums\WarningCode;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\AstronomicalException;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;
use AlFalaq\AlFalaq\SharedKernel\Traits\ResolvesCalculationSources;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CalculationMetadata;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CalculationSource;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CalculationWarning;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\JulianDay;

/**
 * Moon-sighting entry point: geometry at local sunset + criterion verdicts (M3).
 *
 * Core numeric orchestrator: atSunset() runs the Meeus pipeline on the Astro
 * engine, evaluate() fans the geometry out to the four injected criterion
 * strategies, and the metadata siblings share the same sunset() trace.
 * Strategies stay engine-free by construction.
 */
final class CrescentCalculator
{
    use SharesSolarTheory;
    use ResolvesCalculationSources;

    /**
     * Crescent semi-diameter factor: 0.27245 × parallax (pin line 252).
     *
     * Moon-age search bounds live on the search adapter with the search.
     */
    private const SEMI_DIAMETER_FACTOR = 0.27245;

    /**
     * Moon-age reporting floor in days (pin CrescentGeometry.py:39).
     *
     * Same pin value as the search adapter's floor: both cite the pin, which
     * stays the single source of truth.
     */
    private const MOON_AGE_MIN_DAYS = 0.1;

    /**
     * Wire the solar day engine, the lunar/solar ephemeris, the conjunction
     * search, and the four criterion strategies explicitly (no container).
     *
     * Strategies are typed to the shared CrescentEvaluator contract so
     * doubles can substitute in tests; default() wires the specified
     * criterion set in map order.
     *
     * @param SolarDayEngine $solar Day-level solar geometry (sunset instants).
     * @param CrescentEphemeris $ephemeris Sun/moon ephemeris + sidereal time.
     * @param ConjunctionSearch $conjunctionSearch Moon-age search + separation.
     * @param CrescentEvaluator $yallop Yallop q-criterion strategy.
     * @param CrescentEvaluator $odeh Odeh V-criterion strategy.
     * @param CrescentEvaluator $mabims1992 MABIMS-1992 strategy.
     * @param CrescentEvaluator $neoMabims2021 Neo-MABIMS-2021 strategy.
     */
    public function __construct(
        private readonly SolarDayEngine $solar,
        private readonly CrescentEphemeris $ephemeris,
        private readonly ConjunctionSearch $conjunctionSearch,
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
        $ephemeris = new CrescentEphemeris();

        return new self(
            new MeanSolarCalculator(),
            $ephemeris,
            new EphemerisConjunctionSearch($ephemeris),
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
     * @param float|null $deltaTOverride Seconds override for TT−UT1; null uses the provider.
     * @return CrescentGeometry Altitudes, elongation, ages, lag, azimuth delta.
     */
    public function atSunset(Coordinates $coordinates, CivilDate $date, ?float $deltaTOverride = null): CrescentGeometry
    {
        $warnings = [];

        return $this->sunset($coordinates, $date, $deltaTOverride, $warnings, false)['geometry'];
    }

    /**
     * Compute the geometry plus the provenance that explains how it was produced.
     *
     * Shares sunset() with atSunset(); the numeric path cannot drift.
     *
     * @param Coordinates $coordinates Observer location.
     * @param CivilDate $date Civil date of the evening observation.
     * @param float|null $deltaTOverride Seconds override for TT−UT1; null uses the provider.
     * @return CrescentGeometryOutcome Geometry plus provenance.
     */
    public function atSunsetWithMetadata(
        Coordinates $coordinates,
        CivilDate $date,
        ?float $deltaTOverride = null,
    ): CrescentGeometryOutcome {
        $warnings = [];
        $trace = $this->sunset($coordinates, $date, $deltaTOverride, $warnings, true);

        if ($trace['geometry']->lagMinutes === null) {
            $warnings[] = self::moonsetWarning($date);
        }

        return new CrescentGeometryOutcome(
            $trace['geometry'],
            new MoonCalculationMetadata(
                CalculationMetadata::create(CalculationContext::MOON, $warnings, $trace['sources']),
                $coordinates,
                $date,
                $trace['deltaTSeconds'],
                $trace['deltaTSource'],
                MoonCalculationMetadata::GEOMETRY_CALCULATED,
                [],
            ),
        );
    }

    /**
     * One numeric sunset run, shared by atSunset() and the metadata siblings.
     *
     * @param Coordinates $coordinates Observer location.
     * @param CivilDate $date Civil date of the evening observation.
     * @param float|null $deltaTOverride Seconds override for TT−UT1; null uses the provider.
     * @param list<CalculationWarning> $warnings Warning sink, appended in place.
     * @param bool $collectSources Resolve component identities for metadata; legacy paths pass false.
     * @return array{
     *     geometry: CrescentGeometry,
     *     deltaTSeconds: float,
     *     deltaTSource: CalculationSource,
     *     sources: list<CalculationSource>,
     * } Geometry plus the trace metadata needs.
     */
    private function sunset(
        Coordinates $coordinates,
        CivilDate $date,
        ?float $deltaTOverride,
        array &$warnings,
        bool $collectSources,
    ): array {
        if ($deltaTOverride !== null && ! is_finite($deltaTOverride)) {
            throw new ValidationException(sprintf(
                'deltaTOverride must be a finite number of seconds, got %s.',
                var_export($deltaTOverride, true),
            ));
        }

        $sunsetHours = $this->solar->hourAngleFloat($coordinates, $date, 0.0, self::SUNSET_DEPRESSION_DEG, true);

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

        if ($deltaTOverride !== null) {
            $deltaT = $deltaTOverride;
            $deltaTSource = CalculationSource::callerInput('caller.delta-t');
        } else {
            $deltaT = DeltaTProvider::forYear($decimalYear);
            $deltaTSource = CalculationSource::algorithm(DeltaTProvider::MODEL_ID, range: DeltaTProvider::MODEL_RANGE);

            if ($decimalYear < DeltaTProvider::VALID_FROM_YEAR || $decimalYear > DeltaTProvider::VALID_TO_YEAR) {
                $warnings[] = new CalculationWarning(
                    WarningCode::DELTA_T_EXTRAPOLATED,
                    sprintf(
                        'The ΔT provider is outside its calibrated range %s.',
                        DeltaTProvider::MODEL_RANGE,
                    ),
                    [
                        'year' => $decimalYear,
                        'calibrated_from_year' => DeltaTProvider::VALID_FROM_YEAR,
                        'calibrated_to_year' => DeltaTProvider::VALID_TO_YEAR,
                    ],
                );
            }
        }

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
        $arcl = $this->conjunctionSearch->separation($sunGeo['ra'], $sunGeo['dec'], $moonGeo['ra'], $moonGeo['dec']);

        $parallax = rad2deg(asin($this->clamp(
            CrescentEphemeris::PARALLAX_RADIUS_KM / max($moonGeo['distanceKm'], 1.0),
        )));
        $azimuthDiff = abs($moonTopo['azimuthDeg'] - $sunTopo['azimuthDeg']);
        $deltaAzimuth = min($azimuthDiff, 360.0 - $azimuthDiff);

        $semiDiameter = self::SEMI_DIAMETER_FACTOR * $parallax * 60.0;
        $width = $semiDiameter * (1.0 - cos(deg2rad($arcl)));
        $illumination = (1.0 - cos(deg2rad($arcl))) / 2.0;

        $moonsetJdUt = $this->ephemeris->moonsetJulianDay($jdMidnightUt, $deltaT, $coordinates);
        $ageDays = $this->conjunctionSearch->ageDays($jdSunsetTt);

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

        $sources = $collectSources
            ? [
                self::componentSource($this->solar, 'solar', $warnings),
                self::componentSource($this->ephemeris, 'ephemeris', $warnings),
                self::componentSource($this->conjunctionSearch, 'conjunction_search', $warnings),
                $deltaTSource,
            ]
            : [];

        return [
            'geometry' => new CrescentGeometry(
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
            ),
            'deltaTSeconds' => $deltaT,
            'deltaTSource' => $deltaTSource,
            'sources' => $sources,
        ];
    }

    /**
     * Build the no-moonset warning; the null date is caller-supplied geometry.
     *
     * @param CivilDate|null $date Requested civil date, when known.
     * @return CalculationWarning Machine-readable indeterminate explanation.
     */
    private static function moonsetWarning(?CivilDate $date): CalculationWarning
    {
        return new CalculationWarning(
            WarningCode::MOONSET_INDETERMINATE,
            'No moonset occurs on the requested civil date; moonset-dependent fields are null.',
            [
                'date' => $date?->isoformat(),
                'reason' => 'no_moonset_on_civil_date',
            ],
        );
    }

    /**
     * Run all four criteria on the sunset geometry (composition root).
     *
     * Strategies run on the same geometry bundle; the null (no-moonset)
     * window is owned by Mabims1992Evaluator as the alt/elongation-only
     * fallback, so every strategy is delegated to unconditionally.
     *
     * Delegates to evaluateGeometry().
     *
     * @param Coordinates $coordinates Observer location.
     * @param CivilDate $date Civil date of the evening observation.
     * @param float|null $deltaTOverride Seconds override for TT−UT1; null uses the provider.
     * @return array{yallop: CrescentVerdict, odeh: CrescentVerdict, mabims1992: CrescentVerdict, neomabims2021: CrescentVerdict} Verdicts keyed by criterion.
     */
    public function evaluate(Coordinates $coordinates, CivilDate $date, ?float $deltaTOverride = null): array
    {
        return $this->evaluateGeometry($this->atSunset($coordinates, $date, $deltaTOverride));
    }

    /**
     * Run all four criteria on an existing sunset geometry (pure scoring).
     *
     * Same composition as evaluate(), minus the ephemeris run.
     *
     * @param CrescentGeometry $geometry Sunset geometry, e.g. from atSunset().
     * @return array{yallop: CrescentVerdict, odeh: CrescentVerdict, mabims1992: CrescentVerdict, neomabims2021: CrescentVerdict} Verdicts keyed by criterion.
     */
    public function evaluateGeometry(CrescentGeometry $geometry): array
    {
        $yallop = $this->yallop->evaluate($geometry);
        $odeh = $this->odeh->evaluate($geometry);
        $mabims1992 = $this->mabims1992->evaluate($geometry);
        $neoMabims2021 = $this->neoMabims2021->evaluate($geometry);

        return [
            'yallop' => $yallop,
            'odeh' => $odeh,
            'mabims1992' => $mabims1992,
            'neomabims2021' => $neoMabims2021,
        ];
    }

    /**
     * Compute the geometry and run all four criteria, with provenance.
     *
     * Shares sunset() with atSunset(); it does not call atSunset() and then
     * recompute. The verdicts come from the same evaluateGeometry() fan-out.
     *
     * @param Coordinates $coordinates Observer location.
     * @param CivilDate $date Civil date of the evening observation.
     * @param float|null $deltaTOverride Seconds override for TT−UT1; null uses the provider.
     * @return CrescentEvaluationOutcome Geometry, verdicts, and provenance.
     */
    public function evaluateWithMetadata(
        Coordinates $coordinates,
        CivilDate $date,
        ?float $deltaTOverride = null,
    ): CrescentEvaluationOutcome {
        $warnings = [];
        $trace = $this->sunset($coordinates, $date, $deltaTOverride, $warnings, true);
        $verdicts = $this->evaluateGeometry($trace['geometry']);
        $sources = [...$trace['sources'], ...$this->criterionSources($warnings)];

        if ($trace['geometry']->lagMinutes === null) {
            $warnings[] = self::moonsetWarning($date);
        }

        $warnings[] = self::criterionWarning();

        return new CrescentEvaluationOutcome(
            $trace['geometry'],
            $verdicts,
            new MoonCalculationMetadata(
                CalculationMetadata::create(CalculationContext::MOON, $warnings, $sources),
                $coordinates,
                $date,
                $trace['deltaTSeconds'],
                $trace['deltaTSource'],
                MoonCalculationMetadata::GEOMETRY_CALCULATED,
                MoonCalculationMetadata::CRITERIA,
            ),
        );
    }

    /**
     * Run all four criteria on caller-supplied geometry, with provenance.
     *
     * The geometry bundle carries no coordinates, date, ΔT, or geometry
     * source, so those fields are null/caller_supplied rather than invented;
     * only the criterion sources are reported.
     *
     * @param CrescentGeometry $geometry Sunset geometry from the caller.
     * @return CrescentEvaluationOutcome Geometry, verdicts, and provenance.
     */
    public function evaluateGeometryWithMetadata(CrescentGeometry $geometry): CrescentEvaluationOutcome
    {
        $warnings = [];
        $sources = $this->criterionSources($warnings);

        if ($geometry->lagMinutes === null) {
            $warnings[] = self::moonsetWarning(null);
        }

        $warnings[] = self::criterionWarning();

        return new CrescentEvaluationOutcome(
            $geometry,
            $this->evaluateGeometry($geometry),
            new MoonCalculationMetadata(
                CalculationMetadata::create(CalculationContext::MOON, $warnings, $sources),
                null,
                null,
                null,
                null,
                MoonCalculationMetadata::GEOMETRY_CALLER_SUPPLIED,
                MoonCalculationMetadata::CRITERIA,
            ),
        );
    }

    /**
     * Criterion sources in evaluation order, warning on unidentified ones.
     *
     * @param list<CalculationWarning> $warnings Warning sink, appended in place.
     * @return list<CalculationSource> Criterion sources in evaluation order.
     */
    private function criterionSources(array &$warnings): array
    {
        return [
            self::componentSource($this->yallop, 'criterion', $warnings),
            self::componentSource($this->odeh, 'criterion', $warnings),
            self::componentSource($this->mabims1992, 'criterion', $warnings),
            self::componentSource($this->neoMabims2021, 'criterion', $warnings),
        ];
    }

    /**
     * Build the criterion-versus-observation warning.
     *
     * @return CalculationWarning Machine-readable decision-required explanation.
     */
    private static function criterionWarning(): CalculationWarning
    {
        return new CalculationWarning(
            WarningCode::CRESCENT_CRITERION_IS_NOT_OBSERVATION,
            'Crescent criteria are computed verdicts, not observations.',
            ['criteria' => implode(',', MoonCalculationMetadata::CRITERIA)],
        );
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

}
