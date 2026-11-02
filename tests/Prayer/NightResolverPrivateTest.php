<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Prayer;

use AlFalaq\AlFalaq\Astro\Contracts\SolarDayEngine;
use AlFalaq\AlFalaq\Astro\ValueObjects\SolarTime;
use AlFalaq\AlFalaq\Prayer\Services\NightResolver;
use AlFalaq\AlFalaq\Prayer\Strategies\MiddleOfNightCorrection;
use AlFalaq\AlFalaq\Prayer\Strategies\SeasonAdjustedTwilight;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;
use PHPUnit\Framework\TestCase;

/**
 * Direct coverage of NightResolver private methods via reflection.
 * pcov does not track methods called via $this-> from within the same class.
 */
final class NightResolverPrivateTest extends TestCase
{
    /** @param array<int, mixed> $args */
    private function invokeOn(object $instance, string $method, array $args): mixed
    {
        $ref = new \ReflectionMethod($instance, $method);

        return $ref->invokeArgs($instance, $args);
    }

    private function buildResolver(): NightResolver
    {
        $utc = new \DateTimeZone('UTC');
        $day = new SolarTime(
            new \DateTimeImmutable('2025-01-15 12:00:00', $utc),
            new \DateTimeImmutable('2025-01-15 06:00:00', $utc),
            new \DateTimeImmutable('2025-01-15 18:00:00', $utc),
        );

        return new NightResolver(
            new class ($day) implements SolarDayEngine {
                public function __construct(private readonly SolarTime $day) {}

                public function declination(float $j): float
                {
                    return 0.0;
                }
                public function equationOfTime(float $j): float
                {
                    return 0.0;
                }
                public function solarTime(Coordinates $c, CivilDate $d, mixed $e): SolarTime
                {
                    return $this->day;
                }
                public function hourAngleFloat(Coordinates $c, CivilDate $d, mixed $e, float $a, bool $at): ?float
                {
                    return null;
                }
                public function hourAngle(Coordinates $c, CivilDate $d, mixed $e, float $a, bool $at): \DateTimeImmutable
                {
                    return new \DateTimeImmutable('2025-01-15 03:00:00', new \DateTimeZone('UTC'));
                }
                public function afternoon(Coordinates $c, CivilDate $d, mixed $e, mixed $s): ?\DateTimeImmutable
                {
                    return null;
                }
                public function isSolarDefined(Coordinates $c, CivilDate $d, mixed $e): bool
                {
                    return true;
                }
            },
            new MiddleOfNightCorrection(),
            new SeasonAdjustedTwilight(),
        );
    }

    public function testFajrTempViaReflection(): void
    {
        $resolver = $this->buildResolver();
        $utc = new \DateTimeZone('UTC');
        $day = new SolarTime(
            new \DateTimeImmutable('2025-01-15 12:00:00', $utc),
            new \DateTimeImmutable('2025-01-15 06:00:00', $utc),
            new \DateTimeImmutable('2025-01-15 18:00:00', $utc),
        );
        $params = \AlFalaq\AlFalaq\Prayer\ValueObjects\CalculationParameters::create(
            \AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod::NONE,
            \AlFalaq\AlFalaq\Prayer\Enums\Madhab::SHAFI,
            \AlFalaq\AlFalaq\Prayer\Enums\HighLatitudeRule::NONE,
            \AlFalaq\AlFalaq\Prayer\Enums\PolarCircleRule::NEAREST_LATITUDE,
            18.0,
            new \AlFalaq\AlFalaq\Prayer\Strategies\AngleIsha(17.0),
            new \AlFalaq\AlFalaq\Prayer\Strategies\MinutesAfterSunsetMaghrib(0),
            0,
            10,
            15,
            28,
            0.0,
            false,
            \AlFalaq\AlFalaq\Prayer\ValueObjects\PrayerAdjustments::empty(),
            \AlFalaq\AlFalaq\Prayer\ValueObjects\PrayerAdjustments::empty(),
        );

        $result = $this->invokeOn($resolver, 'fajrTemp', [
            Coordinates::fromDegrees(3.1390, 101.6869),
            CivilDate::fromYmd(2025, 1, 15),
            0.0,
            $params,
            $day,
            43200,
            null,
            false,
            false,
            ['morning' => 0, 'evening' => 0],
            0,
        ]);
        self::assertInstanceOf(\DateTimeImmutable::class, $result);
        // hourAngle returns 2025-01-15 03:00:00 UTC; midnight rule passes it through unchanged.
        self::assertSame('2025-01-15 03:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function testIshaTempViaReflection(): void
    {
        $resolver = $this->buildResolver();
        $utc = new \DateTimeZone('UTC');
        $day = new SolarTime(
            new \DateTimeImmutable('2025-01-15 12:00:00', $utc),
            new \DateTimeImmutable('2025-01-15 06:00:00', $utc),
            new \DateTimeImmutable('2025-01-15 18:00:00', $utc),
        );
        $params = \AlFalaq\AlFalaq\Prayer\ValueObjects\CalculationParameters::create(
            \AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod::NONE,
            \AlFalaq\AlFalaq\Prayer\Enums\Madhab::SHAFI,
            \AlFalaq\AlFalaq\Prayer\Enums\HighLatitudeRule::NONE,
            \AlFalaq\AlFalaq\Prayer\Enums\PolarCircleRule::NEAREST_LATITUDE,
            18.0,
            new \AlFalaq\AlFalaq\Prayer\Strategies\AngleIsha(17.0),
            new \AlFalaq\AlFalaq\Prayer\Strategies\MinutesAfterSunsetMaghrib(0),
            0,
            10,
            15,
            28,
            0.0,
            false,
            \AlFalaq\AlFalaq\Prayer\ValueObjects\PrayerAdjustments::empty(),
            \AlFalaq\AlFalaq\Prayer\ValueObjects\PrayerAdjustments::empty(),
        );

        $result = $this->invokeOn($resolver, 'ishaTemp', [
            Coordinates::fromDegrees(3.1390, 101.6869),
            CivilDate::fromYmd(2025, 1, 15),
            0.0,
            $params,
            $day,
            43200,
            null,
            false,
            false,
            ['morning' => 0, 'evening' => 0],
            0,
        ]);
        self::assertInstanceOf(\DateTimeImmutable::class, $result);
        // hourAngle returns 2025-01-15 03:00:00 UTC; midnight rule passes it through unchanged.
        self::assertSame('2025-01-15 03:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function testAddSecondsViaReflection(): void
    {
        $resolver = $this->buildResolver();
        $utc = new \DateTimeZone('UTC');
        $base = new \DateTimeImmutable('2025-01-15 12:00:00', $utc);

        $result = $this->invokeOn($resolver, 'addSeconds', [$base, 3600]);

        self::assertInstanceOf(\DateTimeImmutable::class, $result);
        self::assertSame('2025-01-15 13:00:00', $result->format('Y-m-d H:i:s'));
    }
}
