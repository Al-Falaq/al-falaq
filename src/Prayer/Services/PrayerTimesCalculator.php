<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Prayer\Services;

use AlFalaq\AlFalaq\Astro\Contracts\SolarDayEngine;
use AlFalaq\AlFalaq\Prayer\Contracts\HighLatitudeCorrection;
use AlFalaq\AlFalaq\Prayer\Contracts\MaghribRule;
use AlFalaq\AlFalaq\Prayer\Contracts\PolarCircleHandler;
use AlFalaq\AlFalaq\Prayer\Contracts\PrayerTimesCalculatorInterface;
use AlFalaq\AlFalaq\Prayer\Contracts\TwilightModel;
use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Enums\HighLatitudeRule;
use AlFalaq\AlFalaq\Prayer\Enums\Madhab;
use AlFalaq\AlFalaq\Prayer\Enums\PolarCircleRule;
use AlFalaq\AlFalaq\Prayer\Enums\Prayer;
use AlFalaq\AlFalaq\Prayer\Exceptions\UndefinedScheduleException;
use AlFalaq\AlFalaq\Prayer\Strategies\MakkahHandler;
use AlFalaq\AlFalaq\Prayer\Strategies\MiddleOfNightCorrection;
use AlFalaq\AlFalaq\Prayer\Strategies\NearestDayHandler;
use AlFalaq\AlFalaq\Prayer\Strategies\NearestLatitudeHandler;
use AlFalaq\AlFalaq\Prayer\Strategies\SeasonAdjustedTwilight;
use AlFalaq\AlFalaq\Prayer\Strategies\SeventhOfNightCorrection;
use AlFalaq\AlFalaq\Prayer\Strategies\ThrowHandler;
use AlFalaq\AlFalaq\Prayer\Strategies\TwilightAngleCorrection;
use AlFalaq\AlFalaq\Prayer\Traits\NormalizesCoordinatesInput;
use AlFalaq\AlFalaq\Prayer\ValueObjects\CalculationParameters;
use AlFalaq\AlFalaq\Prayer\ValueObjects\PrayerAdjustments;
use AlFalaq\AlFalaq\Prayer\ValueObjects\PrayerSchedule;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\AstronomicalException;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ConfigurationException;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Minutes;

/**
 * Single-day prayer-time pipeline; strategies injected, no computation in ctor.
 *
 * Normal path plus the MSC seasonal branches (pin PrayerTimes._set_fajr /
 * _set_isha verbatim): seasonal safe caps on every MSC row, seventh-of-night
 * temps at high latitude.
 */
final class PrayerTimesCalculator implements PrayerTimesCalculatorInterface
{
    use NormalizesCoordinatesInput;

    /**
     * Night-temp resolution, composed from the injected strategies (no policy of its own).
     */
    private readonly NightResolver $night;

    /**
     * Wire the day engine plus the three behavioral strategies explicitly (no container).
     *
     * @param SolarDayEngine $solar Day-level solar geometry (real engine or test double).
     * @param HighLatitudeCorrection $highLatitudeCorrection Night splitting above ~48° latitude.
     * @param PolarCircleHandler $polarCircleHandler Polar day/night fallback.
     * @param TwilightModel $twilightModel Seasonal angles for MSC method.
     */
    public function __construct(
        private readonly SolarDayEngine $solar,
        private readonly HighLatitudeCorrection $highLatitudeCorrection,
        private readonly PolarCircleHandler $polarCircleHandler,
        private readonly TwilightModel $twilightModel,
    ) {
        $this->night = new NightResolver($solar, $highLatitudeCorrection, $twilightModel);
    }

    /**
     * Expose the injected day engine (introspection/testing).
     *
     * @return SolarDayEngine The wired solar geometry.
     */
    public function solar(): SolarDayEngine
    {
        return $this->solar;
    }

    /**
     * Expose the night-temp resolver (introspection/testing).
     *
     * @return NightResolver The wired fajr/isha resolution step.
     */
    public function night(): NightResolver
    {
        return $this->night;
    }

