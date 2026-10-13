<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Moon;

use AlFalaq\AlFalaq\Moon\Strategies\Mabims1992Evaluator;
use AlFalaq\AlFalaq\Moon\Strategies\NeoMabims2021Evaluator;
use AlFalaq\AlFalaq\Moon\ValueObjects\CrescentGeometry;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

/**
 * MABIMS rules: 1992 OR-gate on age-at-moonset, Neo thresholds + key rename.
 */
final class MabimsEvaluatorTest extends TestCase
{
    /**
     * Build geometry carrying only the MABIMS inputs.
     *
     * @param float $malt Moon altitude in degrees.
     * @param float $arcl Elongation in degrees.
     * @param float|null $ageAtMoonset Age at moonset in hours (null = windowless).
     * @return CrescentGeometry Valid bundle.
     */
    private function geometry(float $malt, float $arcl, ?float $ageAtMoonset): CrescentGeometry
    {
        return new CrescentGeometry(-0.8333, $malt, $arcl, 12.0, $ageAtMoonset === null ? null : 30.0, 1.0, 0.005, 8.0, 0.2, $ageAtMoonset);
    }

    public function test1992OrGateSydneyCatcher(): void
    {
        // Sydney 2025-02-28: alt 1.59 < 2 but age 8.28 h ≥ 8 → true under OR.
        $verdict = (new Mabims1992Evaluator())->evaluate($this->geometry(1.5947, 4.5853, 8.2824));

        self::assertSame('mabims1992', $verdict->criterion);
        self::assertTrue($verdict->visible);
    }

    public function test1992ExactThresholdsPass(): void
    {
        $verdict = (new Mabims1992Evaluator())->evaluate($this->geometry(2.0, 3.0, 8.0));

        self::assertTrue($verdict->visible);
    }

    public function test1992NullWindowFallsBackToAltElong(): void
    {
        // No-moonset window (spec §3.2): age branch undefined, so only the
        // altitude/elongation half applies — same thresholds as the full rule.
        $visible = (new Mabims1992Evaluator())->evaluate($this->geometry(5.0, 8.0, null));

        self::assertTrue($visible->visible);
        self::assertSame('visible', $visible->zone);

        $hidden = (new Mabims1992Evaluator())->evaluate($this->geometry(1.0, 8.0, null));

        self::assertFalse($hidden->visible);
        self::assertSame('not-visible', $hidden->zone);
    }

    public function testNeoKeyRenamedAndThresholdsHold(): void
    {
        $evaluator = new NeoMabims2021Evaluator();
        $pass = $evaluator->evaluate($this->geometry(3.0, 6.4, 5.0));
        $fail = $evaluator->evaluate($this->geometry(2.9999, 6.4, 5.0));

        self::assertSame('neomabims2021', $pass->criterion);
        self::assertTrue($pass->visible);
        self::assertFalse($fail->visible);
    }

    public function testNeoRejectsNonFinite(): void
    {
        $this->expectException(ValidationException::class);

        (new NeoMabims2021Evaluator())->evaluate($this->geometry(INF, 7.0, 5.0));
    }

    public function test1992AgeEpsilonEdges(): void
    {
        $evaluator = new Mabims1992Evaluator();

        self::assertFalse($evaluator->evaluate($this->geometry(1.0, 2.0, 7.9999))->visible);
        self::assertTrue($evaluator->evaluate($this->geometry(1.0, 2.0, 8.0))->visible);
        self::assertTrue($evaluator->evaluate($this->geometry(1.0, 2.0, 8.0001))->visible);
    }

    public function testNeoElongationEpsilonEdges(): void
    {
        $evaluator = new NeoMabims2021Evaluator();

        self::assertFalse($evaluator->evaluate($this->geometry(3.0, 6.3999, 5.0))->visible);
        self::assertTrue($evaluator->evaluate($this->geometry(3.0, 6.4001, 5.0))->visible);
    }

    public function test1992AltitudeElongationEpsilon(): void
    {
        $evaluator = new Mabims1992Evaluator();

        self::assertFalse($evaluator->evaluate($this->geometry(1.9999, 3.0, 5.0))->visible);
        self::assertFalse($evaluator->evaluate($this->geometry(2.0, 2.9999, 5.0))->visible);
    }

    public function test1992AltElongGateHoldsWithoutAge(): void
    {
        $verdict = (new Mabims1992Evaluator())->evaluate($this->geometry(2.5, 4.0, 5.0));

        self::assertTrue($verdict->visible);
    }
}
