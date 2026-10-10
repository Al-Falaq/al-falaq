<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Qibla;

use AlFalaq\AlFalaq\Qibla\Services\QiblaCalculator;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ConfigurationException;
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

    public function testMissingGeodesyThrowsConfigurationException(): void
    {
        $this->expectException(ConfigurationException::class);

        (new QiblaCalculator())->calculate(Coordinates::fromDegrees(35.7750, -78.6336));
    }

    public function testWgs84RemainsDeferred(): void
    {
        $this->expectException(\LogicException::class);

        QiblaCalculator::wgs84()->calculate(Coordinates::fromDegrees(35.7750, -78.6336));
    }
}
