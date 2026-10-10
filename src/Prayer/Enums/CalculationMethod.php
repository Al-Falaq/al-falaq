<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Prayer\Enums;

use AlFalaq\AlFalaq\Prayer\Strategies\AngleIsha;
use AlFalaq\AlFalaq\Prayer\Strategies\AngleMaghrib;
use AlFalaq\AlFalaq\Prayer\Strategies\IntervalIsha;
use AlFalaq\AlFalaq\Prayer\Strategies\MinutesAfterSunsetMaghrib;
use AlFalaq\AlFalaq\Prayer\ValueObjects\CalculationParameters;
use AlFalaq\AlFalaq\Prayer\ValueObjects\PrayerAdjustments;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ConfigurationException;

/**
 * The 18 frozen calculation methods (Appendix A normative, M1 freeze).
 *
 * String values are lowercase canonical keys; NONE means "caller supplies
 * every parameter, no defaults".
 */
enum CalculationMethod: string
{
    case NONE = 'none';
    case MUSLIM_WORLD_LEAGUE = 'muslim_world_league';
    case EGYPTIAN = 'egyptian';
    case KARACHI = 'karachi';
    case UMM_AL_QURA = 'umm_al_qura';
    case DUBAI = 'dubai';
    case MOON_SIGHTING_COMMITTEE = 'moon_sighting_committee';
    case NORTH_AMERICA = 'north_america';
    case KUWAIT = 'kuwait';
    case QATAR = 'qatar';
    case SINGAPORE = 'singapore';
    case TURKEY = 'turkey';
    case TEHRAN = 'tehran';
    case MOROCCO = 'morocco';
    case JAKIM = 'jakim';
    case KEMENAG = 'kemenag';
    case MUIS = 'muis';
    case BRUNEI = 'brunei';

