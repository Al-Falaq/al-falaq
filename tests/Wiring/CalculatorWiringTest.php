<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Wiring;

use AlFalaq\AlFalaq\Astro\Contracts\SolarDayEngine;
use AlFalaq\AlFalaq\Astro\Services\MeanSolarCalculator;
use AlFalaq\AlFalaq\Prayer\Contracts\NextPrayerResolverInterface;
use AlFalaq\AlFalaq\Prayer\Contracts\PrayerTimesCalculatorInterface;
use AlFalaq\AlFalaq\Prayer\Contracts\TimetableServiceInterface;
use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Enums\HighLatitudeRule;
use AlFalaq\AlFalaq\Prayer\Enums\Madhab;
use AlFalaq\AlFalaq\Prayer\Enums\PolarCircleRule;
use AlFalaq\AlFalaq\Prayer\Enums\Prayer;
use AlFalaq\AlFalaq\Prayer\Services\NextPrayerResolver;
use AlFalaq\AlFalaq\Prayer\Services\PrayerTimesCalculator;
use AlFalaq\AlFalaq\Prayer\Services\TimetableService;
use AlFalaq\AlFalaq\Prayer\Strategies\AngleIsha;
use AlFalaq\AlFalaq\Prayer\Strategies\MakkahHandler;
use AlFalaq\AlFalaq\Prayer\Strategies\MiddleOfNightCorrection;
use AlFalaq\AlFalaq\Prayer\Strategies\MinutesAfterSunsetMaghrib;
use AlFalaq\AlFalaq\Prayer\Strategies\NearestLatitudeHandler;
use AlFalaq\AlFalaq\Prayer\Strategies\SeasonAdjustedTwilight;
use AlFalaq\AlFalaq\Prayer\Strategies\SeventhOfNightCorrection;
use AlFalaq\AlFalaq\Prayer\ValueObjects\CalculationParameters;
use AlFalaq\AlFalaq\Prayer\ValueObjects\PrayerAdjustments;
use AlFalaq\AlFalaq\Qibla\Services\QiblaCalculator;
use AlFalaq\AlFalaq\Qibla\Strategies\SphericalCalculator;
use AlFalaq\AlFalaq\Qibla\Strategies\Wgs84KarneyCalculator;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;
use PHPUnit\Framework\TestCase;

/**
 * Manual wiring smoke test: every service is constructed with plain
 * `new` / named factories. No container, no service provider, no config
 * files — the library must be usable in a single expression.
 */
final class CalculatorWiringTest extends TestCase
{
    public function testQiblaDefaultWiresSphericalGeodesy(): void
    {
        self::assertInstanceOf(SphericalCalculator::class, QiblaCalculator::default()->geodesy());
    }

    public function testQiblaWgs84WiresKarneyGeodesy(): void
    {
        self::assertInstanceOf(Wgs84KarneyCalculator::class, QiblaCalculator::wgs84()->geodesy());
    }

    public function testPrayerCalculatorSatisfiesItsContract(): void
    {
        self::assertInstanceOf(PrayerTimesCalculatorInterface::class, PrayerTimesCalculator::default());
    }

    public function testDefaultCalculatorWiresRealDayEngineBehindPort(): void
    {
        $solar = PrayerTimesCalculator::default()->solar();

        self::assertInstanceOf(SolarDayEngine::class, $solar);
        self::assertInstanceOf(MeanSolarCalculator::class, $solar);
    }

    public function testResolverAndTimetableSatisfyTheirContracts(): void
    {
        self::assertInstanceOf(NextPrayerResolverInterface::class, new NextPrayerResolver());
        self::assertInstanceOf(TimetableServiceInterface::class, new TimetableService());
    }

