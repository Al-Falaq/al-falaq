<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Moon;

use AlFalaq\AlFalaq\Astro\Services\CrescentEphemeris;
use AlFalaq\AlFalaq\Astro\Services\MeanSolarCalculator;
use AlFalaq\AlFalaq\Moon\Contracts\CrescentEvaluator;
use AlFalaq\AlFalaq\Moon\Services\CrescentCalculator;
use AlFalaq\AlFalaq\Moon\Strategies\Mabims1992Evaluator;
use AlFalaq\AlFalaq\Moon\Strategies\NeoMabims2021Evaluator;
use AlFalaq\AlFalaq\Moon\Strategies\OdehEvaluator;
use AlFalaq\AlFalaq\Moon\Strategies\YallopEvaluator;
use AlFalaq\AlFalaq\Moon\ValueObjects\CrescentGeometry;
use AlFalaq\AlFalaq\Moon\ValueObjects\CrescentVerdict;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\AstronomicalException;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;
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

    public function testEvaluateDelegatesToInjectedStrategies(): void
    {
        $calculator = new CrescentCalculator(
            new MeanSolarCalculator(),
            new CrescentEphemeris(),
            self::stub('yallop', 'A'),
            self::stub('odeh', 'B'),
            self::stub('mabims1992', 'visible'),
            self::stub('neomabims2021', 'not-visible'),
        );

        $verdicts = $calculator->evaluate(
            Coordinates::fromDegrees(3.1390, 101.6869),
            CivilDate::fromYmd(2025, 2, 28),
        );

        self::assertSame(['yallop', 'odeh', 'mabims1992', 'neomabims2021'], array_keys($verdicts));
        self::assertSame('A', $verdicts['yallop']->zone);
        self::assertSame('B', $verdicts['odeh']->zone);
        self::assertSame('visible', $verdicts['mabims1992']->zone);
        self::assertSame('not-visible', $verdicts['neomabims2021']->zone);
    }

    public function testExplicitWiringMatchesDefault(): void
    {
        $calculator = new CrescentCalculator(
            new MeanSolarCalculator(),
            new CrescentEphemeris(),
            new YallopEvaluator(),
            new OdehEvaluator(),
            new Mabims1992Evaluator(),
            new NeoMabims2021Evaluator(),
        );

        $verdicts = $calculator->evaluate(
            Coordinates::fromDegrees(3.1390, 101.6869),
            CivilDate::fromYmd(2025, 2, 28),
        );

        self::assertSame('F', $verdicts['yallop']->zone);
        self::assertSame('D', $verdicts['odeh']->zone);
    }

    public function testDeltaTOverrideChangesGeometry(): void
    {
        $calculator = CrescentCalculator::default();
        $coordinates = Coordinates::fromDegrees(3.1390, 101.6869);
        $date = CivilDate::fromYmd(2025, 2, 28);
        $default = $calculator->atSunset($coordinates, $date);
        $overridden = $calculator->atSunset($coordinates, $date, 100.0);

        self::assertNotEquals($default->elongationDeg, $overridden->elongationDeg);
    }

    public function testNullOverrideEqualsDefault(): void
    {
        $calculator = CrescentCalculator::default();
        $coordinates = Coordinates::fromDegrees(3.1390, 101.6869);
        $date = CivilDate::fromYmd(2025, 2, 28);

        self::assertEquals(
            $calculator->atSunset($coordinates, $date),
            $calculator->atSunset($coordinates, $date, null),
        );
    }

    public function testNonFiniteOverrideThrows(): void
    {
        $this->expectException(ValidationException::class);

        CrescentCalculator::default()->atSunset(
            Coordinates::fromDegrees(3.1390, 101.6869),
            CivilDate::fromYmd(2025, 2, 28),
            NAN,
        );
    }

    public function testEvaluatePassesDeltaTOverrideThrough(): void
    {
        $calculator = CrescentCalculator::default();
        $coordinates = Coordinates::fromDegrees(3.1390, 101.6869);
        $date = CivilDate::fromYmd(2025, 2, 28);
        $verdicts = $calculator->evaluate($coordinates, $date, 100.0);

        self::assertSame(['yallop', 'odeh', 'mabims1992', 'neomabims2021'], array_keys($verdicts));
        self::assertEquals(
            $calculator->evaluateGeometry($calculator->atSunset($coordinates, $date, 100.0)),
            $verdicts,
        );
    }

    /**
     * Canned strategy double proving the composition root delegates.
     *
     * @param string $criterion Canned criterion name.
     * @param string $zone Canned zone label.
     * @return CrescentEvaluator Strategy returning the canned verdict.
     */
    private static function stub(string $criterion, string $zone): CrescentEvaluator
    {
        return new class ($criterion, $zone) implements CrescentEvaluator {
            public function __construct(
                private readonly string $criterion,
                private readonly string $zone,
            ) {}

            public function evaluate(CrescentGeometry $geometry): CrescentVerdict
            {
                return new CrescentVerdict($this->criterion, true, 0.0, $this->zone);
            }
        };
    }
}
