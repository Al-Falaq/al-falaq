<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Moon;

use AlFalaq\AlFalaq\Moon\Strategies\NeoMabims2021Evaluator;
use AlFalaq\AlFalaq\Moon\ValueObjects\CrescentGeometry;
use PHPUnit\Framework\TestCase;

/**
 * Neo-MABIMS-2021 criterion: alt >= 3deg and elongation >= 6.4deg.
 */
final class NeoMabims2021EvaluatorTest extends TestCase
{
    /**
     * Build geometry carrying only the Neo-MABIMS inputs (altitude + elongation).
     *
     * @param float $alt Topocentric moon altitude in degrees.
     * @param float $elong Sun-moon elongation in degrees.
     * @return CrescentGeometry Valid bundle.
     */
    private function geometry(float $alt, float $elong): CrescentGeometry
    {
        return new CrescentGeometry(
            -0.8333, // sunAltitude
            $alt, // moonAltitude
            $elong, // elongationDeg
            0.0, // moonAgeHours
            null, // lagMinutes
            0.0, // deltaAzimuthDeg
            0.5, // illuminatedFraction
            0.0, // arcvGeoDeg
            0.0, // widthArcmin
            null, // ageAtMoonsetHours
        );
    }

    public function testVisibleWhenAltAboveThreeAndElongAboveSixFour(): void
    {
        $verdict = (new NeoMabims2021Evaluator())->evaluate($this->geometry(5.0, 10.0));

        self::assertSame('neomabims2021', $verdict->criterion);
        self::assertTrue($verdict->visible);
        self::assertSame('visible', $verdict->zone);
        self::assertSame(10.0, $verdict->score);
    }

    public function testNotVisibleWhenAltBelowThree(): void
    {
        $verdict = (new NeoMabims2021Evaluator())->evaluate($this->geometry(2.9, 10.0));

        self::assertFalse($verdict->visible);
        self::assertSame('not-visible', $verdict->zone);
    }

    public function testNotVisibleWhenElongBelowSixFour(): void
    {
        $verdict = (new NeoMabims2021Evaluator())->evaluate($this->geometry(5.0, 6.3));

        self::assertFalse($verdict->visible);
        self::assertSame('not-visible', $verdict->zone);
    }

    public function testBoundaryAltExactlyThreeIsVisible(): void
    {
        $verdict = (new NeoMabims2021Evaluator())->evaluate($this->geometry(3.0, 10.0));

        self::assertTrue($verdict->visible);
    }

    public function testBoundaryElongExactlySixFourIsVisible(): void
    {
        $verdict = (new NeoMabims2021Evaluator())->evaluate($this->geometry(5.0, 6.4));

        self::assertTrue($verdict->visible);
    }
}