    public function testDefaultForMapsRulesToStrategies(): void
    {
        $mwl = CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters();
        $calc = PrayerTimesCalculator::defaultFor($mwl);
        self::assertInstanceOf(MiddleOfNightCorrection::class, $calc->highLatitudeCorrection());
        self::assertInstanceOf(NearestLatitudeHandler::class, $calc->polarCircleHandler());
        self::assertInstanceOf(SeasonAdjustedTwilight::class, $calc->twilightModel());

        $tehran = CalculationMethod::TEHRAN->defaultParameters();
        self::assertInstanceOf(NearestLatitudeHandler::class, PrayerTimesCalculator::defaultFor($tehran)->polarCircleHandler());

        $none = CalculationParameters::create(
            CalculationMethod::NONE,
            Madhab::SHAFI,
            HighLatitudeRule::SEVENTH_OF_NIGHT,
            PolarCircleRule::MAKKAH,
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
        $custom = PrayerTimesCalculator::defaultFor($none);
        self::assertInstanceOf(SeventhOfNightCorrection::class, $custom->highLatitudeCorrection());
        self::assertInstanceOf(MakkahHandler::class, $custom->polarCircleHandler());
    }

    public function testPrayerSnippetCalculatesTenUtcMarkers(): void
    {
        // Mirrors docs/integrations.md "Prayer times": the snippet must run,
        // not just wire — ten UTC markers in documented key order.
        $calculator = PrayerTimesCalculator::default();
        $params = CalculationMethod::SINGAPORE->defaultParameters();
        $schedule = $calculator->calculate(
            Coordinates::fromDegrees(3.1390, 101.6869),
            CivilDate::fromYmd(2025, 3, 1),
            $params,
        );

        self::assertSame(
            ['imsak', 'fajr', 'sunrise', 'syuruk', 'ishraq', 'dhuha', 'dhuhr', 'asr', 'maghrib', 'isha'],
            array_keys($schedule->all()),
        );

        foreach ($schedule->all() as $instant) {
            self::assertSame('UTC', $instant->getTimezone()->getName());
        }
    }

    public function testAdvancedSnippetConstructsWorkingCalculator(): void
    {
        // Mirrors docs/integrations.md advanced wiring: explicit 4-arg
        // construction must satisfy the constructor contract and calculate.
        $calculator = new PrayerTimesCalculator(
            new MeanSolarCalculator(),
            new MiddleOfNightCorrection(),
            new NearestLatitudeHandler(),
            new SeasonAdjustedTwilight(),
        );
        $schedule = $calculator->calculate(
            Coordinates::fromDegrees(3.1390, 101.6869),
            CivilDate::fromYmd(2025, 3, 1),
            CalculationMethod::SINGAPORE->defaultParameters(),
        );

        self::assertSame('UTC', $schedule->timeFor(Prayer::FAJR)->getTimezone()->getName());
    }

    public function testQiblaSnippetCalculatesBothModels(): void
    {
        // Mirrors docs/integrations.md "Qibla": both factories calculate.
        $spherical = QiblaCalculator::default();
        $precise = QiblaCalculator::wgs84();
        $result = $spherical->calculate(Coordinates::fromDegrees(3.1390, 101.6869));
        $preciseResult = $precise->calculate(Coordinates::fromDegrees(3.1390, 101.6869));

        foreach ([$result, $preciseResult] as $answer) {
            self::assertGreaterThanOrEqual(0.0, $answer->direction);
            self::assertLessThan(360.0, $answer->direction);
            self::assertGreaterThan(0.0, $answer->distanceKm);
            self::assertTrue(is_finite($answer->distanceKm));
        }
    }

    public function testClockSnippetResolvesNextPrayer(): void
    {
        // Mirrors docs/integrations.md clock input: explicit UTC now resolves.
        $calculator = PrayerTimesCalculator::default();
        $params = CalculationMethod::SINGAPORE->defaultParameters();
        $coords = Coordinates::fromDegrees(3.1390, 101.6869);
        $today = $calculator->calculate($coords, CivilDate::fromYmd(2025, 3, 1), $params);
        $tomorrow = $calculator->calculate($coords, CivilDate::fromYmd(2025, 3, 2), $params);

        $next = (new NextPrayerResolver())->resolve(
            $today,
            $tomorrow,
            new \DateTimeImmutable('2025-03-01 12:00:00', new \DateTimeZone('UTC')),
        );

        self::assertInstanceOf(Prayer::class, $next->prayer);
        self::assertSame('UTC', $next->at->getTimezone()->getName());
        self::assertGreaterThan(0, $next->inSeconds);
    }

    public function testOutputShapesMatchDocumentedSchema(): void
    {
        // Mirrors docs/integrations.md output shapes: keys, order, formats.
        $schedule = PrayerTimesCalculator::default()->calculate(
            Coordinates::fromDegrees(3.1390, 101.6869),
            CivilDate::fromYmd(2025, 3, 1),
            CalculationMethod::SINGAPORE->defaultParameters(),
        );
        $keys = ['imsak', 'fajr', 'sunrise', 'syuruk', 'ishraq', 'dhuha', 'dhuhr', 'asr', 'maghrib', 'isha'];

        self::assertSame($keys, array_keys($schedule->all()));
        self::assertSame($keys, array_keys($schedule->toArray()));

        foreach ($schedule->toArray() as $iso) {
            self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/', $iso);
        }

        $zoned = $schedule->forZone(new \DateTimeZone('Asia/Kuala_Lumpur'))->toArray();

        self::assertSame($keys, array_keys($zoned));

        foreach ($zoned as $iso) {
            self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+08:00$/', $iso);
        }
    }
}
