<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Moon;

use AlFalaq\AlFalaq\Moon\Strategies\YallopEvaluator;
use AlFalaq\AlFalaq\Moon\ValueObjects\CrescentGeometry;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

/**
 * Yallop q-criterion: cubic, strict-> zone edges, width hygiene.
 */
final class YallopEvaluatorTest extends TestCase
{
    /**
     * Build geometry carrying only the Yallop inputs.
     *
     * @param float $arcvGeo Geocentric ARCV in degrees.
     * @param float $width Width in arcmin (raw; may be 0.0).
     * @return CrescentGeometry Valid bundle.
     */
    private function geometry(float $arcvGeo, float $width): CrescentGeometry
    {
        return new CrescentGeometry(-0.8333, 5.0, 8.0, 12.0, 30.0, 1.0, 0.005, $arcvGeo, $width, 12.5);
    }

    public function testRaleighGoldenScoresZoneA(): void
    {
        $verdict = (new YallopEvaluator())->evaluate($this->geometry(12.3195, 0.4085));

        self::assertSame('yallop', $verdict->criterion);
        self::assertTrue(abs($verdict->score - 0.2950) <= 0.01);
        self::assertSame('A', $verdict->zone);
        self::assertTrue($verdict->visible);
    }

    public function testAucklandGoldenScoresZoneF(): void
    {
        $verdict = (new YallopEvaluator())->evaluate($this->geometry(2.8977, 0.0357));

        self::assertTrue(abs($verdict->score - (-0.8715)) <= 0.01);
        self::assertSame('F', $verdict->zone);
        self::assertFalse($verdict->visible);
    }

    public function testZoneEdgesAreStrict(): void
    {
        // Float note: $limit + 2.16 does NOT yield q === 0.216 — the
        // subtraction/division round one ulp above the 0.216 literal, so
        // the strict-> edge is bracketed at ±0.01 instead of hit exactly
        // (pin test_yallop.py:9 covers the literal: yallop_zone(0.216) == B,
        // which the `>` chain below reproduces for an exact operand).
        $w = 0.2;
        $limit = -0.1018 * $w ** 3 + 0.7319 * $w ** 2 - 6.3226 * $w + 11.8371;
        $below = (new YallopEvaluator())->evaluate($this->geometry($limit + 2.16 - 0.01, $w));
        $above = (new YallopEvaluator())->evaluate($this->geometry($limit + 2.16 + 0.01, $w));

        self::assertSame('B', $below->zone);
        self::assertSame('A', $above->zone);
    }

    public function testNegativeWidthIsRejected(): void
    {
        $this->expectException(ValidationException::class);

        (new YallopEvaluator())->evaluate($this->geometry(6.0, -0.5));
    }
}
