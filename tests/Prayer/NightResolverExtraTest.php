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
 * Additional NightResolver coverage for branches not reached by other tests.
 */
final class NightResolverExtraTest extends TestCase
{
    public function testPortionsPathWithReachableAngleTemp(): void
    {
        // Portions path (non-NONE, non-seasonal) where angle temp IS reachable
        // and gets clamped by the safe cap.
        $utc = new \DateTimeZone('UTC');
        $day = new SolarTime(
            new \DateTimeImmutable('2025-01-15 12:00:00', $utc),
            new \DateTimeImmutable('2025-01-15 06:00:00', $utc),
            new \DateTimeImmutable('2025-01-15 18:00:00', $utc),
        );
        $fajrTemp = new \DateTimeImmutable('2025-01-14 20:00:00', $utc); // before safe cap
        $resolver = new NightResolver(
            self::stubSolar($day, $fajrTemp),
            new MiddleOfNightCorrection(),
            new SeasonAdjustedTwilight(),
        );
        $coordinates = Coordinates::fromDegrees(3.1390, 101.6869);
        $date = CivilDate::fromYmd(2025, 1, 15);

        // MWL method, not SEASONAL_TWILIGHT, not NONE -> portions path
        $params = CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters();

        $temps = $resolver->resolve(
            $coordinates,
            $date,
            0.0,
            $params,
            $day,
            43200,
        );

        self::assertNotNull($temps['fajr']);
        self::assertNotNull($temps['isha']);
    }

    public function testIshaAnglePathReachable(): void
    {
        // Isha angle path (not interval, not MSC, not seasonal) where
        // hourAngle returns a valid result.
        $utc = new \DateTimeZone('UTC');
        $day = new SolarTime(
            new \DateTimeImmutable('2025-01-15 12:00:00', $utc),
            new \DateTimeImmutable('2025-01-15 06:00:00', $utc),
            new \DateTimeImmutable('2025-01-15 18:00:00', $utc),
        );
        $ishaTemp = new \DateTimeImmutable('2025-01-16 01:00:00', $utc);
        $resolver = new NightResolver(
            self::stubSolar($day, null, $ishaTemp),
            new MiddleOfNightCorrection(),
            new SeasonAdjustedTwilight(),
        );
        $coordinates = Coordinates::fromDegrees(3.1390, 101.6869);
        $date = CivilDate::fromYmd(2025, 1, 15);
        $params = CalculationParameters::create(
            CalculationMethod::NONE,
            Madhab::SHAFI,
            HighLatitudeRule::MIDDLE_OF_NIGHT,
            PolarCircleRule::NEAREST_LATITUDE,
            18.0,
            new AngleIsha(17.0), // angle-based Isha
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

        self::assertNotNull($temps['isha']);
        self::assertInstanceOf(\DateTimeImmutable::class, $temps['isha']);
    }

    /**
     * @return SolarDayEngine
     */
    private static function stubSolar(
        SolarTime $day,
        ?\DateTimeImmutable $angleTemp,
        ?\DateTimeImmutable $eveningTemp = null,
    ): SolarDayEngine {
        return new class ($day, $angleTemp, $eveningTemp ?? $angleTemp) implements SolarDayEngine {
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
            public function hourAngleFloat(Coordinates $coordinates, CivilDate $date, mixed $elevationM, float $angleDeg, bool $afterTransit): ?float
            {
                return null;
            }
            public function hourAngle(Coordinates $coordinates, CivilDate $date, mixed $elevationM, float $angleDeg, bool $afterTransit): ?\DateTimeImmutable
            {
                return $afterTransit ? $this->evening : $this->morning;
            }
            public function afternoon(Coordinates $coordinates, CivilDate $date, mixed $elevationM, mixed $shadowLength): ?\DateTimeImmutable
            {
                return null;
            }
            public function isSolarDefined(Coordinates $coordinates, CivilDate $date, mixed $elevationM): bool
            {
                return true;
            }
        };
    }
}
