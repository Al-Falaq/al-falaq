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
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Night mode trace: the selected mode is reported beside the two instants.
 */
final class NightResolverModeTest extends TestCase
{
    /**
     * @param CalculationParameters $parameters Configuration to resolve with.
     * @param string $expected Expected mode.
     */
    #[DataProvider('modeProvider')]
    public function testResolveReportsTheSelectedMode(CalculationParameters $parameters, string $expected): void
    {
        $temps = self::resolver()->resolve(
            Coordinates::fromDegrees(3.1390, 101.6869),
            CivilDate::fromYmd(2025, 1, 15),
            0.0,
            $parameters,
            self::day(),
            43200,
        );

        self::assertSame($expected, $temps['mode']);
    }

    /**
     * @return iterable<string, array{CalculationParameters, string}>
     */
    public static function modeProvider(): iterable
    {
        yield 'portions (MWL / middle of night)' => [
            CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters(),
            'portions',
        ];
        yield 'none (NONE rule, no cap)' => [self::noneParameters(), 'none'];
        yield 'seasonal (MSC method)' => [
            CalculationMethod::MOON_SIGHTING_COMMITTEE->defaultParameters(),
            'seasonal',
        ];
        yield 'seasonal (SEASONAL_TWILIGHT rule)' => [
            CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters()
                ->withHighLatitudeRule(HighLatitudeRule::SEASONAL_TWILIGHT),
            'seasonal',
        ];
    }

    public function testModeIsReturnedBesideBothInstants(): void
    {
        $temps = self::resolver()->resolve(
            Coordinates::fromDegrees(3.1390, 101.6869),
            CivilDate::fromYmd(2025, 1, 15),
            0.0,
            CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters(),
            self::day(),
            43200,
        );

        self::assertSame(['fajr', 'isha', 'mode'], array_keys($temps));
        self::assertInstanceOf(\DateTimeImmutable::class, $temps['fajr']);
        self::assertInstanceOf(\DateTimeImmutable::class, $temps['isha']);
        self::assertSame('UTC', $temps['fajr']->getTimezone()->getName());
        self::assertSame('UTC', $temps['isha']->getTimezone()->getName());
    }

    private static function noneParameters(): CalculationParameters
    {
        return CalculationParameters::create(
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
    }

    private static function resolver(): NightResolver
    {
        return new NightResolver(
            self::solar(),
            new MiddleOfNightCorrection(),
            new SeasonAdjustedTwilight(),
        );
    }

    private static function day(): SolarTime
    {
        $utc = new \DateTimeZone('UTC');

        return new SolarTime(
            new \DateTimeImmutable('2025-01-15 12:00:00', $utc),
            new \DateTimeImmutable('2025-01-15 06:00:00', $utc),
            new \DateTimeImmutable('2025-01-15 18:00:00', $utc),
        );
    }

    private static function solar(): SolarDayEngine
    {
        $utc = new \DateTimeZone('UTC');
        $fajr = new \DateTimeImmutable('2025-01-15 05:00:00', $utc);
        $isha = new \DateTimeImmutable('2025-01-15 19:00:00', $utc);
        $day = self::day();

        return new class ($day, $fajr, $isha) implements SolarDayEngine {
            public function __construct(
                private readonly SolarTime $day,
                private readonly \DateTimeImmutable $fajr,
                private readonly \DateTimeImmutable $isha,
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
            ): \DateTimeImmutable {
                return $afterTransit ? $this->isha : $this->fajr;
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
