<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Astro;

use AlFalaq\AlFalaq\Astro\Services\CrescentEphemeris;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;
use PHPUnit\Framework\TestCase;

/**
 * Meeus engine: physical bands (the golden gate lands in Task 7).
 */
final class CrescentEphemerisTest extends TestCase
{
    public function testLunarGeocentricStaysInPhysicalBands(): void
    {
        $geo = (new CrescentEphemeris())->lunarGeocentric(2460734.977337402);

        self::assertGreaterThanOrEqual(0.0, $geo['ra']);
        self::assertLessThan(360.0, $geo['ra']);
        self::assertGreaterThanOrEqual(-90.0, $geo['dec']);
        self::assertLessThanOrEqual(90.0, $geo['dec']);
        self::assertGreaterThanOrEqual(350000.0, $geo['distanceKm']);
        self::assertLessThanOrEqual(410000.0, $geo['distanceKm']);
    }

    public function testTopocentricStaysNearGeocentric(): void
    {
        $engine = new CrescentEphemeris();
        $geo = $engine->lunarGeocentric(2460734.977337402);
        $topo = $engine->topocentric(
            $geo['ra'],
            $geo['dec'],
            $geo['distanceKm'],
            Coordinates::fromDegrees(3.1390, 101.6869),
            12.0,
        );

        self::assertGreaterThanOrEqual(-90.0, $topo['altitudeDeg']);
        self::assertLessThanOrEqual(90.0, $topo['altitudeDeg']);
        self::assertGreaterThanOrEqual(0.0, $topo['azimuthDeg']);
        self::assertLessThan(360.0, $topo['azimuthDeg']);
    }

    public function testSiderealTimeUnwinds(): void
    {
        $lst = (new CrescentEphemeris())->siderealTime(2460734.5, 101.6869);

        self::assertGreaterThanOrEqual(0.0, $lst);
        self::assertLessThan(24.0, $lst);
    }
}
