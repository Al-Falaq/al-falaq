<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Qibla;

use AlFalaq\AlFalaq\Qibla\Services\QiblaCalculator;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;
use PHPUnit\Framework\TestCase;

/**
 * Service wiring: coercion, guards, result construction (no trig here).
 */
final class QiblaCalculatorTest extends TestCase
{
    public function testDefaultCalculatorMatchesRaleighGolden(): void
    {
        $result = QiblaCalculator::default()->calculate(Coordinates::fromDegrees(35.7750, -78.6336));

        self::assertTrue(abs($result->direction - 55.825083) <= 1e-6);
        self::assertTrue(abs($result->distanceKm - 10943.598) <= 1e-3);
    }

    public function testSphericalTinyNegativeAzimuthDoesNotWindTo360(): void
    {
        // Same hazard as the WGS84 path below: (-30.0, 39.82618180000001) is
        // one double (ulp 7.1e-15) east of Makkah's meridian, so the raw
        // Todhunter bearing is ≈ -1.2e-14°, and fmod + 360 rounds to exactly
        // 360.0 (ulp(360) ≈ 5.7e-14), which QiblaResult rejects. The engine's
        // re-clamp turns that into 0.0. Without it this throws
        // ValidationException.
        $result = QiblaCalculator::default()->calculate(Coordinates::fromDegrees(-30.0, 39.82618180000001));

        self::assertSame(0.0, $result->direction);
        self::assertTrue(is_finite($result->distanceKm));
        self::assertGreaterThan(0.0, $result->distanceKm);
    }

    public function testArrayPairInputEqualsVoInput(): void
    {
        $fromVo = QiblaCalculator::default()->calculate(Coordinates::fromDegrees(51.5074, -0.1278));
        $fromArray = QiblaCalculator::default()->calculate([51.5074, -0.1278]);

        self::assertSame($fromVo->direction, $fromArray->direction);
        self::assertSame($fromVo->distanceKm, $fromArray->distanceKm);

        // Distinct city pinned to goldens: proves the array pair is honored,
        // not ignored or hardcoded (London 118.987219° / 4793.778 km).
        self::assertTrue(abs($fromArray->direction - 118.987219) <= 1e-6);
        self::assertTrue(abs($fromArray->distanceKm - 4793.778) <= 1e-3);
    }

    public function testWgs84RaleighMatchesOracle(): void
    {
        $result = QiblaCalculator::wgs84()->calculate(Coordinates::fromDegrees(35.7750, -78.6336));

        self::assertEqualsWithDelta(55.739246718, $result->direction, 1e-6);
        self::assertEqualsWithDelta(10961.845992388, $result->distanceKm, 1e-3);
    }

    public function testWgs84MakkahSelfClampsToZero(): void
    {
        $result = QiblaCalculator::wgs84()->calculate(Coordinates::fromDegrees(21.4225241, 39.8261818));

        self::assertSame(0.0, $result->direction);
        self::assertSame(0.0, $result->distanceKm);
    }

    public function testWgs84AntipodeStaysFinite(): void
    {
        $result = QiblaCalculator::wgs84()->calculate(Coordinates::fromDegrees(-21.4225241, -140.1738182));

        self::assertTrue($result->direction >= 0.0 && $result->direction < 360.0);
        self::assertTrue(is_finite($result->distanceKm) && $result->distanceKm > 19000.0);
    }

    public function testWgs84NegativeAzimuthUnwinds(): void
    {
        // Pin oracle KL azimuth is negative (-67.557623661); the wired path
        // must surface it unwound. Verify the expectation first:
        // php -r 'echo 360 - 67.557623661, PHP_EOL;'
        $result = QiblaCalculator::wgs84()->calculate(Coordinates::fromDegrees(3.1390, 101.6869));

        self::assertEqualsWithDelta(292.442376339, $result->direction, 1e-6);
    }

    public function testWgs84TinyNegativeAzimuthDoesNotWindTo360(): void
    {
        // A real trigger, not hypothetical: (-30.0, 39.82618180000001) is one
        // double (ulp 7.1e-15) east of Makkah's meridian, so the raw engine
        // azimuth is -8.4823699697673616e-15°. Unwinding gives
        // fmod(-8.48e-15, 360) + 360 === 360.0 exactly (ulp(360) is 5.7e-14),
        // which QiblaResult rejects with ValidationException. The strategy's
        // re-clamp turns that into 0.0. Verified: drop the clamp and this
        // throws.
        $result = QiblaCalculator::wgs84()->calculate(Coordinates::fromDegrees(-30.0, 39.82618180000001));

        self::assertSame(0.0, $result->direction);
        self::assertTrue(is_finite($result->distanceKm));
        self::assertGreaterThan(0.0, $result->distanceKm);
    }
}
