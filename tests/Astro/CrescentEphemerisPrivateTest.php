<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Astro;

use AlFalaq\AlFalaq\Astro\Services\CrescentEphemeris;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;
use PHPUnit\Framework\TestCase;

/**
 * Direct coverage of CrescentEphemeris private methods via reflection.
 * pcov does not count methods called via $this-> from within the same class.
 */
final class CrescentEphemerisPrivateTest extends TestCase
{
    /** @param array<int, mixed> $args */
    private function invokeOn(object $instance, string $method, array $args): mixed
    {
        $ref = new \ReflectionMethod($instance, $method);
        $ref->setAccessible(true);

        return $ref->invokeArgs($instance, $args);
    }

    private function ephemeris(): CrescentEphemeris
    {
        return new CrescentEphemeris();
    }

    public function testGreenwichSiderealDegreesViaReflection(): void
    {
        $ephemeris = $this->ephemeris();
        $result = $this->invokeOn($ephemeris, 'greenwichSiderealDegrees', [2451545.0]);
        self::assertIsFloat($result);
        self::assertTrue(is_finite($result));
    }

    public function testSiderealDegreesViaReflection(): void
    {
        $ephemeris = $this->ephemeris();
        $result = $this->invokeOn($ephemeris, 'siderealDegrees', [2451545.0, 0.0]);
        self::assertIsFloat($result);
        self::assertTrue(is_finite($result));
    }

    public function testCorrectedHourAngleViaReflection(): void
    {
        $ephemeris = $this->ephemeris();
        $result = $this->invokeOn($ephemeris, 'correctedHourAngle', [
            0.5, -0.8333, Coordinates::fromDegrees(3.1390, 101.6869), true,
            100.0, 0.0, 0.1, 0.0, 0.0, 0.0, 0.0,
        ]);
        self::assertIsFloat($result);
    }

    public function testBodyAltitudeViaReflection(): void
    {
        $ephemeris = $this->ephemeris();
        $result = $this->invokeOn($ephemeris, 'bodyAltitude', [3.1390, 0.0, 0.0]);
        self::assertIsFloat($result);
        self::assertTrue(is_finite($result));
    }

    public function testFiniteGeoViaReflection(): void
    {
        $ephemeris = $this->ephemeris();
        $result = $this->invokeOn($ephemeris, 'finiteGeo', ['test', 0.0, 0.0, 1000.0]);
        self::assertIsArray($result);
        self::assertArrayHasKey('ra', $result);
        self::assertArrayHasKey('dec', $result);
        self::assertArrayHasKey('distanceKm', $result);
    }
}
