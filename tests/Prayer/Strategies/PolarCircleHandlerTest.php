<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Prayer\Strategies;

use AlFalaq\AlFalaq\Astro\Services\MeanSolarCalculator;
use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Enums\PolarCircleRule;
use AlFalaq\AlFalaq\Prayer\Enums\Prayer;
use AlFalaq\AlFalaq\Prayer\Services\PrayerTimesCalculator;
use AlFalaq\AlFalaq\Prayer\Strategies\MakkahHandler;
use AlFalaq\AlFalaq\Prayer\Strategies\NearestDayHandler;
use AlFalaq\AlFalaq\Prayer\Strategies\NearestLatitudeHandler;
use AlFalaq\AlFalaq\Prayer\Strategies\ThrowHandler;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\AstronomicalException;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;
use PHPUnit\Framework\TestCase;

/**
 * Polar-circle fallback strategies (pin _nearest_* verbatim).
 */
final class PolarCircleHandlerTest extends TestCase
{
    public function testNearestLatitudeMatchesPin(): void
    {
        $probe = fn(Coordinates $c, CivilDate $d): bool => (new MeanSolarCalculator())->isSolarDefined($c, $d, 0.0);

        $resolved = (new NearestLatitudeHandler())->resolve(
            Coordinates::fromDegrees(69.6496, 18.9553),
            CivilDate::fromYmd(2025, 6, 21),
            $probe,
        );

        self::assertEqualsWithDelta(65.22818986592176, $resolved['coordinates']->latitude, 1e-9);
        self::assertSame(18.9553, $resolved['coordinates']->longitude);
        self::assertTrue($resolved['date']->equals(CivilDate::fromYmd(2025, 6, 21)));
    }

    public function testNearestDayMatchesPin(): void
    {
        $probe = fn(Coordinates $c, CivilDate $d): bool => (new MeanSolarCalculator())->isSolarDefined($c, $d, 0.0);

        $resolved = (new NearestDayHandler())->resolve(
            Coordinates::fromDegrees(69.6496, 18.9553),
            CivilDate::fromYmd(2025, 12, 15),
            $probe,
        );

        self::assertTrue($resolved['date']->equals(CivilDate::fromYmd(2025, 11, 26)));
    }

    public function testMakkahSubstitutesPinConstant(): void
    {
        $probe = fn(Coordinates $c, CivilDate $d): bool => true;

        $resolved = (new MakkahHandler())->resolve(
            Coordinates::fromDegrees(69.6496, 18.9553),
            CivilDate::fromYmd(2025, 12, 15),
            $probe,
        );

        self::assertSame(21.4225241, $resolved['coordinates']->latitude);
        self::assertSame(39.8261818, $resolved['coordinates']->longitude);
    }

    public function testThrowEchoesInput(): void
    {
        $probe = fn(Coordinates $c, CivilDate $d): bool => false;
        $coords = Coordinates::fromDegrees(69.6496, 18.9553);
        $date = CivilDate::fromYmd(2025, 12, 15);

        $resolved = (new ThrowHandler())->resolve($coords, $date, $probe);

        self::assertTrue($resolved['coordinates']->equals($coords));
        self::assertTrue($resolved['date']->equals($date));
    }

    public function testPoleExhaustionThrows(): void
    {
        $probe = fn(Coordinates $c, CivilDate $d): bool => (new MeanSolarCalculator())->isSolarDefined($c, $d, 0.0);

        $this->expectException(AstronomicalException::class);
        (new NearestDayHandler())->resolve(
            Coordinates::fromDegrees(90.0, 0.0),
            CivilDate::fromYmd(2025, 6, 21),
            $probe,
        );
    }

    public function testCalculatorTromsoeSummerMatchesGoldenRow(): void
    {
        // Oracle row: pin MWL, Tromsø 2025-06-21 (also in prayer_muslim_world_league.csv).
        $schedule = PrayerTimesCalculator::default()->calculate(
            Coordinates::fromDegrees(69.6496, 18.9553),
            CivilDate::fromYmd(2025, 6, 21),
            CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters(),
        );

        $expected = [
            'imsak' => '2025-06-20 22:36', 'fajr' => '2025-06-20 22:46',
            'sunrise' => '2025-06-20 23:35', 'syuruk' => '2025-06-20 23:35',
            'ishraq' => '2025-06-20 23:50', 'dhuha' => '2025-06-21 00:03',
            'dhuhr' => '2025-06-21 10:47', 'asr' => '2025-06-21 15:42',
            'maghrib' => '2025-06-21 21:57', 'isha' => '2025-06-21 22:46',
        ];

        foreach ($expected as $marker => $minute) {
            self::assertSame(
                $minute,
                $schedule->timeFor(Prayer::from($marker))->format('Y-m-d H:i'),
                "marker {$marker}",
            );
        }
    }

    public function testDefaultForCoversAllPolarHandlers(): void
    {
        $base = CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters();

        $day = PrayerTimesCalculator::defaultFor($base->withPolarCircleRule(PolarCircleRule::NEAREST_DAY));
        self::assertInstanceOf(NearestDayHandler::class, $day->polarCircleHandler());

        $makkah = PrayerTimesCalculator::defaultFor($base->withPolarCircleRule(PolarCircleRule::MAKKAH));
        self::assertInstanceOf(MakkahHandler::class, $makkah->polarCircleHandler());

        $none = PrayerTimesCalculator::defaultFor($base->withPolarCircleRule(PolarCircleRule::NONE));
        self::assertInstanceOf(ThrowHandler::class, $none->polarCircleHandler());
    }
}
