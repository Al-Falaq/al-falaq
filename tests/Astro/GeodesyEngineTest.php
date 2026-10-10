<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Astro;

use AlFalaq\AlFalaq\Astro\Services\GeodesyEngine;
use PHPUnit\Framework\TestCase;

/**
 * Todhunter bearing + haversine distance against committed goldens.
 */
final class GeodesyEngineTest extends TestCase
{
    public function testRaleighInverseMatchesGolden(): void
    {
        $raw = (new GeodesyEngine())->inverse(35.7750, -78.6336, 21.4225241, 39.8261818);

        self::assertTrue(abs($raw['direction'] - 55.825083) <= 1e-6, 'bearing, got ' . $raw['direction']);
        self::assertTrue(abs($raw['distanceKm'] - 10943.598) <= 1e-3, 'distance, got ' . $raw['distanceKm']);
    }

    public function testMadinahShortDistanceMatchesGolden(): void
    {
        $raw = (new GeodesyEngine())->inverse(24.5247, 39.5692, 21.4225241, 39.8261818);

        self::assertTrue(abs($raw['direction'] - 175.587856) <= 1e-6, 'bearing, got ' . $raw['direction']);
        self::assertTrue(abs($raw['distanceKm'] - 345.948) <= 1e-3, 'distance, got ' . $raw['distanceKm']);
    }

    public function testSelfPointIsFiniteWithNearZeroDistance(): void
    {
        $raw = (new GeodesyEngine())->inverse(21.4225241, 39.8261818, 21.4225241, 39.8261818);

        self::assertTrue(is_finite($raw['direction']));
        self::assertGreaterThanOrEqual(0.0, $raw['direction']);
        self::assertLessThan(360.0, $raw['direction']);
        self::assertTrue($raw['distanceKm'] >= 0.0 && $raw['distanceKm'] < 1e-6);
    }
}
