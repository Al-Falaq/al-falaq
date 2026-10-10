<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Wiring;

use AlFalaq\AlFalaq\Prayer\Contracts\NextPrayerResolverInterface;
use AlFalaq\AlFalaq\Prayer\Contracts\PrayerTimesCalculatorInterface;
use AlFalaq\AlFalaq\Prayer\Contracts\TimetableServiceInterface;
use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Enums\HighLatitudeRule;
use AlFalaq\AlFalaq\Prayer\Enums\Madhab;
use AlFalaq\AlFalaq\Prayer\Enums\PolarCircleRule;
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
}
