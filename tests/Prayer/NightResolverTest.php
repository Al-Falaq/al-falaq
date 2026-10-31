<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Prayer;

use AlFalaq\AlFalaq\Astro\Contracts\SolarDayEngine;
use AlFalaq\AlFalaq\Astro\ValueObjects\SolarTime;
use AlFalaq\AlFalaq\Prayer\Contracts\HighLatitudeCorrection;
use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Enums\HighLatitudeRule;
use AlFalaq\AlFalaq\Prayer\Enums\Madhab;
use AlFalaq\AlFalaq\Prayer\Enums\PolarCircleRule;
use AlFalaq\AlFalaq\Prayer\Services\NightResolver;
use AlFalaq\AlFalaq\Prayer\Strategies\AngleIsha;
use AlFalaq\AlFalaq\Prayer\Strategies\IntervalIsha;
use AlFalaq\AlFalaq\Prayer\Strategies\MiddleOfNightCorrection;
use AlFalaq\AlFalaq\Prayer\Strategies\MinutesAfterSunsetMaghrib;
use AlFalaq\AlFalaq\Prayer\Strategies\SeasonAdjustedTwilight;
use AlFalaq\AlFalaq\Prayer\ValueObjects\CalculationParameters;
use AlFalaq\AlFalaq\Prayer\ValueObjects\NightPortions;
use AlFalaq\AlFalaq\Prayer\ValueObjects\PrayerAdjustments;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\AstronomicalException;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;
use PHPUnit\Framework\TestCase;

/**
 * Night temp-vs-safe resolution through stubbed astronomy (no engine).
 */
final class NightResolverTest extends TestCase
{
    public function testPortionsCapsClampWithoutAstronomy(): void
    {
        // Canned 12-hour day: sunrise 06:00, sunset 18:00 UTC, night 43200 s.
        // Middle-of-night halves cap fajr at 00:00 and isha at next 00:00.
        $utc = new \DateTimeZone('UTC');
        $day = new SolarTime(
            new \DateTimeImmutable('2025-01-15 12:00:00', $utc),
            new \DateTimeImmutable('2025-01-15 06:00:00', $utc),
            new \DateTimeImmutable('2025-01-15 18:00:00', $utc),
        );
        // Stub angle temps outside both caps: fajr must clamp up to the safe,
        // isha down to it — with zero trigonometry involved.
        $fajrStub = new \DateTimeImmutable('2025-01-14 23:00:00', $utc);
        $ishaStub = new \DateTimeImmutable('2025-01-16 01:00:00', $utc);
        $capped = new NightResolver(
            self::stubSolar($day, $fajrStub, $ishaStub),
            new MiddleOfNightCorrection(),
            new SeasonAdjustedTwilight(),
        );

        $temps = $capped->resolve(
            Coordinates::fromDegrees(3.1390, 101.6869),
            CivilDate::fromYmd(2025, 1, 15),
            0.0,
            CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters(),
            $day,
            43200,
        );

        self::assertSame('2025-01-15 00:00', $temps['fajr']->format('Y-m-d H:i'));
        self::assertSame('2025-01-16 00:00', $temps['isha']->format('Y-m-d H:i'));
        self::assertSame('UTC', $temps['fajr']->getTimezone()->getName());
    }

