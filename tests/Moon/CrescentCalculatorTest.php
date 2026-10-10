<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Moon;

use AlFalaq\AlFalaq\Moon\Services\CrescentCalculator;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\AstronomicalException;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;
use PHPUnit\Framework\TestCase;

/**
 * Pipeline: KL gates in PHP storage units, composition agreement, polar-night type.
 */
final class CrescentCalculatorTest extends TestCase
{
    public function testKlSunsetGeometryInPhpUnits(): void
    {
        $geometry = CrescentCalculator::default()->atSunset(
            Coordinates::fromDegrees(3.1390, 101.6869),
            CivilDate::fromYmd(2025, 2, 28),
        );

        self::assertTrue(abs($geometry->elongationDeg - 6.1740) <= 0.01);
        self::assertTrue(abs($geometry->moonAltitude - 4.2545) <= 0.01);
        self::assertTrue(abs($geometry->arcvGeoDeg - 6.0880) <= 0.01);
        self::assertTrue(abs($geometry->widthArcmin - 0.0953) <= 0.01);
        self::assertTrue(abs(($geometry->lagMinutes ?? NAN) - 24.7257) <= 0.6);
        self::assertTrue(abs($geometry->moonAgeHours - 10.0) <= 0.24);
        self::assertTrue(abs(($geometry->ageAtMoonsetHours ?? NAN) - 10.4121) <= 0.24);
    }

    public function testEvaluateReturnsAgreementTuple(): void
    {
        $verdicts = CrescentCalculator::default()->evaluate(
            Coordinates::fromDegrees(3.1390, 101.6869),
            CivilDate::fromYmd(2025, 2, 28),
        );

        self::assertSame(['yallop', 'odeh', 'mabims1992', 'neomabims2021'], array_keys($verdicts));
        self::assertSame('F', $verdicts['yallop']->zone);
        self::assertSame('D', $verdicts['odeh']->zone);
        self::assertFalse($verdicts['neomabims2021']->visible);
        self::assertTrue($verdicts['mabims1992']->visible);
    }

    public function testPolarNightRaisesAstronomicalException(): void
    {
        $this->expectException(AstronomicalException::class);

        CrescentCalculator::default()->atSunset(
            Coordinates::fromDegrees(69.6492, 18.9553),
            CivilDate::fromYmd(2025, 1, 1),
        );
    }
}
