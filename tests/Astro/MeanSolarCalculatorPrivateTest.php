<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Astro;

use AlFalaq\AlFalaq\Astro\Services\MeanSolarCalculator;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;
use PHPUnit\Framework\TestCase;

/**
 * Direct coverage of MeanSolarCalculator private methods via reflection.
 * pcov does not track methods called via $this-> from within the same class.
 */
final class MeanSolarCalculatorPrivateTest extends TestCase
{
    /** @param array<int, mixed> $args */
    private function invokeOn(object $instance, string $method, array $args): mixed
    {
        $ref = new \ReflectionMethod($instance, $method);
        $ref->setAccessible(true);

        return $ref->invokeArgs($instance, $args);
    }

    private function calculator(): MeanSolarCalculator
    {
        return new MeanSolarCalculator();
    }

    public function testDeclinationFromViaReflection(): void
    {
        $calc = $this->calculator();
        $result = $this->invokeOn($calc, 'declinationFrom', [0.0, 0.0]);
        self::assertIsFloat($result);
    }

    public function testClosestAngleViaReflection(): void
    {
        $calc = $this->calculator();
        $result = $this->invokeOn($calc, 'closestAngle', [45.0]);
        self::assertSame(45.0, $result);

        $result = $this->invokeOn($calc, 'closestAngle', [200.0]);
        self::assertEqualsWithDelta(-160.0, $result, 1e-12);
    }

    public function testSolarAltitudeViaReflection(): void
    {
        $calc = $this->calculator();
        $result = $this->invokeOn($calc, 'solarAltitude', [0.0]);
        self::assertIsFloat($result);
    }

    public function testTransitHoursViaReflection(): void
    {
        $calc = $this->calculator();
        $result = $this->invokeOn($calc, 'transitHours', [0.5, 0.0, 0.0, 0.0, 0.0, 0.0]);
        self::assertIsFloat($result);
    }

    public function testHourAngleHoursViaReflection(): void
    {
        $calc = $this->calculator();
        $result = $this->invokeOn($calc, 'hourAngleHours', [
            0.5, -0.8333, 3.1390, 101.6869, false, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0,
        ]);
        self::assertIsFloat($result);
    }

    public function testHoursToUtcViaReflection(): void
    {
        $calc = $this->calculator();
        $result = $this->invokeOn($calc, 'hoursToUtc', [CivilDate::fromYmd(2025, 1, 15), 5.5]);
        self::assertInstanceOf(\DateTimeImmutable::class, $result);
    }

    public function testAfternoonAngleViaReflection(): void
    {
        $calc = $this->calculator();
        $result = $this->invokeOn($calc, 'afternoonAngle', [3.1390, 0.0, 1.0]);
        self::assertIsFloat($result);
    }

    public function testAssertElevationViaReflection(): void
    {
        $calc = $this->calculator();
        $result = $this->invokeOn($calc, 'assertElevation', [100.0]);
        self::assertSame(100.0, $result);
    }

    public function testAssertShadowLengthViaReflection(): void
    {
        $calc = $this->calculator();
        $result = $this->invokeOn($calc, 'assertShadowLength', [1.5]);
        self::assertSame(1.5, $result);
    }

    public function testSolarTripletViaReflection(): void
    {
        $calc = $this->calculator();
        $result = $this->invokeOn($calc, 'solarTriplet', [2451545.0]);
        self::assertIsArray($result);
        self::assertArrayHasKey('ra', $result);
        self::assertArrayHasKey('dec', $result);
        self::assertArrayHasKey('st', $result);
    }
}
