<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Astro;

use AlFalaq\AlFalaq\Astro\Services\CrescentEphemeris;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;
use PHPUnit\Framework\TestCase;

/**
 * Meeus engine: physical bands (the golden gate lands in Task 7).
 *
 * Structural bands only; transcription correctness is gated by the Task 7
 * moon goldens plus the Example 47.a distance pin below.
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

    public function testTopocentricStaysInPhysicalBands(): void
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

    public function testLunarDistanceMatchesMeeusExample47a(): void
    {
        $geo = (new CrescentEphemeris())->lunarGeocentric(2448724.5);

        self::assertTrue(abs($geo['distanceKm'] - 368409.7) <= 1.0, 'lunar distanceKm vs Meeus Example 47.a (368409.7 km), precision 1 km');
    }

    public function testSiderealTimeUnwinds(): void
    {
        $lst = (new CrescentEphemeris())->siderealTime(2460734.5, 101.6869);

        self::assertGreaterThanOrEqual(0.0, $lst);
        self::assertLessThan(24.0, $lst);
    }
}
