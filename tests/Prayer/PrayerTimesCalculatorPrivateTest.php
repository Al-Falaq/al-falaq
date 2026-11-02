<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Prayer;

use AlFalaq\AlFalaq\Astro\Services\MeanSolarCalculator;
use AlFalaq\AlFalaq\Prayer\Contracts\HighLatitudeCorrection;
use AlFalaq\AlFalaq\Prayer\Contracts\MaghribRule;
use AlFalaq\AlFalaq\Prayer\Contracts\PolarCircleHandler;
use AlFalaq\AlFalaq\Prayer\Contracts\TwilightModel;
use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Enums\HighLatitudeRule;
use AlFalaq\AlFalaq\Prayer\Enums\Madhab;
use AlFalaq\AlFalaq\Prayer\Enums\PolarCircleRule;
use AlFalaq\AlFalaq\Prayer\Enums\Prayer;
use AlFalaq\AlFalaq\Prayer\Services\PrayerTimesCalculator;
use AlFalaq\AlFalaq\Prayer\Strategies\AngleIsha;
use AlFalaq\AlFalaq\Prayer\Strategies\AngleMaghrib;
use AlFalaq\AlFalaq\Prayer\Strategies\MiddleOfNightCorrection;
use AlFalaq\AlFalaq\Prayer\Strategies\MinutesAfterSunsetMaghrib;
use AlFalaq\AlFalaq\Prayer\Strategies\NearestLatitudeHandler;
use AlFalaq\AlFalaq\Prayer\Strategies\SeasonAdjustedTwilight;
use AlFalaq\AlFalaq\Prayer\ValueObjects\CalculationParameters;
use AlFalaq\AlFalaq\Prayer\ValueObjects\PrayerAdjustments;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;
use PHPUnit\Framework\TestCase;

/**
 * Direct coverage of PrayerTimesCalculator private methods via reflection.
 */
final class PrayerTimesCalculatorPrivateTest extends TestCase
{
    /** @param array<int, mixed> $args */
    private function invokeOn(object $instance, string $method, array $args): mixed
    {
        $ref = new \ReflectionMethod($instance, $method);

        return $ref->invokeArgs($instance, $args);
    }

    private function calculator(): PrayerTimesCalculator
    {
        return new PrayerTimesCalculator(
            new MeanSolarCalculator(),
            new MiddleOfNightCorrection(),
            new NearestLatitudeHandler(),
            new SeasonAdjustedTwilight(),
        );
    }

    public function testAdjustedViaReflection(): void
    {
        $calc = $this->calculator();
        $base = new \DateTimeImmutable('2025-01-15 12:00:00', new \DateTimeZone('UTC'));
        $eff = PrayerAdjustments::empty()->with(Prayer::DHUHR, 5);

        $result = $this->invokeOn($calc, 'adjusted', [$base, $eff, Prayer::DHUHR]);
        self::assertInstanceOf(\DateTimeImmutable::class, $result);
        self::assertSame('2025-01-15 12:05:00', $result->format('Y-m-d H:i:s'));
    }

    public function testMaghribBaseViaReflection(): void
    {
        $calc = $this->calculator();
        $sunset = new \DateTimeImmutable('2025-01-15 11:21:00', new \DateTimeZone('UTC'));
        $rule = new AngleMaghrib(6.0);

        $result = $this->invokeOn($calc, 'maghribBase', [
            Coordinates::fromDegrees(3.1390, 101.6869),
            CivilDate::fromYmd(2025, 1, 15),
            0.0,
            $rule,
            $sunset,
        ]);
        self::assertInstanceOf(\DateTimeImmutable::class, $result);
        // Sunset 11:21 + 6° depression ≈ 11:43:38
        self::assertSame('2025-01-15 11:43:38', $result->format('Y-m-d H:i:s'));
    }

    public function testMaghribBaseMinutesAfterSunsetViaReflection(): void
    {
        $calc = $this->calculator();
        $sunset = new \DateTimeImmutable('2025-01-15 11:21:00', new \DateTimeZone('UTC'));
        $rule = new MinutesAfterSunsetMaghrib(3);

        $result = $this->invokeOn($calc, 'maghribBase', [
            Coordinates::fromDegrees(3.1390, 101.6869),
            CivilDate::fromYmd(2025, 1, 15),
            0.0,
            $rule,
            $sunset,
        ]);
        self::assertInstanceOf(\DateTimeImmutable::class, $result);
        // Sunset 11:21 + 3 minutes = 11:24
        self::assertSame('2025-01-15 11:24:00', $result->format('Y-m-d H:i:s'));
    }
}
