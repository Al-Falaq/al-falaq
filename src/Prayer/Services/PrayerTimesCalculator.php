<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Prayer\Services;

use AlFalaq\AlFalaq\Astro\Services\MeanSolarCalculator;
use AlFalaq\AlFalaq\Prayer\Contracts\HighLatitudeCorrection;
use AlFalaq\AlFalaq\Prayer\Contracts\PolarCircleHandler;
use AlFalaq\AlFalaq\Prayer\Contracts\PrayerTimesCalculatorInterface;
use AlFalaq\AlFalaq\Prayer\Contracts\TwilightModel;
use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Enums\HighLatitudeRule;
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
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;
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
     * Mean-sun day engine (@internal detail, not policy; ctor signature unchanged).
     *
     * @var MeanSolarCalculator Day-level solar geometry.
     */
    private readonly MeanSolarCalculator $solar;

    /**
     * Wire the three behavioral strategies explicitly (no container).
     *
     * @param HighLatitudeCorrection $highLatitudeCorrection Night splitting above ~48° latitude.
     * @param PolarCircleHandler $polarCircleHandler Polar day/night fallback.
     * @param TwilightModel $twilightModel Seasonal angles for MSC method.
     */
    public function __construct(
        private readonly HighLatitudeCorrection $highLatitudeCorrection,
        private readonly PolarCircleHandler $polarCircleHandler,
        private readonly TwilightModel $twilightModel,
    ) {
        $this->solar = new MeanSolarCalculator();
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

        return new self($hl, $polar, $twilight);
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

        if ($parameters->isRamadan && $parameters->method !== CalculationMethod::UMM_AL_QURA) {
            throw new ConfigurationException('isRamadan requires UMM_AL_QURA.');
        }

        $elev = $parameters->elevationM;
        $effDate = $date;
        $effCoords = $coords;

        if (! $this->solar->isSolarDefined($effCoords, $effDate, $elev)) {
            if ($parameters->polarCircleRule === PolarCircleRule::NONE) {
                throw UndefinedScheduleException::polar($effCoords, $effDate, 'none');
            }

            // Current PolarCircleHandler contract is resolve(Coordinates): Coordinates (T17
            // expands to resolve(Coordinates, CivilDate, callable): array{Coordinates, CivilDate}).
            // Call with 1 arg so PHPStan max stays clean; polar rows throw LogicException until T17.
            $effCoords = $this->polarCircleHandler->resolve($effCoords);

            if (! $this->solar->isSolarDefined($effCoords, $effDate, $elev)) {
                throw UndefinedScheduleException::polar($effCoords, $effDate, $parameters->polarCircleRule->value);
            }
        }

        $day = $this->solar->solarTime($effCoords, $effDate, $elev);
        $tomorrowSunrise = $this->solar->solarTime($effCoords, $effDate->plusDays(1), $elev)->sunrise;
        $nightSec = $tomorrowSunrise->getTimestamp() - $day->sunset->getTimestamp();

        // NONE skips the portions safe caps (raw angle temps); every other
        // rule resolves fallback portions eagerly. The SEASONAL_TWILIGHT arm
        // is wired to MiddleOfNightCorrection via defaultFor and only
        // consults these portions on the non-seasonal fallback path.
        $portions = match ($parameters->highLatitudeRule) {
            HighLatitudeRule::NONE => null,
            default => $this->highLatitudeCorrection->portions(
                $effCoords,
                $effDate,
                $parameters->fajrAngle,
                $parameters->ishaRule->angle(),
            ),
        };

        $eff = $parameters->methodAdjustments->combinedWith($parameters->adjustments);

        $isMsc = $parameters->method === CalculationMethod::MOON_SIGHTING_COMMITTEE;

        // Pin _set_fajr/_set_isha MSC branches (PrayerTimes.py verbatim): the
        // seasonal safe caps apply whenever the MSC method or the
        // SEASONAL_TWILIGHT rule arm is in play (the rule arm is
        // MSC-equivalent by design); the seventh-of-night temp replaces
        // the angle temp only at high latitude.
        // NOTE: the pin gates its temp branch on signed `latitude >= 55`
        // (_set_fajr and _set_isha); keep pin-verbatim here.
        $useSeasonalSafe = $isMsc || $parameters->highLatitudeRule === HighLatitudeRule::SEASONAL_TWILIGHT;
        $useSeventh = $useSeasonalSafe && $effCoords->latitude >= 55;

        $seasonalSeconds = ['morning' => 0, 'evening' => 0];
        $seventh = 0;

        if ($useSeasonalSafe) {
            $seasonalSeconds = $this->twilightModel->seasonalSeconds(
                $effDate->dayOfYear(),
                $effDate->year,
                $effCoords->latitude,
            );
            $seventh = intdiv($nightSec, 7);
        }

        if ($useSeasonalSafe) {
            $fajrSafe = $this->addSeconds($day->sunrise, -$seasonalSeconds['morning']);

            if ($useSeventh) {
                // Angle temp discarded (the pin overwrites it on this path).
                $fajrTemp = $this->addSeconds($day->sunrise, -$seventh);
            } else {
                $fajrTemp = $this->solar->hourAngle($effCoords, $effDate, $elev, -$parameters->fajrAngle, false);
            }

            if ($fajrTemp === null || $fajrTemp < $fajrSafe) {
                $fajrTemp = $fajrSafe;
            }
        } elseif ($portions === null) {
            // NONE rule: raw angle temp, no safe cap.
            $fajrTemp = $this->solar->hourAngle($effCoords, $effDate, $elev, -$parameters->fajrAngle, false);

            if ($fajrTemp === null) {
                throw new AstronomicalException(sprintf(
                    'Unable to compute Fajr: the sun never reaches the angle for these coordinates and date (coordinates=%s, date=%s).',
                    (string) $effCoords,
                    $effDate->isoformat(),
                ));
            }
        } else {
            $fajrTemp = $this->solar->hourAngle($effCoords, $effDate, $elev, -$parameters->fajrAngle, false);
            $fajrSafe = $this->addSeconds($day->sunrise, - (int) ($portions->fajrPortion * $nightSec));

            if ($fajrTemp === null || $fajrTemp < $fajrSafe) {
                $fajrTemp = $fajrSafe;
            }
        }

        $fajr = $this->adjusted($fajrTemp, $eff, Prayer::FAJR);
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

        $maghribMinutes = $parameters->maghribRule->minutesAfterSunset();

        if ($maghribMinutes !== null) {
            $maghribBase = Minutes::add($day->sunset, $maghribMinutes);
        } else {
            $maghribAngle = $parameters->maghribRule->angle();

            if ($maghribAngle === null) {
                throw new AstronomicalException(sprintf(
                    'Unable to compute Maghrib: no angle or interval defined (coordinates=%s, date=%s).',
                    (string) $effCoords,
                    $effDate->isoformat(),
                ));
            }

            $maghribTemp = $this->solar->hourAngle($effCoords, $effDate, $elev, -$maghribAngle, true);

            if ($maghribTemp === null) {
                throw new AstronomicalException(sprintf(
                    'Unable to compute Maghrib: the sun never reaches the angle for these coordinates and date (coordinates=%s, date=%s).',
                    (string) $effCoords,
                    $effDate->isoformat(),
                ));
            }

            $maghribBase = $maghribTemp;
        }

        $maghrib = $this->adjusted($maghribBase, $eff, Prayer::MAGHRIB);

        $ishaMinutes = $parameters->ishaRule->minutesAfterMaghrib();

        if ($parameters->isRamadan && $parameters->method === CalculationMethod::UMM_AL_QURA) {
            $ishaMinutes = 120;
        }

        if ($ishaMinutes !== null) {
            // Pin _set_isha: interval from raw sunset, not adjusted maghrib.
            $ishaBase = Minutes::add($day->sunset, $ishaMinutes);
            $isha = $this->adjusted($ishaBase, $eff, Prayer::ISHA);
        } else {
            $ishaAngle = $parameters->ishaRule->angle();

            if ($ishaAngle === null) {
                throw new AstronomicalException(sprintf(
                    'Unable to compute Isha: no angle or interval defined (coordinates=%s, date=%s).',
                    (string) $effCoords,
                    $effDate->isoformat(),
                ));
            }

            if ($useSeasonalSafe) {
                // Pin _set_isha MSC branch: seasonal evening safe; the
                // seventh-of-night temp replaces the angle temp only on the
                // high-latitude path, like fajr above.
                $ishaSafe = $this->addSeconds($day->sunset, $seasonalSeconds['evening']);

                if ($useSeventh) {
                    $ishaTemp = $this->addSeconds($day->sunset, $seventh);
                } else {
                    $ishaTemp = $this->solar->hourAngle($effCoords, $effDate, $elev, -$ishaAngle, true);
                }

                if ($ishaTemp === null || $ishaTemp > $ishaSafe) {
                    $ishaTemp = $ishaSafe;
                }
            } elseif ($portions === null) {
                // NONE rule: raw angle temp, no safe cap.
                $ishaTemp = $this->solar->hourAngle($effCoords, $effDate, $elev, -$ishaAngle, true);

                if ($ishaTemp === null) {
                    throw new AstronomicalException(sprintf(
                        'Unable to compute Isha: the sun never reaches the angle for these coordinates and date (coordinates=%s, date=%s).',
                        (string) $effCoords,
                        $effDate->isoformat(),
                    ));
                }
            } else {
                $ishaTemp = $this->solar->hourAngle($effCoords, $effDate, $elev, -$ishaAngle, true);
                $ishaSafe = $this->addSeconds($day->sunset, (int) ($portions->ishaPortion * $nightSec));

                if ($ishaTemp === null || $ishaTemp > $ishaSafe) {
                    $ishaTemp = $ishaSafe;
                }
            }

            $isha = $this->adjusted($ishaTemp, $eff, Prayer::ISHA);
        }

        if ($asr > $maghrib) {
            $asr = $maghrib;
        }

        return new PrayerSchedule($imsak, $fajr, $sunrise, $syuruk, $ishraq, $dhuha, $dhuhr, $asr, $maghrib, $isha);
    }

    private function adjusted(\DateTimeImmutable $base, PrayerAdjustments $eff, Prayer $prayer): \DateTimeImmutable
    {
        return Minutes::roundedHalfUp(Minutes::add($base, $eff->for($prayer)));
    }

    private function addSeconds(\DateTimeImmutable $base, int $seconds): \DateTimeImmutable
    {
        $moved = $base->modify(sprintf('%+d seconds', $seconds));

        if ($moved === false) {
            throw new ValidationException('Unable to add seconds.');
        }

        return $moved;
    }
}
