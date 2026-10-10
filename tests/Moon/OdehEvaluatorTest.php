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

    public function testClassEdgeABIsInclusive(): void
    {
        // A/B edge at V == 5.65 (>=): V 5.70 -> A, V 5.60 -> B. malt is
        // salt + (limit + V); arcl 8.0 keeps the Danjon floor off. Verified
        // via php -r: V targets land on the intended side.
        $width = 0.4;
        $salt = -0.8333;
        $limit = -0.1018 * $width ** 3 + 0.7319 * $width ** 2 - 6.3226 * $width + 7.1651;
        $above = (new OdehEvaluator())->evaluate($this->geometry(8.0, $salt + ($limit + 5.70), $width));
        $below = (new OdehEvaluator())->evaluate($this->geometry(8.0, $salt + ($limit + 5.60), $width));

        self::assertSame('A', $above->zone);
        self::assertSame('B', $below->zone);
    }

    public function testClassEdgeBCIsInclusive(): void
    {
        // B/C edge at V == 2.0 (>=): V 2.05 -> B, V 1.95 -> C.
        $width = 0.4;
        $salt = -0.8333;
        $limit = -0.1018 * $width ** 3 + 0.7319 * $width ** 2 - 6.3226 * $width + 7.1651;
        $above = (new OdehEvaluator())->evaluate($this->geometry(8.0, $salt + ($limit + 2.05), $width));
        $below = (new OdehEvaluator())->evaluate($this->geometry(8.0, $salt + ($limit + 1.95), $width));

        self::assertSame('B', $above->zone);
        self::assertSame('C', $below->zone);
    }

    public function testClassEdgeCDIsInclusive(): void
    {
        // C/D edge at V == -0.96 (>=): V -0.91 -> C, V -1.01 -> D.
        $width = 0.4;
        $salt = -0.8333;
        $limit = -0.1018 * $width ** 3 + 0.7319 * $width ** 2 - 6.3226 * $width + 7.1651;
        $above = (new OdehEvaluator())->evaluate($this->geometry(8.0, $salt + ($limit + -0.91), $width));
        $below = (new OdehEvaluator())->evaluate($this->geometry(8.0, $salt + ($limit + -1.01), $width));

        self::assertSame('C', $above->zone);
        self::assertSame('D', $below->zone);
    }

    public function testExactThresholdHitsUpperClass(): void
    {
        // Exact V thresholds are >= edges: V == 5.65 -> A, == 2.0 -> B,
        // == -0.96 -> C. malt is salt + (limit + V) with arcl 8.0 keeping
        // the Danjon floor off; the same limit expression runs on both
        // sides so the edge lands exactly.
        $width = 0.4;
        $salt = -0.8333;
        $limit = -0.1018 * $width ** 3 + 0.7319 * $width ** 2 - 6.3226 * $width + 7.1651;

        self::assertSame('A', (new OdehEvaluator())->evaluate($this->geometry(8.0, $salt + ($limit + 5.65), $width))->zone);
        self::assertSame('B', (new OdehEvaluator())->evaluate($this->geometry(8.0, $salt + ($limit + 2.0), $width))->zone);
        self::assertSame('C', (new OdehEvaluator())->evaluate($this->geometry(8.0, $salt + ($limit + -0.96), $width))->zone);
    }

    public function testZeroWidthClampsAndStaysFinite(): void
    {
        // Width 0 clamps to 1e-6 arcmin: V ~= 4.17 -> B (same geometry with
        // width 0.4085 scores V ~= 6.63 -> A). Finite score documents the
        // clamp path.
        $verdict = (new OdehEvaluator())->evaluate($this->geometry(12.7862, 10.4995, 0.0));

        self::assertTrue(is_finite($verdict->score));
        self::assertSame('B', $verdict->zone);
    }

    public function testNegativeWidthIsRejected(): void
    {
        $this->expectException(ValidationException::class);

        (new OdehEvaluator())->evaluate($this->geometry(8.0, 6.0, -0.5));
    }
}