    /**
     * Expose the injected high-latitude strategy (introspection/testing).
     *
     * @return HighLatitudeCorrection The wired correction.
     */
    public function highLatitudeCorrection(): HighLatitudeCorrection
    {
        return $this->highLatitudeCorrection;
    }

    /**
     * Maghrib instant without the full schedule (strict-config fallback).
     *
     * Runs the pipeline's own solar/maghrib computation and returns before
     * the Fajr/Isha correction phases, so strict high-latitude configs with
     * an undefined schedule still resolve maghrib where the sun sets. Throws
     * UndefinedScheduleException only when maghrib itself is undefined.
     * Class-only capability (like highLatitudeCorrection()); the interface
     * keeps the single calculate() command.
     *
     * The tomorrow-rise probe and the polar resolve are skipped by design,
     * not by omission: they gate the full schedule's night length (Fajr/Isha
     * portions), which maghrib never consumes. Maghrib needs only today's
     * sunset, so a polar-transition day (sun sets today, no sunrise
     * tomorrow) resolves here while calculate() still throws. Every call
     * below is the same private solar/maghrib call calculate() performs, in
     * the same order — no astronomy is reimplemented.
     *
     * @param Coordinates|array{0: mixed, 1: mixed} $coordinates VO or [lat, lon] pair.
     * @param CivilDate $date Civil date to compute.
     * @param CalculationParameters $parameters Method defaults + overrides.
     * @return \DateTimeImmutable Maghrib instant (UTC).
     */
    public function maghribAt(
        Coordinates|array $coordinates,
        CivilDate $date,
        CalculationParameters $parameters,
    ): \DateTimeImmutable {
        $coords = self::normalizeCoordinates($coordinates);
        $this->assertRamadanCompatible($parameters);

        try {
            $day = $this->solar->solarTime($coords, $date, $parameters->elevationM);
        } catch (AstronomicalException) {
            throw UndefinedScheduleException::polar($coords, $date, $parameters->polarCircleRule->value);
        }

        $eff = $parameters->methodAdjustments->combinedWith($parameters->adjustments);
        $maghribBase = $this->maghribBase($coords, $date, $parameters->elevationM, $parameters->maghribRule, $day->sunset);

        return $this->adjusted($maghribBase, $eff, Prayer::MAGHRIB);
    }

    /**
     * Expose the injected polar-circle strategy (introspection/testing).
     *
     * @return PolarCircleHandler The wired handler.
     */
    public function polarCircleHandler(): PolarCircleHandler
    {
        return $this->polarCircleHandler;
    }

    /**
     * Expose the injected twilight strategy (introspection/testing).
     *
     * @return TwilightModel The wired model.
     */
    public function twilightModel(): TwilightModel
    {
        return $this->twilightModel;
    }

    /**
     * Recommended wiring: middle-of-night + nearest-latitude + seasonal twilight.
     *
     * @return self Ready calculator covering 95% of use cases.
     */
    public static function default(): self
    {
        return new self(
            new \AlFalaq\AlFalaq\Astro\Services\MeanSolarCalculator(),
            new \AlFalaq\AlFalaq\Prayer\Strategies\MiddleOfNightCorrection(),
            new \AlFalaq\AlFalaq\Prayer\Strategies\NearestLatitudeHandler(),
            new \AlFalaq\AlFalaq\Prayer\Strategies\SeasonAdjustedTwilight(),
        );
    }

