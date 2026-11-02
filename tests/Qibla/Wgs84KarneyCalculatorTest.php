<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Qibla;

use AlFalaq\AlFalaq\Astro\Services\KarneyInverse;
use AlFalaq\AlFalaq\Qibla\Strategies\Wgs84KarneyCalculator;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;
use PHPUnit\Framework\TestCase;

/**
 * WGS84 Karney geodesy: inverse bearing + distance, Makkah-self clamp.
 */
final class Wgs84KarneyCalculatorTest extends TestCase
{
    public function testInverseFromKualaLumpurToMakkah(): void
    {
        $calc = new Wgs84KarneyCalculator();
        $result = $calc->inverse(Coordinates::fromDegrees(3.1390, 101.6869));

        self::assertTrue($result['direction'] > 0.0 && $result['direction'] < 360.0);
        self::assertTrue($result['distanceKm'] > 6000.0 && $result['distanceKm'] < 7000.0);
        self::assertTrue(is_finite($result['direction']));
        self::assertTrue(is_finite($result['distanceKm']));
    }

    public function testInverseFromMakkahToItselfClampsToZero(): void
    {
        $calc = new Wgs84KarneyCalculator();
        $result = $calc->inverse(Coordinates::fromDegrees(21.4225241, 39.8261818));

        self::assertSame(0.0, $result['direction']);
        self::assertTrue($result['distanceKm'] < 1e-6);
    }

    public function testInverseFromAntipodeOfMakkah(): void
    {
        $calc = new Wgs84KarneyCalculator();
        $result = $calc->inverse(Coordinates::fromDegrees(-21.4225241, 39.8261818 - 180.0));

        self::assertTrue(is_finite($result['direction']));
        self::assertTrue($result['distanceKm'] > 19000.0);
        self::assertTrue($result['distanceKm'] < 21000.0);
    }

    public function testInverseUnwindsAzimuthIntoZeroTo360(): void
    {
        $calc = new Wgs84KarneyCalculator();

        // Due west from a point east of Makkah: bearing should be ~270, unwound to [0,360).
        $result = $calc->inverse(Coordinates::fromDegrees(21.4225241, 40.0));

        self::assertTrue($result['direction'] >= 0.0 && $result['direction'] < 360.0);
    }

    public function testDefaultConstructorUsesFreshKarneyInverse(): void
    {
        $calc = new Wgs84KarneyCalculator();

        self::assertInstanceOf(Wgs84KarneyCalculator::class, $calc);
        $result = $calc->inverse(Coordinates::fromDegrees(0.0, 0.0));
        // Any valid input produces finite, in-range output from the default engine.
        self::assertTrue(is_finite($result['direction']));
        self::assertTrue(is_finite($result['distanceKm']));
        self::assertTrue($result['direction'] >= 0.0 && $result['direction'] < 360.0);
    }

    public function testExplicitEngineConstructor(): void
    {
        $engine = new KarneyInverse();
        $calc = new Wgs84KarneyCalculator($engine);

        $result = $calc->inverse(Coordinates::fromDegrees(3.1390, 101.6869));
        self::assertTrue(is_finite($result['direction']));
        self::assertTrue(is_finite($result['distanceKm']));
    }
}
