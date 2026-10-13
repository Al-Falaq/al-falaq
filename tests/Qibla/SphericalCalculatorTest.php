<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Qibla;

use AlFalaq\AlFalaq\Qibla\Contracts\GeodesyCalculator;
use AlFalaq\AlFalaq\Qibla\Strategies\SphericalCalculator;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;
use PHPUnit\Framework\TestCase;

/**
 * Spherical model rules: engine delegation + Makkah-self clamp.
 */
final class SphericalCalculatorTest extends TestCase
{
    public function testRaleighDelegatesToEngineGoldens(): void
    {
        $solved = (new SphericalCalculator())->inverse(Coordinates::fromDegrees(35.7750, -78.6336));

        self::assertTrue(abs($solved['direction'] - 55.825083) <= 1e-6);
        self::assertTrue(abs($solved['distanceKm'] - 10943.598) <= 1e-3);
    }

    public function testMakkahSelfClampsToZeroBearingAndZeroDistance(): void
    {
        $solved = (new SphericalCalculator())->inverse(
            Coordinates::fromDegrees(GeodesyCalculator::MAKKAH_LATITUDE, GeodesyCalculator::MAKKAH_LONGITUDE),
        );

        self::assertSame(0.0, $solved['direction']);
        self::assertSame(0.0, $solved['distanceKm']);
    }

    public function testAntipodeIsFiniteWithoutThrowing(): void
    {
        $solved = (new SphericalCalculator())->inverse(Coordinates::fromDegrees(-21.4225241, -140.1738182));

        self::assertTrue(is_finite($solved['direction']));
        self::assertGreaterThanOrEqual(0.0, $solved['direction']);
        self::assertLessThan(360.0, $solved['direction']);
        self::assertTrue(abs($solved['distanceKm'] - 20015.114) <= 1e-3);
    }
}
