<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Prayer;

use AlFalaq\AlFalaq\Astro\Contracts\SolarDayEngine;
use AlFalaq\AlFalaq\Astro\ValueObjects\SolarTime;
use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Enums\HighLatitudeRule;
use AlFalaq\AlFalaq\Prayer\Enums\Madhab;
use AlFalaq\AlFalaq\Prayer\Enums\PolarCircleRule;
use AlFalaq\AlFalaq\Prayer\Services\NightResolver;
use AlFalaq\AlFalaq\Prayer\Strategies\AngleIsha;
use AlFalaq\AlFalaq\Prayer\Strategies\MiddleOfNightCorrection;
use AlFalaq\AlFalaq\Prayer\Strategies\MinutesAfterSunsetMaghrib;
use AlFalaq\AlFalaq\Prayer\Strategies\SeasonAdjustedTwilight;
use AlFalaq\AlFalaq\Prayer\ValueObjects\CalculationParameters;
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
