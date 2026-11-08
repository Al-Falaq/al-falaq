<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Astro;

use AlFalaq\AlFalaq\Astro\Services\GeodesyEngine;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;
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

    public function testInverseRejectsBadInput(): void
    {
        $bad = [
            'lat-91' => [[91.0, 0.0], 'latitude'],
            'lat-minus-91' => [[-91.0, 0.0], 'latitude'],
            'lat-nan' => [[NAN, 0.0], 'latitude'],
            'lon-inf' => [[0.0, INF], 'longitude'],
            'lon-181' => [[0.0, 181.0], 'longitude'],
        ];
        $engine = new GeodesyEngine();

        foreach ($bad as $name => [$point, $keyword]) {
            try {
                $engine->inverse($point[0], $point[1], 21.4225241, 39.8261818);

                self::fail(sprintf('%s: expected ValidationException.', $name));
            } catch (ValidationException $e) {
                self::assertStringContainsStringIgnoringCase($keyword, $e->getMessage(), $name);
                self::assertStringContainsString('must be within', $e->getMessage(), $name);
            }
        }
    }

    public function testInverseRejectsBadSecondPoint(): void
    {
        $bad = [
            'lat2-91' => [[91.0, 0.0], 'latitude'],
            'lat2-minus-91' => [[-91.0, 0.0], 'latitude'],
            'lon2-nan' => [[0.0, NAN], 'longitude'],
        ];
        $engine = new GeodesyEngine();

        foreach ($bad as $name => [$point, $keyword]) {
            try {
                $engine->inverse(21.4225241, 39.8261818, $point[0], $point[1]);

                self::fail(sprintf('%s: expected ValidationException.', $name));
            } catch (ValidationException $e) {
                self::assertStringContainsStringIgnoringCase($keyword, $e->getMessage(), $name);
                self::assertStringContainsString('must be within', $e->getMessage(), $name);
            }
        }
    }
}