    /**
     * Method-aware wiring: select strategies from the parameter rules.
     *
     * @param CalculationParameters $parameters Resolved method parameters selecting the strategies.
     * @return self Calculator wired for the given rules.
     */
    public static function defaultFor(CalculationParameters $parameters): self
    {
        $hl = match ($parameters->highLatitudeRule) {
            HighLatitudeRule::MIDDLE_OF_NIGHT => new MiddleOfNightCorrection(),
            HighLatitudeRule::SEVENTH_OF_NIGHT => new SeventhOfNightCorrection(),
            HighLatitudeRule::TWILIGHT_ANGLE => new TwilightAngleCorrection(),
            HighLatitudeRule::SEASONAL_TWILIGHT,
            HighLatitudeRule::NONE => new MiddleOfNightCorrection(),
        };

        $polar = match ($parameters->polarCircleRule) {
            PolarCircleRule::NEAREST_LATITUDE => new NearestLatitudeHandler(),
            PolarCircleRule::NEAREST_DAY => new NearestDayHandler(),
            PolarCircleRule::MAKKAH => new MakkahHandler(),
            PolarCircleRule::NONE => new ThrowHandler(),
        };

        // Consulted on the MSC seasonal branches in calculate().
        $twilight = new SeasonAdjustedTwilight();

        return new self(
            new \AlFalaq\AlFalaq\Astro\Services\MeanSolarCalculator(),
            $hl,
            $polar,
            $twilight,
        );
    }

    /**
     * Compute all ten prayer instants for one date and place (UTC).
     *
     * Mirrors pin PrayerTimes.__init__ + _set_* UTC pipeline; round = Minutes::roundedHalfUp
     * AFTER adding effective offsets. Syuruk is a sunrise alias and never reads
     * Prayer::SYURUK adjustments (mirrors pin PrayerAdjustments having no syuruk slot).
     *
     * @param Coordinates|array{0: mixed, 1: mixed} $coordinates VO or [lat, lon] pair.
     * @param CivilDate $date Civil date to compute.
     * @param CalculationParameters $parameters Method defaults + overrides.
     * @return PrayerSchedule All instants in UTC; display via forZone().
     */
    public function calculate(Coordinates|array $coordinates, CivilDate $date, CalculationParameters $parameters): PrayerSchedule
    {
        $coords = self::normalizeCoordinates($coordinates);
        $this->assertRamadanCompatible($parameters);

        $elev = $parameters->elevationM;
        $effDate = $date;
        $effCoords = $coords;

        if (! $this->solar->isSolarDefined($effCoords, $effDate, $elev)) {
            // NONE short-circuit duplicates ThrowHandler on purpose: the handler serves
            // explicit-wiring/direct-call users while the calculator fails fast here.
            if ($parameters->polarCircleRule === PolarCircleRule::NONE) {
                throw UndefinedScheduleException::polar($effCoords, $effDate, 'none');
            }

            $probe = fn(Coordinates $c, CivilDate $d): bool => $this->solar->isSolarDefined($c, $d, $elev);
            $resolved = $this->polarCircleHandler->resolve($coords, $date, $probe);
            $effCoords = $resolved['coordinates'];
            $effDate = $resolved['date'];

            if (! $this->solar->isSolarDefined($effCoords, $effDate, $elev)) {
                throw UndefinedScheduleException::polar($effCoords, $effDate, $parameters->polarCircleRule->value);
            }
        }

        $day = $this->solar->solarTime($effCoords, $effDate, $elev);
        $tomorrowSunrise = $this->solar->solarTime($effCoords, $effDate->plusDays(1), $elev)->sunrise;
        $nightSec = $tomorrowSunrise->getTimestamp() - $day->sunset->getTimestamp();

        $eff = $parameters->methodAdjustments->combinedWith($parameters->adjustments);

        $temps = $this->night->resolve($effCoords, $effDate, $elev, $parameters, $day, $nightSec);

        $fajr = $this->adjusted($temps['fajr'], $eff, Prayer::FAJR);
        $imsak = $this->adjusted(Minutes::add($fajr, -$parameters->imsakOffsetMinutes), $eff, Prayer::IMSAK);

        $sunrise = $this->adjusted($day->sunrise, $eff, Prayer::SUNRISE);
        $syuruk = $sunrise;
        $ishraq = $this->adjusted(Minutes::add($sunrise, $parameters->ishraqOffsetMinutes), $eff, Prayer::ISHRAQ);
        $dhuha = $this->adjusted(Minutes::add($sunrise, $parameters->dhuhaOffsetMinutes), $eff, Prayer::DHUHA);
        $dhuhr = $this->adjusted(Minutes::add($day->transit, $parameters->dhuhrOffsetMinutes), $eff, Prayer::DHUHR);

        $asrTemp = $this->solar->afternoon($effCoords, $effDate, $elev, $parameters->madhab->shadowLength());

        if ($asrTemp === null) {
            throw new AstronomicalException(sprintf(
                'Unable to compute Asr: the afternoon shadow length is undefined for these coordinates and date (coordinates=%s, date=%s).',
                (string) $effCoords,
                $effDate->isoformat(),
            ));
        }

        $asr = $this->adjusted($asrTemp, $eff, Prayer::ASR);

        if ($asr < $dhuhr) {
            $asr = $dhuhr;
        }

        $maghribBase = $this->maghribBase($effCoords, $effDate, $elev, $parameters->maghribRule, $day->sunset);

        $maghrib = $this->adjusted($maghribBase, $eff, Prayer::MAGHRIB);

        $isha = $this->adjusted($temps['isha'], $eff, Prayer::ISHA);

        if ($asr > $maghrib) {
            $asr = $maghrib;
        }

        return new PrayerSchedule($imsak, $fajr, $sunrise, $syuruk, $ishraq, $dhuha, $dhuhr, $asr, $maghrib, $isha);
    }

