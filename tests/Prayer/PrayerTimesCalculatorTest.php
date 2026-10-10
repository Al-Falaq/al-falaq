<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Prayer;

use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Enums\HighLatitudeRule;
use AlFalaq\AlFalaq\Prayer\Enums\Madhab;
use AlFalaq\AlFalaq\Prayer\Enums\PolarCircleRule;
use AlFalaq\AlFalaq\Prayer\Enums\Prayer;
use AlFalaq\AlFalaq\Prayer\Services\PrayerTimesCalculator;
use AlFalaq\AlFalaq\Prayer\Strategies\AngleIsha;
use AlFalaq\AlFalaq\Prayer\Strategies\MinutesAfterSunsetMaghrib;
use AlFalaq\AlFalaq\Prayer\ValueObjects\CalculationParameters;
use AlFalaq\AlFalaq\Prayer\ValueObjects\PrayerAdjustments;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;
use AlFalaq\AlFalaq\Tests\Goldens\Comparator;
use PHPUnit\Framework\TestCase;

/**
 * Core pipeline pinned to two golden rows (full sweep lives in the harness).
 */
final class PrayerTimesCalculatorTest extends TestCase
{
    public function testKualaLumpurMwlMatchesGoldenRow(): void
    {
        $schedule = PrayerTimesCalculator::default()->calculate(
            Coordinates::fromDegrees(3.1390, 101.6869),
            CivilDate::fromYmd(2025, 1, 15),
            CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters(),
        );

        $expected = [
            'imsak' => '2025-01-14T22:00:00+00:00',
            'fajr' => '2025-01-14T22:10:00+00:00',
            'sunrise' => '2025-01-14T23:24:00+00:00',
            'syuruk' => '2025-01-14T23:24:00+00:00',
            'ishraq' => '2025-01-14T23:39:00+00:00',
            'dhuha' => '2025-01-14T23:52:00+00:00',
            'dhuhr' => '2025-01-15T05:24:00+00:00',
            'asr' => '2025-01-15T08:46:00+00:00',
            'maghrib' => '2025-01-15T11:21:00+00:00',
            'isha' => '2025-01-15T12:31:00+00:00',
        ];

        foreach ($expected as $marker => $minute) {
            self::assertTrue(
                Comparator::minutesEqual(
                    $schedule->timeFor(Prayer::from($marker)),
                    new \DateTimeImmutable($minute, new \DateTimeZone('UTC')),
                ),
                "marker {$marker}",
            );
        }
    }

    public function testNewYorkMwlJuneMatchesGoldenRow(): void
    {
        $schedule = PrayerTimesCalculator::default()->calculate(
            Coordinates::fromDegrees(40.7128, -74.0060),
            CivilDate::fromYmd(2025, 6, 21),
            CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters(),
        );

        $expected = [
            'imsak' => '2025-06-21T07:09:00+00:00',
            'fajr' => '2025-06-21T07:19:00+00:00',
            'sunrise' => '2025-06-21T09:25:00+00:00',
            'syuruk' => '2025-06-21T09:25:00+00:00',
            'ishraq' => '2025-06-21T09:40:00+00:00',
            'dhuha' => '2025-06-21T09:53:00+00:00',
            'dhuhr' => '2025-06-21T16:59:00+00:00',
            'asr' => '2025-06-21T20:58:00+00:00',
            'maghrib' => '2025-06-22T00:31:00+00:00',
            'isha' => '2025-06-22T02:28:00+00:00',
        ];

        foreach ($expected as $marker => $minute) {
            self::assertTrue(
                Comparator::minutesEqual(
                    $schedule->timeFor(Prayer::from($marker)),
                    new \DateTimeImmutable($minute, new \DateTimeZone('UTC')),
                ),
                "marker {$marker}",
            );
        }
    }

