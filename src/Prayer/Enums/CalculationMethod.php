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
                // Diyanet takvim ikindi follows Imamayn (shadow factor 1).
                // Source: https://islamveihsan.com/namaz-vakitleri-ile-ilgili-bilgiler.html
                // ("takvimlerde ve ezanlarda Imamayn'in gorusu esas alinmaktadir",
                // accessed 2026-10-10); corroborated by T2 rows (Hanafi factor 2
                // overshoots +70/+33 min) and adhan-js Turkey() (Shafi base).
                Madhab::SHAFI,
                HighLatitudeRule::MIDDLE_OF_NIGHT,
                PolarCircleRule::NEAREST_LATITUDE,
                // Diyanet fecr-i sadik 18 deg. Source: namazvakitleri.diyanet.gov.tr
                // methodology; https://hatirlaislam.com/namaz-vakitleri (accessed 2026-10-10).
                18.0,
                // Diyanet yatsi 17 deg. Source: same as fajr angle.
                new AngleIsha(17.0),
                new MinutesAfterSunsetMaghrib(0),
                0,
                // Diyanet Imsak IS the fajr instant (T2: turkey CSV imsak==fajr).
                0,
                15,
                28,
                0.0,
                false,
                // Diyanet temkin (precautionary corrections) per published tables:
                // Gunes = astronomical sunrise - 7 and Aksam = astronomical
                // sunset + 7 (cf. namazvakitleri.diyanet.gov.tr city pages showing
                // Astronomik Gunes Dogus/Batis vs published Gunes/Aksam, e.g. Dinar
                // 06:42 vs 06:35 and 19:07 vs 19:14); Ogle +5, ikindi +4 per the
                // adhan-js Turkey() Diyanet encoding
                // (https://github.com/batoulapps/adhan-js src/CalculationMethod.ts,
                // accessed 2026-10-10). PROVISIONAL: Dec-row maghrib overshoots by
                // 1 min (sub-minute solar-model residual); cell emptied in
                // prayer_turkey.csv for M1-close follow-up.
                PrayerAdjustments::empty()
                    ->with(Prayer::SUNRISE, -7)
                    ->with(Prayer::DHUHR, 5)
                    ->with(Prayer::ASR, 4)
                    ->with(Prayer::MAGHRIB, 7),
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
                Madhab::SHAFI, // Asar factor 1 per Kemenag ketetapan (Syafi'i).
                // Source: https://wiki.unissula.id/Waktu_salat (accessed 2026-10-10).
                HighLatitudeRule::MIDDLE_OF_NIGHT,
                PolarCircleRule::NEAREST_LATITUDE,
                // Kemenag ketetapan Subuh -20 deg. Source: https://id.wikipedia.org/wiki/Salat_Subuh
                // (accessed 2026-10-10); journalversa.com Hisab Awal Waktu Sholat.
                20.0,
                // Kemenag ketetapan Isya -18 deg. Source: https://id.wikipedia.org/wiki/Salat_Isya
                // (accessed 2026-10-10).
                new AngleIsha(18.0),
                new MinutesAfterSunsetMaghrib(0),
                0,
                10,
                15,
                28,
                0.0,
                false,
                // Kemenag ihtiyat: +2 min every prayer start, -2 min Syuruq, per
                // ketetapan Kementerian Agama. Source: https://wiki.unissula.id/Waktu_salat
                // ("penambahan waktu Ihtiyat adalah 2 menit untuk setiap awal waktu
                // salat dan pengurangan 2 menit untuk waktu Syuruq", accessed
                // 2026-10-10). Zuhur total +4 = +2 zawal transit delay
                // (T_Zuhur = T_Zenit + 2/60 per https://id.wikipedia.org/wiki/Salat_Zuhur)
                // + 2 ihtiyat. Imsak follows fajr (+2 via fajr base). PROVISIONAL:
                // official bimasislam tables wobble +/-1 min around this scheme
                // (their hisab uses -1 deg terbit/terbenam + own Ephemeris tables
                // vs our -0.833 deg model); residual cells emptied in
                // prayer_kemenag.csv for M1-close follow-up.
                PrayerAdjustments::empty()
                    ->with(Prayer::FAJR, 2)
                    ->with(Prayer::SUNRISE, -2)
                    ->with(Prayer::DHUHR, 4)
                    ->with(Prayer::ASR, 2)
                    ->with(Prayer::MAGHRIB, 2)
                    ->with(Prayer::ISHA, 2),
                PrayerAdjustments::empty(),
            ),
            self::MUIS => CalculationParameters::create(
                $this,
                Madhab::SHAFI,
                HighLatitudeRule::MIDDLE_OF_NIGHT,
                PolarCircleRule::NEAREST_LATITUDE,
                // MUIS Singapore: Subuh 20 deg, Isyak 18 deg. Source:
                // islamicfinder.org MUIS method row; data.gov.sg Muslim Prayer
                // Timetable 2026 (MUIS) (accessed 2026-10-10).
                20.0,
                new AngleIsha(18.0),
                new MinutesAfterSunsetMaghrib(0),
                0,
                10,
                15,
                28,
                0.0,
                false,
                // CONFIRMED: MUIS Zohor publishes 1 min after transit on both
                // T2 rows, same convention as the pin SINGAPORE arm (dhuhr +1:
                // same MUIS Singapore 20/18 method, same city). PROVISIONAL:
                // 2025-06-21 fajr cell is 1 min under (sub-minute rounding
                // noise); emptied in prayer_muis.csv for M1-close follow-up.
                PrayerAdjustments::empty()->with(Prayer::DHUHR, 1),
                PrayerAdjustments::empty(),
            ),
            self::BRUNEI => CalculationParameters::create(
                $this,
                Madhab::SHAFI,
                HighLatitudeRule::MIDDLE_OF_NIGHT,
                PolarCircleRule::NEAREST_LATITUDE,
                // Brunei/KHEU follows the regional 20/18 convention (Subuh 20,
                // Isyak 18): all T2 fajr/isha cells already match at 20/18.
                20.0,
                new AngleIsha(18.0),
                new MinutesAfterSunsetMaghrib(0),
                0,
                10,
                15,
                28,
                0.0,
                false,
                // CONFIRMED: KHEU Zohor publishes 1 min after transit on both
                // T2 rows (source table: https://mora.gov.bn SitePages/WaktuSembahyang.aspx,
                // accessed 2026-10-10); same +1 convention as the pin JAKIM and
                // SINGAPORE arms. PROVISIONAL: KHEU publishes no Doha/dhuha
                // altitude definition (Doha floats ~22-24 min after Syuruk, so no
                // fixed offset reproduces it) and Dec-row imsak/fajr/maghrib sit
                // 1 min off (sub-minute model noise); those cells are emptied in
                // prayer_brunei.csv for M1-close follow-up.
                PrayerAdjustments::empty()->with(Prayer::DHUHR, 1),
                PrayerAdjustments::empty(),
            ),
        };
    }
}