    public function testNoneRuleUnreachableAngleThrows(): void
    {
        $utc = new \DateTimeZone('UTC');
        $day = new SolarTime(
            new \DateTimeImmutable('2025-01-15 12:00:00', $utc),
            new \DateTimeImmutable('2025-01-15 06:00:00', $utc),
            new \DateTimeImmutable('2025-01-15 18:00:00', $utc),
        );
        $resolver = new NightResolver(
            self::stubSolar($day, null),
            new MiddleOfNightCorrection(),
            new SeasonAdjustedTwilight(),
        );
        $params = CalculationParameters::create(
            CalculationMethod::NONE,
            Madhab::SHAFI,
            HighLatitudeRule::NONE,
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

        $this->expectException(AstronomicalException::class);

        $resolver->resolve(
            Coordinates::fromDegrees(3.1390, 101.6869),
            CivilDate::fromYmd(2025, 1, 15),
            0.0,
            $params,
            $day,
            43200,
        );
    }

    /**
     * The seasonal arm must not compute fallback portions it never reads.
     *
     * The counter also proves the test is not vacuous: the same resolver
     * constructed with MIDDLE_OF_NIGHT does ask for portions.
     *
     * @return void
     */
    public function testSeasonalPathNeverComputesFallbackPortions(): void
    {
        $utc = new \DateTimeZone('UTC');
        $day = new SolarTime(
            new \DateTimeImmutable('2025-06-21 12:00:00', $utc),
            new \DateTimeImmutable('2025-06-21 03:00:00', $utc),
            new \DateTimeImmutable('2025-06-21 21:00:00', $utc),
        );
        $correction = new class implements HighLatitudeCorrection {
            public bool $called = false;

            public function portions(float $fajrAngle, ?float $ishaAngle): NightPortions
            {
                $this->called = true;

                return new NightPortions(0.5, 0.5);
            }
        };
        $resolver = new NightResolver(
            self::stubSolar($day, new \DateTimeImmutable('2025-06-21 01:00:00', $utc)),
            $correction,
            new SeasonAdjustedTwilight(),
        );
        $coordinates = Coordinates::fromDegrees(3.1390, 101.6869);
        $date = CivilDate::fromYmd(2025, 6, 21);
        $base = CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters();

        $resolver->resolve(
            $coordinates,
            $date,
            0.0,
            $base->withHighLatitudeRule(HighLatitudeRule::SEASONAL_TWILIGHT),
            $day,
            36000,
        );
        self::assertFalse($correction->called, 'SEASONAL_TWILIGHT must not compute fallback portions.');

        $resolver->resolve(
            $coordinates,
            $date,
            0.0,
            $base->withHighLatitudeRule(HighLatitudeRule::MIDDLE_OF_NIGHT),
            $day,
            36000,
        );
        self::assertTrue($correction->called, 'The portions path must still compute portions.');
    }

    public function testSeventhOfNightBranchAtHighLatitude(): void
    {
        // Latitude >= 55 with MSC/seasonal-safe engages the seventh-of-night
        // temp in both fajrTemp and ishaTemp, bypassing the angle temp call.
        $utc = new \DateTimeZone('UTC');
        $day = new SolarTime(
            new \DateTimeImmutable('2025-06-21 12:00:00', $utc),
            new \DateTimeImmutable('2025-06-21 03:00:00', $utc),
            new \DateTimeImmutable('2025-06-21 21:00:00', $utc),
        );
        $resolver = new NightResolver(
            self::stubSolar($day, null), // angle temp must NOT be called on seventh path
            new MiddleOfNightCorrection(),
            new SeasonAdjustedTwilight(),
        );
        $coordinates = Coordinates::fromDegrees(60.0, 10.0); // >= 55
        $date = CivilDate::fromYmd(2025, 6, 21);
        $base = CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters();

        $temps = $resolver->resolve(
            $coordinates,
            $date,
            0.0,
            $base->withHighLatitudeRule(HighLatitudeRule::SEASONAL_TWILIGHT),
            $day,
            64800,
        );

        self::assertNotNull($temps['fajr']);
        self::assertNotNull($temps['isha']);
        self::assertTrue($temps['fajr'] < $day->sunrise);
        self::assertTrue($temps['isha'] > $day->sunset);
    }

    public function testSeasonalSafeWithoutSeventhAtLowLatitude(): void
    {
        // Latitude < 55 with SEASONAL_TWILIGHT: seasonal-safe path engages
        // but seventh-of-night does NOT, so angle temp is still computed.
        $utc = new \DateTimeZone('UTC');
        $day = new SolarTime(
            new \DateTimeImmutable('2025-06-21 12:00:00', $utc),
            new \DateTimeImmutable('2025-06-21 03:00:00', $utc),
            new \DateTimeImmutable('2025-06-21 21:00:00', $utc),
        );
        // Angle temp after the seasonal safe cap, so it is used as-is.
        $fajrTemp = new \DateTimeImmutable('2025-06-21 02:00:00', $utc);
        $resolver = new NightResolver(
            self::stubSolar($day, $fajrTemp, $fajrTemp), // evening = fajr temp
            new MiddleOfNightCorrection(),
            new SeasonAdjustedTwilight(),
        );
        $coordinates = Coordinates::fromDegrees(3.1390, 101.6869); // < 55
        $date = CivilDate::fromYmd(2025, 6, 21);
        $base = CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters();

        $temps = $resolver->resolve(
            $coordinates,
            $date,
            0.0,
            $base->withHighLatitudeRule(HighLatitudeRule::SEASONAL_TWILIGHT),
            $day,
            64800,
        );

        self::assertSame($fajrTemp, $temps['fajr']);
    }

    public function testIshaIntervalEarlyReturn(): void
    {
        // IshaRule with minutesAfterMaghrib returns early without computing angle temp.
        $utc = new \DateTimeZone('UTC');
        $day = new SolarTime(
            new \DateTimeImmutable('2025-06-21 12:00:00', $utc),
            new \DateTimeImmutable('2025-06-21 03:00:00', $utc),
            new \DateTimeImmutable('2025-06-21 21:00:00', $utc),
        );
        $resolver = new NightResolver(
            self::stubSolar($day, null), // angle temp must NOT be called for interval isha
            new MiddleOfNightCorrection(),
            new SeasonAdjustedTwilight(),
        );
        $coordinates = Coordinates::fromDegrees(3.1390, 101.6869);
        $date = CivilDate::fromYmd(2025, 6, 21);
        $params = CalculationParameters::create(
            CalculationMethod::NONE,
            Madhab::SHAFI,
            HighLatitudeRule::MIDDLE_OF_NIGHT,
            PolarCircleRule::NEAREST_LATITUDE,
            18.0,
            new IntervalIsha(90), // interval-based Isha
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

        $temps = $resolver->resolve(
            $coordinates,
            $date,
            0.0,
            $params,
            $day,
            43200,
        );

        self::assertSame(
            $day->sunset->modify('+90 minutes')->format('Y-m-d H:i:s'),
            $temps['isha']->format('Y-m-d H:i:s'),
        );
    }

    public function testUmmAlQuraRamadanIshaOverride(): void
    {
        // UMM_AL_QURA during Ramadan overrides isha minutes to 120.
        $utc = new \DateTimeZone('UTC');
        $day = new SolarTime(
            new \DateTimeImmutable('2025-06-21 12:00:00', $utc),
            new \DateTimeImmutable('2025-06-21 03:00:00', $utc),
            new \DateTimeImmutable('2025-06-21 21:00:00', $utc),
        );
        $resolver = new NightResolver(
            self::stubSolar($day, null),
            new MiddleOfNightCorrection(),
            new SeasonAdjustedTwilight(),
        );
        $coordinates = Coordinates::fromDegrees(21.4225241, 39.8261818);
        $date = CivilDate::fromYmd(2025, 6, 21);
        $params = CalculationParameters::create(
            CalculationMethod::UMM_AL_QURA,
            Madhab::SHAFI,
            HighLatitudeRule::MIDDLE_OF_NIGHT,
            PolarCircleRule::NEAREST_LATITUDE,
            18.0,
            new IntervalIsha(90), // overridden by Ramadan rule to 120
            new MinutesAfterSunsetMaghrib(0),
            0,
            10,
            15,
            28,
            0.0,
            true, // isRamadan = true
            PrayerAdjustments::empty(),
            PrayerAdjustments::empty(),
        );

        $temps = $resolver->resolve(
            $coordinates,
            $date,
            0.0,
            $params,
            $day,
            43200,
        );

        self::assertSame(
            $day->sunset->modify('+120 minutes')->format('Y-m-d H:i:s'),
            $temps['isha']->format('Y-m-d H:i:s'),
        );
    }

    public function testFajrTempNonePathNullThrows(): void
    {
        // NONE rule (portions === null): hourAngle returns null for fajr,
        // triggering the null check throw (no safe cap in NONE mode).
        $utc = new \DateTimeZone('UTC');
        $day = new SolarTime(
            new \DateTimeImmutable('2025-01-15 12:00:00', $utc),
            new \DateTimeImmutable('2025-01-15 06:00:00', $utc),
            new \DateTimeImmutable('2025-01-15 18:00:00', $utc),
        );
        // morning=null → fajr hourAngle returns null.
        $resolver = new NightResolver(
            self::stubSolar($day, null),
            new MiddleOfNightCorrection(),
            new SeasonAdjustedTwilight(),
        );
        $coordinates = Coordinates::fromDegrees(3.1390, 101.6869);
        $date = CivilDate::fromYmd(2025, 1, 15);
        $params = CalculationParameters::create(
            CalculationMethod::NONE,
            Madhab::SHAFI,
            HighLatitudeRule::NONE, // NONE → portions === null
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

        $this->expectException(AstronomicalException::class);
        $this->expectExceptionMessage('Unable to compute Fajr');

        $resolver->resolve(
            $coordinates,
            $date,
            0.0,
            $params,
            $day,
            43200,
        );
    }

    public function testIshaTempNonePathNullThrows(): void
    {
        // NONE rule (portions === null): hourAngle returns null for isha
        // (evening), triggering the null check throw.
        $utc = new \DateTimeZone('UTC');
        $day = new SolarTime(
            new \DateTimeImmutable('2025-01-15 12:00:00', $utc),
            new \DateTimeImmutable('2025-01-15 06:00:00', $utc),
            new \DateTimeImmutable('2025-01-15 18:00:00', $utc),
        );
        // evening=null → isha hourAngle returns null; morning set → fajr works.
        $resolver = new NightResolver(
            self::stubSolar($day, new \DateTimeImmutable('2025-01-15 03:00:00', $utc), null),
            new MiddleOfNightCorrection(),
            new SeasonAdjustedTwilight(),
        );
        $coordinates = Coordinates::fromDegrees(3.1390, 101.6869);
        $date = CivilDate::fromYmd(2025, 1, 15);
        $params = CalculationParameters::create(
            CalculationMethod::NONE,
            Madhab::SHAFI,
            HighLatitudeRule::NONE, // NONE → portions === null
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

        $this->expectException(AstronomicalException::class);
        $this->expectExceptionMessage('Unable to compute Isha');

        $resolver->resolve(
            $coordinates,
            $date,
            0.0,
            $params,
            $day,
            43200,
        );
    }

    /**
     * Day engine double with a canned solar day and angle temps.
     *
     * @param SolarTime $day Canned transit/sunrise/sunset.
     * @param \DateTimeImmutable|null $angleTemp Fixed hourAngle answer for both directions.
     * @param \DateTimeImmutable|null $eveningTemp Fixed evening answer (defaults to $angleTemp).
     * @return SolarDayEngine Engine double with no astronomy.
     */
    private static function stubSolar(
        SolarTime $day,
        ?\DateTimeImmutable $angleTemp,
        ?\DateTimeImmutable $eveningTemp = null,
    ): SolarDayEngine {
        return new class ($day, $angleTemp, $eveningTemp) implements SolarDayEngine {
            public function __construct(
                private readonly SolarTime $day,
                private readonly ?\DateTimeImmutable $morning,
                private readonly ?\DateTimeImmutable $evening,
            ) {}

            public function declination(float $julianDay): float
            {
                return 0.0;
            }

            public function equationOfTime(float $julianDay): float
            {
                return 0.0;
            }

            public function solarTime(Coordinates $coordinates, CivilDate $date, mixed $elevationM): SolarTime
            {
                return $this->day;
            }

            public function hourAngleFloat(
                Coordinates $coordinates,
                CivilDate $date,
                mixed $elevationM,
                float $angleDeg,
                bool $afterTransit,
            ): ?float {
                return null;
            }

            public function hourAngle(
                Coordinates $coordinates,
                CivilDate $date,
                mixed $elevationM,
                float $angleDeg,
                bool $afterTransit,
            ): ?\DateTimeImmutable {
                return $afterTransit ? $this->evening : $this->morning;
            }

            public function afternoon(
                Coordinates $coordinates,
                CivilDate $date,
                mixed $elevationM,
                mixed $shadowLength,
            ): ?\DateTimeImmutable {
                return null;
            }

            public function isSolarDefined(Coordinates $coordinates, CivilDate $date, mixed $elevationM): bool
            {
                return true;
            }
        };
    }
}