    /**
     * Shipped defaults for this method (Appendix A frozen M1 from pin a7baa8b).
     *
     * Pin-known cells trace to MethodsParameters.py @ a7baa8b plus the
     * CalculationParameters.py ctor defaults (imsak 10 / ishraq 15 /
     * dhuha 28); cells marked PROVISIONAL have no pin source and T2
     * goldens decide.
     *
     * @return CalculationParameters Defaults to customize via with*().
     */
    public function defaultParameters(): CalculationParameters
    {
        return match ($this) {
            self::NONE => throw new ConfigurationException(
                'NONE has no defaults: build via CalculationParameters::create().',
            ),
            self::MUSLIM_WORLD_LEAGUE => CalculationParameters::create(
                $this,
                Madhab::SHAFI,
                HighLatitudeRule::MIDDLE_OF_NIGHT,
                PolarCircleRule::NEAREST_LATITUDE,
                18.0,
                new AngleIsha(17.0),
                new MinutesAfterSunsetMaghrib(0),
                0,
                10,
                15,
                28,
                0.0,
                false,
                PrayerAdjustments::empty()->with(Prayer::DHUHR, 1),
                PrayerAdjustments::empty(),
            ),
            self::EGYPTIAN => CalculationParameters::create(
                $this,
                Madhab::SHAFI,
                HighLatitudeRule::MIDDLE_OF_NIGHT,
                PolarCircleRule::NEAREST_LATITUDE,
                19.5,
                new AngleIsha(17.5),
                new MinutesAfterSunsetMaghrib(0),
                0,
                10,
                15,
                28,
                0.0,
                false,
                PrayerAdjustments::empty()->with(Prayer::DHUHR, 1),
                PrayerAdjustments::empty(),
            ),
            self::KARACHI => CalculationParameters::create(
                $this,
                Madhab::SHAFI,
                HighLatitudeRule::MIDDLE_OF_NIGHT,
                PolarCircleRule::NEAREST_LATITUDE,
                18.0,
                new AngleIsha(18.0),
                new MinutesAfterSunsetMaghrib(0),
                0,
                10,
                15,
                28,
                0.0,
                false,
                PrayerAdjustments::empty()->with(Prayer::DHUHR, 1),
                PrayerAdjustments::empty(),
            ),
            self::UMM_AL_QURA => CalculationParameters::create(
                $this,
                Madhab::SHAFI,
                HighLatitudeRule::MIDDLE_OF_NIGHT,
                PolarCircleRule::NEAREST_LATITUDE,
                18.5,
                new IntervalIsha(90),
                new MinutesAfterSunsetMaghrib(0),
                0,
                10,
                15,
                28,
                0.0,
                false,
                PrayerAdjustments::empty(),
                PrayerAdjustments::empty(),
            ),
            self::DUBAI => CalculationParameters::create(
                $this,
                Madhab::SHAFI,
                HighLatitudeRule::MIDDLE_OF_NIGHT,
                PolarCircleRule::NEAREST_LATITUDE,
                18.2,
                new AngleIsha(18.2),
                new MinutesAfterSunsetMaghrib(0),
                0,
                10,
                15,
                28,
                0.0,
                false,
                PrayerAdjustments::empty()
                    ->with(Prayer::SUNRISE, -3)
                    ->with(Prayer::DHUHR, 3)
                    ->with(Prayer::ASR, 3)
                    ->with(Prayer::MAGHRIB, 3),
                PrayerAdjustments::empty(),
            ),
            self::MOON_SIGHTING_COMMITTEE => CalculationParameters::create(
                $this,
                Madhab::SHAFI,
                HighLatitudeRule::MIDDLE_OF_NIGHT,
                PolarCircleRule::NEAREST_LATITUDE,
                18.0,
                new AngleIsha(18.0),
                new MinutesAfterSunsetMaghrib(0),
                0,
                10,
                15,
                28,
                0.0,
                false,
                PrayerAdjustments::empty()
                    ->with(Prayer::DHUHR, 5)
                    ->with(Prayer::MAGHRIB, 3),
                PrayerAdjustments::empty(),
            ),
            self::NORTH_AMERICA => CalculationParameters::create(
                $this,
                Madhab::SHAFI,
                HighLatitudeRule::MIDDLE_OF_NIGHT,
                PolarCircleRule::NEAREST_LATITUDE,
                15.0,
                new AngleIsha(15.0),
                new MinutesAfterSunsetMaghrib(0),
                0,
                10,
                15,
                28,
                0.0,
                false,
                PrayerAdjustments::empty()->with(Prayer::DHUHR, 1),
                PrayerAdjustments::empty(),
            ),
            self::KUWAIT => CalculationParameters::create(
                $this,
                Madhab::SHAFI,
                HighLatitudeRule::MIDDLE_OF_NIGHT,
                PolarCircleRule::NEAREST_LATITUDE,
                18.0,
                new AngleIsha(17.5),
                new MinutesAfterSunsetMaghrib(0),
                0,
                10,
                15,
                28,
                0.0,
                false,
                PrayerAdjustments::empty(),
                PrayerAdjustments::empty(),
            ),
            self::QATAR => CalculationParameters::create(
                $this,
                Madhab::SHAFI,
                HighLatitudeRule::MIDDLE_OF_NIGHT,
                PolarCircleRule::NEAREST_LATITUDE,
                18.0,
                new IntervalIsha(90),
                new MinutesAfterSunsetMaghrib(0),
                0,
                10,
                15,
                28,
                0.0,
                false,
                PrayerAdjustments::empty(),
                PrayerAdjustments::empty(),
            ),
            self::SINGAPORE => CalculationParameters::create(
                $this,
                Madhab::SHAFI,
                HighLatitudeRule::MIDDLE_OF_NIGHT,
                PolarCircleRule::NEAREST_LATITUDE,
                20.0,
                new AngleIsha(18.0),
                new MinutesAfterSunsetMaghrib(0),
                0,
                10,
                15,
                28,
                0.0,
                false,
                PrayerAdjustments::empty()->with(Prayer::DHUHR, 1),
                PrayerAdjustments::empty(),
            ),
            self::TURKEY => CalculationParameters::create(
                $this,
                Madhab::HANAFI, // PROVISIONAL: no pin source.
                HighLatitudeRule::MIDDLE_OF_NIGHT,
                PolarCircleRule::NEAREST_LATITUDE,
                18.0, // PROVISIONAL: no pin source.
                new AngleIsha(17.0), // PROVISIONAL: no pin source.
                new MinutesAfterSunsetMaghrib(0),
                0,
                // Diyanet Imsak IS the fajr instant (T2: turkey CSV imsak==fajr).
                0,
                15,
                28,
                0.0,
                false,
                PrayerAdjustments::empty(),
                PrayerAdjustments::empty(),
            ),
            self::TEHRAN => CalculationParameters::create(
                $this,
                Madhab::SHAFI,
                HighLatitudeRule::MIDDLE_OF_NIGHT,
                PolarCircleRule::NEAREST_LATITUDE,
                17.7, // PROVISIONAL: no pin source.
                new AngleIsha(14.0), // PROVISIONAL: no pin source.
                new AngleMaghrib(4.5), // PROVISIONAL: no pin source.
                0,
                10,
                15,
                28,
                0.0,
                false,
                PrayerAdjustments::empty(),
                PrayerAdjustments::empty(),
            ),
            self::MOROCCO => CalculationParameters::create(
                $this,
                Madhab::SHAFI,
                HighLatitudeRule::MIDDLE_OF_NIGHT,
                PolarCircleRule::NEAREST_LATITUDE,
                19.0, // PROVISIONAL: no pin source.
                new AngleIsha(17.0), // PROVISIONAL: no pin source.
                new MinutesAfterSunsetMaghrib(0),
                0,
                10,
                15,
                28,
                0.0,
                false,
                PrayerAdjustments::empty(),
                PrayerAdjustments::empty(),
            ),
            self::JAKIM => CalculationParameters::create(
                $this,
                Madhab::SHAFI,
                HighLatitudeRule::MIDDLE_OF_NIGHT,
                PolarCircleRule::NEAREST_LATITUDE,
                20.0,
                new AngleIsha(18.0),
                new MinutesAfterSunsetMaghrib(0),
                0,
                10,
                15,
                28,
                0.0,
                false,
                PrayerAdjustments::empty()->with(Prayer::DHUHR, 1),
                PrayerAdjustments::empty(),
            ),
            self::KEMENAG => CalculationParameters::create(
                $this,
                Madhab::SHAFI,
                HighLatitudeRule::MIDDLE_OF_NIGHT,
                PolarCircleRule::NEAREST_LATITUDE,
                20.0, // PROVISIONAL: no pin source.
                new AngleIsha(18.0), // PROVISIONAL: no pin source.
                new MinutesAfterSunsetMaghrib(0),
                0,
                10,
                15,
                28,
                0.0,
                false,
                PrayerAdjustments::empty(),
                PrayerAdjustments::empty(),
            ),
            self::MUIS => CalculationParameters::create(
                $this,
                Madhab::SHAFI,
                HighLatitudeRule::MIDDLE_OF_NIGHT,
                PolarCircleRule::NEAREST_LATITUDE,
                20.0, // PROVISIONAL: no pin source.
                new AngleIsha(18.0), // PROVISIONAL: no pin source.
                new MinutesAfterSunsetMaghrib(0),
                0,
                10,
                15,
                28,
                0.0,
                false,
                PrayerAdjustments::empty(),
                PrayerAdjustments::empty(),
            ),
            self::BRUNEI => CalculationParameters::create(
                $this,
                Madhab::SHAFI,
                HighLatitudeRule::MIDDLE_OF_NIGHT,
                PolarCircleRule::NEAREST_LATITUDE,
                20.0, // PROVISIONAL: no pin source.
                new AngleIsha(18.0), // PROVISIONAL: no pin source.
                new MinutesAfterSunsetMaghrib(0),
                0,
                10,
                15,
                28,
                0.0,
                false,
                PrayerAdjustments::empty(),
                PrayerAdjustments::empty(),
            ),
        };
    }
}
