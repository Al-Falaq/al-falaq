<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Prayer\Services;

use AlFalaq\AlFalaq\Astro\Contracts\SolarDayEngine;
use AlFalaq\AlFalaq\Astro\ValueObjects\SolarTime;
use AlFalaq\AlFalaq\Prayer\Contracts\HighLatitudeCorrection;
use AlFalaq\AlFalaq\Prayer\Contracts\TwilightModel;
use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Enums\HighLatitudeRule;
use AlFalaq\AlFalaq\Prayer\ValueObjects\CalculationParameters;
use AlFalaq\AlFalaq\Prayer\ValueObjects\NightPortions;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\AstronomicalException;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Minutes;

/**
 * Fajr/Isha temp-vs-safe resolution (single owner, internal step).
 *
 * Composed inside PrayerTimesCalculator from its injected strategies — not a
 * variation seam (no second implementation foreseeable), just the extracted
 * night logic both markers share: mode selection (seasonal / NONE-raw /
 * portions) plus the two mirrored temp-vs-safe-cap branches. Pin
 * PrayerTimes._set_fajr/_set_isha verbatim; mode comments move with the code.
 */
final class NightResolver
{
    /**
     * Wire the day engine plus the night-behavior strategies explicitly.
     *
     * @param SolarDayEngine $solar Day-level solar geometry (real engine or test double).
     * @param HighLatitudeCorrection $highLatitudeCorrection Night splitting above ~48° latitude.
     * @param TwilightModel $twilightModel Seasonal angles for MSC method.
     */
    public function __construct(
        private readonly SolarDayEngine $solar,
        private readonly HighLatitudeCorrection $highLatitudeCorrection,
        private readonly TwilightModel $twilightModel,
    ) {}

    /**
     * Resolve unadjusted Fajr and Isha instants for one date and place (UTC).
     *
     * @param Coordinates $coords Resolved observer location.
     * @param CivilDate $date Resolved civil date.
     * @param mixed $elevationM Observer height in metres.
     * @param CalculationParameters $parameters Method defaults + overrides.
     * @param SolarTime $day Solar day from the engine.
     * @param int $nightSec Sunset-to-next-sunrise seconds.
     * @return array{fajr: \DateTimeImmutable, isha: \DateTimeImmutable} Unadjusted temps in UTC.
     */
    public function resolve(
        Coordinates $coords,
        CivilDate $date,
        mixed $elevationM,
        CalculationParameters $parameters,
        SolarTime $day,
        int $nightSec,
    ): array {
        $isMsc = $parameters->method === CalculationMethod::MOON_SIGHTING_COMMITTEE;

        // Pin _set_fajr/_set_isha MSC branches (PrayerTimes.py verbatim): the
        // seasonal safe caps apply whenever the MSC method or the
        // SEASONAL_TWILIGHT rule arm is in play (the rule arm is
        // MSC-equivalent by design); the seventh-of-night temp replaces
        // the angle temp only at high latitude.
        // NOTE: the pin gates its temp branch on signed `latitude >= 55`
        // (_set_fajr and _set_isha); keep pin-verbatim here.
        $useSeasonalSafe = $isMsc || $parameters->highLatitudeRule === HighLatitudeRule::SEASONAL_TWILIGHT;
        $useSeventh = $useSeasonalSafe && $coords->latitude >= 55;

        // Three modes: seasonal safe caps (MSC or SEASONAL_TWILIGHT),
        // NONE-raw (no cap), or fallback portions. The seasonal arm never
        // reads portions, so resolve them only for the portions mode.
        $portions = $useSeasonalSafe || $parameters->highLatitudeRule === HighLatitudeRule::NONE
            ? null
            : $this->highLatitudeCorrection->portions(
                $parameters->fajrAngle,
                $parameters->ishaRule->angle(),
            );

        $seasonalSeconds = ['morning' => 0, 'evening' => 0];
        $seventh = 0;

        if ($useSeasonalSafe) {
            // Twilight model consulted only on the seasonal path.
            $seasonalSeconds = $this->twilightModel->seasonalSeconds(
                $date->dayOfYear(),
                $date->year,
                $coords->latitude,
            );
            $seventh = intdiv($nightSec, 7);
        }

        $fajrTemp = $this->fajrTemp($coords, $date, $elevationM, $parameters, $day, $nightSec, $portions, $useSeasonalSafe, $useSeventh, $seasonalSeconds, $seventh);
        $ishaTemp = $this->ishaTemp($coords, $date, $elevationM, $parameters, $day, $nightSec, $portions, $useSeasonalSafe, $useSeventh, $seasonalSeconds, $seventh);

        return ['fajr' => $fajrTemp, 'isha' => $ishaTemp];
    }

