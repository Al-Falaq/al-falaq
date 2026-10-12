<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Prayer;

use AlFalaq\AlFalaq\Prayer\Contracts\IshaRule;
use AlFalaq\AlFalaq\Prayer\Contracts\MaghribRule;
use AlFalaq\AlFalaq\Prayer\Contracts\PolarCircleHandler;
use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Enums\HighLatitudeRule;
use AlFalaq\AlFalaq\Prayer\Enums\Madhab;
use AlFalaq\AlFalaq\Prayer\Enums\PolarCircleRule;
use AlFalaq\AlFalaq\Prayer\Enums\Prayer;
use AlFalaq\AlFalaq\Prayer\Exceptions\UndefinedScheduleException;
use AlFalaq\AlFalaq\Prayer\Services\PrayerTimesCalculator;
use AlFalaq\AlFalaq\Prayer\Strategies\AngleIsha;
use AlFalaq\AlFalaq\Prayer\Strategies\AngleMaghrib;
use AlFalaq\AlFalaq\Prayer\Strategies\IntervalIsha;
use AlFalaq\AlFalaq\Prayer\Strategies\MiddleOfNightCorrection;
use AlFalaq\AlFalaq\Prayer\Strategies\MinutesAfterSunsetMaghrib;
use AlFalaq\AlFalaq\Prayer\Strategies\SeasonAdjustedTwilight;
use AlFalaq\AlFalaq\Prayer\Strategies\TwilightAngleCorrection;
use AlFalaq\AlFalaq\Prayer\ValueObjects\CalculationParameters;
use AlFalaq\AlFalaq\Prayer\ValueObjects\PrayerAdjustments;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\AstronomicalException;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ConfigurationException;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;
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
            'imsak' => '2025-01-14 22:00',
            'fajr' => '2025-01-14 22:10',
            'sunrise' => '2025-01-14 23:24',
            'syuruk' => '2025-01-14 23:24',
            'ishraq' => '2025-01-14 23:39',
            'dhuha' => '2025-01-14 23:52',
            'dhuhr' => '2025-01-15 05:24',
            'asr' => '2025-01-15 08:46',
            'maghrib' => '2025-01-15 11:21',
            'isha' => '2025-01-15 12:31',
        ];

        foreach ($expected as $marker => $minute) {
            self::assertSame(
                $minute,
                $schedule->timeFor(Prayer::from($marker))->format('Y-m-d H:i'),
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
            'imsak' => '2025-06-21 07:09',
            'fajr' => '2025-06-21 07:19',
            'sunrise' => '2025-06-21 09:25',
            'syuruk' => '2025-06-21 09:25',
            'ishraq' => '2025-06-21 09:40',
            'dhuha' => '2025-06-21 09:53',
            'dhuhr' => '2025-06-21 16:59',
            'asr' => '2025-06-21 20:58',
            'maghrib' => '2025-06-22 00:31',
            'isha' => '2025-06-22 02:28',
        ];

        foreach ($expected as $marker => $minute) {
            self::assertSame(
                $minute,
                $schedule->timeFor(Prayer::from($marker))->format('Y-m-d H:i'),
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

    public function testHanafiAsrIsLaterThanShafiAsr(): void
    {
        $coords = Coordinates::fromDegrees(3.1390, 101.6869);
        $date = CivilDate::fromYmd(2025, 1, 15);

        $shafi = CalculationParameters::create(
            CalculationMethod::NONE,
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
            PrayerAdjustments::empty(),
            PrayerAdjustments::empty(),
        );

        $hanafi = CalculationParameters::create(
            CalculationMethod::NONE,
            Madhab::HANAFI,
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
            PrayerAdjustments::empty(),
            PrayerAdjustments::empty(),
        );

        $shafiAsr = PrayerTimesCalculator::default()->calculate($coords, $date, $shafi)->timeFor(Prayer::ASR);
        $hanafiAsr = PrayerTimesCalculator::default()->calculate($coords, $date, $hanafi)->timeFor(Prayer::ASR);

        self::assertGreaterThan(
            $shafiAsr->getTimestamp(),
            $hanafiAsr->getTimestamp(),
            'Hanafi asr must be strictly later than Shafi asr via the AsrRule strategy seam.',
        );
    }

    /**
     * Build NONE-rule parameters for the polar-edge branch tests.
     *
     * @param float $fajrAngle Fajr depression in degrees.
     * @param MaghribRule $maghribRule Maghrib definition.
     * @param IshaRule $ishaRule Isha definition.
     * @return CalculationParameters Custom parameters with no night splitting.
     */
    private static function noneParams(float $fajrAngle, MaghribRule $maghribRule, IshaRule $ishaRule): CalculationParameters
    {
        return CalculationParameters::create(
            CalculationMethod::NONE,
            Madhab::SHAFI,
            HighLatitudeRule::NONE,
            PolarCircleRule::NEAREST_LATITUDE,
            $fajrAngle,
            $ishaRule,
            $maghribRule,
            0,
            10,
            15,
            28,
            0.0,
            false,
            PrayerAdjustments::empty(),
            PrayerAdjustments::empty(),
        );
    }

    public function testDefaultForSelectsHighLatitudeArms(): void
    {
        // Only the arms the wiring test does not cover (mutation gate); the
        // middle/seventh arms live in CalculatorWiringTest.
        $base = CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters();

        $twilight = PrayerTimesCalculator::defaultFor($base->withHighLatitudeRule(HighLatitudeRule::TWILIGHT_ANGLE));
        self::assertInstanceOf(TwilightAngleCorrection::class, $twilight->highLatitudeCorrection());

        $seasonal = PrayerTimesCalculator::defaultFor($base->withHighLatitudeRule(HighLatitudeRule::SEASONAL_TWILIGHT));
        self::assertInstanceOf(MiddleOfNightCorrection::class, $seasonal->highLatitudeCorrection());

        $none = PrayerTimesCalculator::defaultFor($base->withHighLatitudeRule(HighLatitudeRule::NONE));
        self::assertInstanceOf(MiddleOfNightCorrection::class, $none->highLatitudeCorrection());
    }

    public function testSeasonalMscAtLatitude55UsesSeventhOfNight(): void
    {
        // The pin gates its temp branch on signed latitude >= 55: at exactly
        // 55.0 the seventh-of-night temp replaces the angle temp (mutation
        // gate pins the boundary against 54.9, which stays on angle temps).
        $params = CalculationMethod::MOON_SIGHTING_COMMITTEE->defaultParameters();

        $at55 = PrayerTimesCalculator::default()->calculate(
            Coordinates::fromDegrees(55.0, 37.6173),
            CivilDate::fromYmd(2025, 6, 21),
            $params,
        );

        self::assertSame('2025-06-20 23:53', $at55->timeFor(Prayer::FAJR)->format('Y-m-d H:i'));
        self::assertSame('2025-06-21 19:09', $at55->timeFor(Prayer::ISHA)->format('Y-m-d H:i'));
    }

    public function testNoneRuleThrowsWhereMiddleSucceedsAtPolarSummer(): void
    {
        // NONE skips the portions safe caps (raw angle temps); at polar
        // summer the raw fajr temp is unreachable while Middle clamps.
        $coords = Coordinates::fromDegrees(69.6496, 18.9553);
        $date = CivilDate::fromYmd(2025, 6, 21);
        $base = CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters();

        $middle = PrayerTimesCalculator::default()->calculate($coords, $date, $base);
        self::assertTrue($middle->timeFor(Prayer::FAJR) < $middle->timeFor(Prayer::SUNRISE));

        $this->expectException(AstronomicalException::class);
        PrayerTimesCalculator::default()->calculate(
            $coords,
            $date,
            $base->withHighLatitudeRule(HighLatitudeRule::NONE),
        );
    }

    public function testCalculateAcceptsArrayCoordinates(): void
    {
        // The Coordinates|array union normalizes pairs (mutation gate).
        $params = CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters();
        $date = CivilDate::fromYmd(2025, 1, 15);

        $fromVo = PrayerTimesCalculator::default()->calculate(
            Coordinates::fromDegrees(3.1390, 101.6869),
            $date,
            $params,
        );
        $fromArray = PrayerTimesCalculator::default()->calculate([3.1390, 101.6869], $date, $params);

        self::assertSame(
            $fromVo->timeFor(Prayer::FAJR)->format('Y-m-d H:i'),
            $fromArray->timeFor(Prayer::FAJR)->format('Y-m-d H:i'),
        );
        self::assertSame('2025-01-14 22:10', $fromArray->timeFor(Prayer::FAJR)->format('Y-m-d H:i'));
    }

    public function testRamadanFlagRequiresUmmAlQura(): void
    {
        $params = CalculationParameters::create(
            CalculationMethod::MUSLIM_WORLD_LEAGUE,
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
            true,
            PrayerAdjustments::empty(),
            PrayerAdjustments::empty(),
        );

        $this->expectException(ConfigurationException::class);

        PrayerTimesCalculator::default()->calculate(
            Coordinates::fromDegrees(3.1390, 101.6869),
            CivilDate::fromYmd(2025, 1, 15),
            $params,
        );
    }

    public function testUnresolvedPolarNightThrows(): void
    {
        $handler = new class implements PolarCircleHandler {
            public function resolve(Coordinates $coordinates, CivilDate $date, callable $isSolvable): array
            {
                return ['coordinates' => $coordinates, 'date' => $date];
            }
        };

        $calculator = new PrayerTimesCalculator(
            new MiddleOfNightCorrection(),
            $handler,
            new SeasonAdjustedTwilight(),
        );

        $this->expectException(UndefinedScheduleException::class);

        $calculator->calculate(
            Coordinates::fromDegrees(85.0, 0.0),
            CivilDate::fromYmd(2025, 12, 15),
            CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters(),
        );
    }

    public function testNoneRuleFajrBelowHorizonThrows(): void
    {
        $this->expectException(AstronomicalException::class);

        PrayerTimesCalculator::default()->calculate(
            Coordinates::fromDegrees(65.0, 0.0),
            CivilDate::fromYmd(2025, 6, 21),
            self::noneParams(18.0, new MinutesAfterSunsetMaghrib(0), new IntervalIsha(90)),
        );
    }

    public function testNoneRuleMaghribAngleBelowHorizonThrows(): void
    {
        $this->expectException(AstronomicalException::class);

        PrayerTimesCalculator::default()->calculate(
            Coordinates::fromDegrees(65.0, 0.0),
            CivilDate::fromYmd(2025, 6, 21),
            self::noneParams(1.0, new AngleMaghrib(6.0), new IntervalIsha(90)),
        );
    }

    public function testNoneRuleIshaAngleBelowHorizonThrows(): void
    {
        $this->expectException(AstronomicalException::class);

        PrayerTimesCalculator::default()->calculate(
            Coordinates::fromDegrees(65.0, 0.0),
            CivilDate::fromYmd(2025, 6, 21),
            self::noneParams(1.0, new MinutesAfterSunsetMaghrib(0), new AngleIsha(6.0)),
        );
    }

    public function testNoneRuleSucceedsWhenAllAnglesReachable(): void
    {
        $schedule = PrayerTimesCalculator::default()->calculate(
            Coordinates::fromDegrees(3.1390, 101.6869),
            CivilDate::fromYmd(2025, 1, 15),
            self::noneParams(18.0, new MinutesAfterSunsetMaghrib(0), new AngleIsha(17.0)),
        );

        self::assertTrue($schedule->timeFor(Prayer::FAJR) < $schedule->timeFor(Prayer::SUNRISE));
        self::assertTrue($schedule->timeFor(Prayer::SUNRISE) < $schedule->timeFor(Prayer::ISHA));
    }
}