    public function testOsloMscJanuaryMatchesSeasonalGoldenRow(): void
    {
        $schedule = PrayerTimesCalculator::default()->calculate(
            Coordinates::fromDegrees(59.9139, 10.7522),
            CivilDate::fromYmd(2025, 1, 15),
            CalculationMethod::MOON_SIGHTING_COMMITTEE->defaultParameters(),
        );

        // Row moon_sighting_committee,2025-01-15,59.9139,10.7522 in
        // tests/Goldens/prayer_moon_sighting_committee.csv (pin a7baa8b).
        $expected = [
            'imsak' => '2025-01-15 06:10',
            'fajr' => '2025-01-15 06:20',
            'sunrise' => '2025-01-15 08:04',
            'syuruk' => '2025-01-15 08:04',
            'ishraq' => '2025-01-15 08:19',
            'dhuha' => '2025-01-15 08:32',
            'dhuhr' => '2025-01-15 11:31',
            'asr' => '2025-01-15 12:38',
            'maghrib' => '2025-01-15 14:53',
            'isha' => '2025-01-15 16:26',
        ];

        foreach ($expected as $marker => $minute) {
            self::assertSame(
                $minute,
                $schedule->timeFor(Prayer::from($marker))->format('Y-m-d H:i'),
                "marker {$marker}",
            );
        }
    }

    public function testSeasonalTwilightRuleArmMatchesMscPath(): void
    {
        $coords = Coordinates::fromDegrees(59.9139, 10.7522);
        $date = CivilDate::fromYmd(2025, 1, 15);

        $msc = PrayerTimesCalculator::default()->calculate(
            $coords,
            $date,
            CalculationMethod::MOON_SIGHTING_COMMITTEE->defaultParameters(),
        );

        // Custom NONE-method config mirroring MSC angles (fajr 18, AngleIsha 18,
        // polar NEAREST_LATITUDE, offsets 10/15/28, no adjustments) with the
        // SEASONAL_TWILIGHT rule arm instead of the MSC method arm.
        $custom = CalculationParameters::create(
            CalculationMethod::NONE,
            Madhab::SHAFI,
            HighLatitudeRule::SEASONAL_TWILIGHT,
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
            PrayerAdjustments::empty(),
            PrayerAdjustments::empty(),
        );

        $alt = PrayerTimesCalculator::default()->calculate($coords, $date, $custom);

        // Only fajr+isha engage the seasonal path: MSC ships methodAdjustments
        // {dhuhr: 5, maghrib: 3} while the custom config is empty, so the other
        // markers intentionally differ.
        self::assertSame(
            $msc->timeFor(Prayer::FAJR)->format('Y-m-d H:i'),
            $alt->timeFor(Prayer::FAJR)->format('Y-m-d H:i'),
            'seasonal fajr matches MSC fajr',
        );
        self::assertSame(
            $msc->timeFor(Prayer::ISHA)->format('Y-m-d H:i'),
            $alt->timeFor(Prayer::ISHA)->format('Y-m-d H:i'),
            'seasonal isha matches MSC isha',
        );
    }

    public function testSeasonalTwilightRuleArmMatchesMscPathBelow55(): void
    {
        // Sydney 2025-01-15 (|lat| < 55): the seasonal safes bind here (MSC
        // isha 10:23 vs portion-path 10:49), so this discriminates the rule
        // arm engaging MSC-equivalent safes. (Kuala Lumpur was considered,
        // but neither safe binds there — both paths agree trivially at
        // 22:10/12:35 — so it cannot prove the rule arm engages.)
        $coords = Coordinates::fromDegrees(-33.8688, 151.2093);
        $date = CivilDate::fromYmd(2025, 1, 15);

        $msc = PrayerTimesCalculator::default()->calculate(
            $coords,
            $date,
            CalculationMethod::MOON_SIGHTING_COMMITTEE->defaultParameters(),
        );

        // Same MSC-mirroring custom config as the Oslo rule-arm test.
        $custom = CalculationParameters::create(
            CalculationMethod::NONE,
            Madhab::SHAFI,
            HighLatitudeRule::SEASONAL_TWILIGHT,
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
            PrayerAdjustments::empty(),
            PrayerAdjustments::empty(),
        );

        $alt = PrayerTimesCalculator::default()->calculate($coords, $date, $custom);

        // Only fajr+isha engage the seasonal path: MSC ships methodAdjustments
        // {dhuhr: 5, maghrib: 3} while the custom config is empty, so the other
        // markers intentionally differ.
        self::assertSame(
            $msc->timeFor(Prayer::FAJR)->format('Y-m-d H:i'),
            $alt->timeFor(Prayer::FAJR)->format('Y-m-d H:i'),
            'low-latitude rule-arm fajr matches MSC fajr',
        );
        self::assertSame(
            $msc->timeFor(Prayer::ISHA)->format('Y-m-d H:i'),
            $alt->timeFor(Prayer::ISHA)->format('Y-m-d H:i'),
            'low-latitude rule-arm isha matches MSC isha',
        );
    }
}