    /**
     * Fajr temp for one mode (seasonal / NONE-raw / portions).
     *
     * @param Coordinates $coords Resolved observer location.
     * @param CivilDate $date Resolved civil date.
     * @param mixed $elevationM Observer height in metres.
     * @param CalculationParameters $parameters Method defaults + overrides.
     * @param SolarTime $day Solar day from the engine.
     * @param int $nightSec Sunset-to-next-sunrise seconds.
     * @param NightPortions|null $portions Fallback portions; null for NONE or
     *     the seasonal safe-cap path (never read there).
     * @param bool $useSeasonalSafe MSC method or SEASONAL_TWILIGHT arm.
     * @param bool $useSeventh High-latitude seventh-of-night path.
     * @param array{morning: int, evening: int} $seasonalSeconds Seasonal caps in seconds.
     * @param int $seventh Seventh-of-night seconds.
     * @return \DateTimeImmutable Unadjusted Fajr temp in UTC.
     */
    private function fajrTemp(
        Coordinates $coords,
        CivilDate $date,
        mixed $elevationM,
        CalculationParameters $parameters,
        SolarTime $day,
        int $nightSec,
        ?NightPortions $portions,
        bool $useSeasonalSafe,
        bool $useSeventh,
        array $seasonalSeconds,
        int $seventh,
    ): \DateTimeImmutable {
        if ($useSeasonalSafe) {
            $fajrSafe = $this->addSeconds($day->sunrise, -$seasonalSeconds['morning']);

            if ($useSeventh) {
                // Angle temp discarded (the pin overwrites it on this path).
                $fajrTemp = $this->addSeconds($day->sunrise, -$seventh);
            } else {
                $fajrTemp = $this->solar->hourAngle($coords, $date, $elevationM, -$parameters->fajrAngle, false);
            }

            if ($fajrTemp === null || $fajrTemp < $fajrSafe) {
                $fajrTemp = $fajrSafe;
            }
        } elseif ($portions === null) {
            // NONE rule: raw angle temp, no safe cap.
            $fajrTemp = $this->solar->hourAngle($coords, $date, $elevationM, -$parameters->fajrAngle, false);

            if ($fajrTemp === null) {
                throw new AstronomicalException(sprintf(
                    'Unable to compute Fajr: the sun never reaches the angle for these coordinates and date (coordinates=%s, date=%s).',
                    (string) $coords,
                    $date->isoformat(),
                ));
            }
        } else {
            $fajrTemp = $this->solar->hourAngle($coords, $date, $elevationM, -$parameters->fajrAngle, false);
            $fajrSafe = $this->addSeconds($day->sunrise, - (int) ($portions->fajrPortion * $nightSec));

            if ($fajrTemp === null || $fajrTemp < $fajrSafe) {
                $fajrTemp = $fajrSafe;
            }
        }

        return $fajrTemp;
    }

    /**
     * Isha temp for one mode (interval / seasonal / NONE-raw / portions).
     *
     * @param Coordinates $coords Resolved observer location.
     * @param CivilDate $date Resolved civil date.
     * @param mixed $elevationM Observer height in metres.
     * @param CalculationParameters $parameters Method defaults + overrides.
     * @param SolarTime $day Solar day from the engine.
     * @param int $nightSec Sunset-to-next-sunrise seconds.
     * @param NightPortions|null $portions Fallback portions; null for NONE or
     *     the seasonal safe-cap path (never read there).
     * @param bool $useSeasonalSafe MSC method or SEASONAL_TWILIGHT arm.
     * @param bool $useSeventh High-latitude seventh-of-night path.
     * @param array{morning: int, evening: int} $seasonalSeconds Seasonal caps in seconds.
     * @param int $seventh Seventh-of-night seconds.
     * @return \DateTimeImmutable Unadjusted Isha temp in UTC.
     */
    private function ishaTemp(
        Coordinates $coords,
        CivilDate $date,
        mixed $elevationM,
        CalculationParameters $parameters,
        SolarTime $day,
        int $nightSec,
        ?NightPortions $portions,
        bool $useSeasonalSafe,
        bool $useSeventh,
        array $seasonalSeconds,
        int $seventh,
    ): \DateTimeImmutable {
        $ishaMinutes = $parameters->ishaRule->minutesAfterMaghrib();

        if ($parameters->isRamadan && $parameters->method === CalculationMethod::UMM_AL_QURA) {
            $ishaMinutes = 120;
        }

        if ($ishaMinutes !== null) {
            // Pin _set_isha: interval from raw sunset, not adjusted maghrib.
            return Minutes::add($day->sunset, $ishaMinutes);
        }

        $ishaAngle = $parameters->ishaRule->angle();

        if ($ishaAngle === null) {
            throw new AstronomicalException(sprintf(
                'Unable to compute Isha: no angle or interval defined (coordinates=%s, date=%s).',
                (string) $coords,
                $date->isoformat(),
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
                $ishaTemp = $this->solar->hourAngle($coords, $date, $elevationM, -$ishaAngle, true);
            }

            if ($ishaTemp === null || $ishaTemp > $ishaSafe) {
                $ishaTemp = $ishaSafe;
            }
        } elseif ($portions === null) {
            // NONE rule: raw angle temp, no safe cap.
            $ishaTemp = $this->solar->hourAngle($coords, $date, $elevationM, -$ishaAngle, true);

            if ($ishaTemp === null) {
                throw new AstronomicalException(sprintf(
                    'Unable to compute Isha: the sun never reaches the angle for these coordinates and date (coordinates=%s, date=%s).',
                    (string) $coords,
                    $date->isoformat(),
                ));
            }
        } else {
            $ishaTemp = $this->solar->hourAngle($coords, $date, $elevationM, -$ishaAngle, true);
            $ishaSafe = $this->addSeconds($day->sunset, (int) ($portions->ishaPortion * $nightSec));

            if ($ishaTemp === null || $ishaTemp > $ishaSafe) {
                $ishaTemp = $ishaSafe;
            }
        }

        return $ishaTemp;
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
