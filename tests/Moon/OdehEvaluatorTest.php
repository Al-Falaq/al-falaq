<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Moon;

use AlFalaq\AlFalaq\Moon\Strategies\OdehEvaluator;
use AlFalaq\AlFalaq\Moon\ValueObjects\CrescentGeometry;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

/**
 * Odeh V-criterion: Danjon floor, >= class edges, topo-ARCV derivation.
 */
final class OdehEvaluatorTest extends TestCase
{
    /**
     * Build geometry carrying only the Odeh inputs (topo ARCV derives as
     * malt − salt from these two fields).
     *
     * @param float $arcl Elongation in degrees.
     * @param float $malt Topocentric moon altitude in degrees.
     * @param float $width Width in arcmin.
     * @return CrescentGeometry Valid bundle.
     */
    private function geometry(float $arcl, float $malt, float $width): CrescentGeometry
    {
        return new CrescentGeometry(-0.8333, $malt, $arcl, 12.0, 30.0, 1.0, 0.005, 8.0, $width, 12.5);
    }

    public function testRaleighGoldenScoresClassA(): void
    {
        $verdict = (new OdehEvaluator())->evaluate($this->geometry(12.7862, 10.4995, 0.4085));

        self::assertSame('odeh', $verdict->criterion);
        self::assertTrue(abs($verdict->score - 6.6344) <= 0.01);
        self::assertSame('A', $verdict->zone);
        self::assertTrue($verdict->visible);
    }

    public function testDanjonFloorForcesDBelow64(): void
    {
        $verdict = (new OdehEvaluator())->evaluate($this->geometry(6.3999, 10.0, 0.4));

        self::assertSame('D', $verdict->zone);
    }

    public function testExactly64EscapesTheFloor(): void
    {
        // arcl exactly 6.4 does NOT trigger the strict-< floor; with high
        // malt the class is then set by V alone (A here).
        $verdict = (new OdehEvaluator())->evaluate($this->geometry(6.4, 12.0, 0.4));

        self::assertNotSame('D', $verdict->zone);
    }

    public function testAucklandGoldenScoresClassD(): void
    {
        $verdict = (new OdehEvaluator())->evaluate($this->geometry(3.7800, 1.0615, 0.0357));

        self::assertTrue(abs($verdict->score - (-5.0459)) <= 0.01);
        self::assertSame('D', $verdict->zone);
        self::assertFalse($verdict->visible);
    }

    public function testNegativeWidthIsRejected(): void
    {
        $this->expectException(ValidationException::class);

        (new OdehEvaluator())->evaluate($this->geometry(8.0, 6.0, -0.5));
    }
}