    private function adjusted(\DateTimeImmutable $base, PrayerAdjustments $eff, Prayer $prayer): \DateTimeImmutable
    {
        return Minutes::roundedHalfUp(Minutes::add($base, $eff->for($prayer)));
    }

    /**
     * Shared maghrib resolution for calculate() and maghribAt() (single owner).
     *
     * Callers pass their own resolved coordinates, date, and sunset: calculate()
     * passes the polar-resolved pair, maghribAt() the raw pair (it skips the
     * polar resolve by design — maghrib never consumes night length).
     *
     * @param Coordinates $coords Resolved observer location.
     * @param CivilDate $date Resolved civil date.
     * @param mixed $elevationM Observer height in metres.
     * @param MaghribRule $rule Angle or interval rule.
     * @param \DateTimeImmutable $sunset Sunset instant from the day engine.
     * @return \DateTimeImmutable Unadjusted maghrib instant (UTC).
     */
    private function maghribBase(Coordinates $coords, CivilDate $date, mixed $elevationM, MaghribRule $rule, \DateTimeImmutable $sunset): \DateTimeImmutable
    {
        $maghribMinutes = $rule->minutesAfterSunset();

        if ($maghribMinutes !== null) {
            return Minutes::add($sunset, $maghribMinutes);
        }

        $maghribAngle = $rule->angle();

        if ($maghribAngle === null) {
            throw new AstronomicalException(sprintf(
                'Unable to compute Maghrib: no angle or interval defined (coordinates=%s, date=%s).',
                (string) $coords,
                $date->isoformat(),
            ));
        }

        $maghribTemp = $this->solar->hourAngle($coords, $date, $elevationM, -$maghribAngle, true);

        if ($maghribTemp === null) {
            throw new AstronomicalException(sprintf(
                'Unable to compute Maghrib: the sun never reaches the angle for these coordinates and date (coordinates=%s, date=%s).',
                (string) $coords,
                $date->isoformat(),
            ));
        }

        return $maghribTemp;
    }

    /**
     * Guard Umm al-Qura Ramadan mode at pipeline entry.
     *
     * @param CalculationParameters $parameters Method defaults + overrides.
     * @return void
     */
    private function assertRamadanCompatible(CalculationParameters $parameters): void
    {
        if ($parameters->isRamadan && $parameters->method !== CalculationMethod::UMM_AL_QURA) {
            throw new ConfigurationException(sprintf(
                'isRamadan requires UMM_AL_QURA (got %s).',
                $parameters->method->value,
            ));
        }
    }
}
