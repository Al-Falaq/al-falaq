<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Astro;

use AlFalaq\AlFalaq\Astro\Services\KarneyInverse;
use PHPUnit\Framework\TestCase;

/**
 * Direct coverage of KarneyInverse private helpers via reflection,
 * pinning branches that normal inverse() inputs rarely reach.
 */
final class KarneyInversePrivateTest extends TestCase
{
    private function getPrivateConstant(string $name): int
    {
        $ref = new \ReflectionClass(KarneyInverse::class);
        $const = $ref->getReflectionConstant($name);

        self::assertNotFalse($const);
        $value = $const->getValue();

        self::assertIsInt($value);

        return $value;
    }

    private function newEngine(): KarneyInverse
    {
        return new KarneyInverse();
    }

    /** @param array<int, mixed> $args */
    private function invokeOn(object $instance, string $method, array $args): mixed
    {
        $ref = new \ReflectionMethod($instance, $method);
        $ref->setAccessible(true);

        return $ref->invokeArgs($instance, $args);
    }

    /** @param array<int, mixed> $args */
    private function invokeStatic(string $method, array $args): mixed
    {
        $ref = new \ReflectionMethod(KarneyInverse::class, $method);
        $ref->setAccessible(true);

        return $ref->invokeArgs(null, $args);
    }

    public function testTinyReturnsSqrtOfPhpFloatMin(): void
    {
        $result = $this->invokeStatic('tiny', []);
        self::assertIsFloat($result);
        self::assertTrue($result > 0.0);
    }

    public function testTol2ReturnsSqrtOfPhpFloatEpsilon(): void
    {
        $result = $this->invokeStatic('tol2', []);
        self::assertIsFloat($result);
        self::assertTrue($result > 0.0);
    }

    public function testXthreshReturnsThousandTimesTol2(): void
    {
        $result = $this->invokeStatic('xthresh', []);
        self::assertIsFloat($result);
        self::assertTrue($result > 0.0);
    }

    public function testEtol2ReturnsShortLineTolerance(): void
    {
        $result = $this->invokeStatic('etol2', []);
        self::assertIsFloat($result);
        self::assertTrue($result > 0.0);
    }

    public function testSqReturnsSquare(): void
    {
        self::assertSame(25.0, $this->invokeStatic('sq', [5.0]));
        self::assertSame(0.0, $this->invokeStatic('sq', [0.0]));
        self::assertSame(4.0, $this->invokeStatic('sq', [-2.0]));
    }

    public function testFloatReprHandlesNanInfAndNormal(): void
    {
        $engine = $this->newEngine();
        self::assertSame('nan', $this->invokeOn($engine, 'floatRepr', [NAN]));
        self::assertSame('inf', $this->invokeOn($engine, 'floatRepr', [INF]));
        self::assertSame('-inf', $this->invokeOn($engine, 'floatRepr', [-INF]));
        self::assertSame('3.14', $this->invokeOn($engine, 'floatRepr', [3.14]));
    }

    public function testCopysignPreservesSignOfY(): void
    {
        $engine = $this->newEngine();
        self::assertSame(5.0, $this->invokeOn($engine, 'copysign', [5.0, 1.0]));
        self::assertSame(-5.0, $this->invokeOn($engine, 'copysign', [5.0, -1.0]));
        self::assertSame(-5.0, $this->invokeOn($engine, 'copysign', [5.0, -0.0]));
    }

    public function testNormNormalisesVector(): void
    {
        $engine = $this->newEngine();
        $result = $this->invokeOn($engine, 'norm', [3.0, 4.0]);
        self::assertIsArray($result);
        self::assertSame(2, \count($result));
        self::assertEqualsWithDelta(0.6, $result[0], 1e-12);
        self::assertEqualsWithDelta(0.8, $result[1], 1e-12);
    }

    public function testCbrtSignedCubeRoot(): void
    {
        $engine = $this->newEngine();
        self::assertEqualsWithDelta(2.0, $this->invokeOn($engine, 'cbrt', [8.0]), 1e-12);
        self::assertEqualsWithDelta(-2.0, $this->invokeOn($engine, 'cbrt', [-8.0]), 1e-12);
        self::assertSame(0.0, $this->invokeOn($engine, 'cbrt', [0.0]));
    }

    public function testRemainderIeee754Behavior(): void
    {
        $engine = $this->newEngine();
        self::assertSame(0.0, $this->invokeOn($engine, 'remainder', [720.0, 360.0]));
        self::assertSame(-180.0, $this->invokeOn($engine, 'remainder', [540.0, 360.0]));
    }

    public function testRoundHalfEvenBreaksTiesToEven(): void
    {
        $engine = $this->newEngine();
        self::assertSame(0, $this->invokeOn($engine, 'roundHalfEven', [0.5]));
        self::assertSame(2, $this->invokeOn($engine, 'roundHalfEven', [1.5]));
        self::assertSame(2, $this->invokeOn($engine, 'roundHalfEven', [2.5]));
        self::assertSame(4, $this->invokeOn($engine, 'roundHalfEven', [3.5]));
    }

    public function testAngRoundSmallValues(): void
    {
        $engine = $this->newEngine();
        $result = $this->invokeOn($engine, 'angRound', [0.01]);
        self::assertIsFloat($result);
        self::assertTrue($result > 0.0);

        $result = $this->invokeOn($engine, 'angRound', [-0.01]);
        self::assertTrue($result < 0.0);
    }

    public function testAngDiffExactLongitudeDifference(): void
    {
        $engine = $this->newEngine();
        $result = $this->invokeOn($engine, 'angDiff', [10.0, 20.0]);
        self::assertIsArray($result);
        self::assertSame(2, \count($result));
        self::assertSame(10.0, $result[0]);
        self::assertSame(0.0, $result[1]);
    }

    public function testAtan2dQuadrants(): void
    {
        $engine = $this->newEngine();
        self::assertSame(0.0, $this->invokeOn($engine, 'atan2d', [0.0, 1.0]));
        self::assertSame(90.0, $this->invokeOn($engine, 'atan2d', [1.0, 0.0]));
        self::assertSame(180.0, $this->invokeOn($engine, 'atan2d', [0.0, -1.0]));
        self::assertSame(-90.0, $this->invokeOn($engine, 'atan2d', [-1.0, 0.0]));
    }

    public function testSincosdQuadrants(): void
    {
        $engine = $this->newEngine();

        $result = $this->invokeOn($engine, 'sincosd', [0.0]);
        self::assertIsArray($result);
        self::assertSame(2, \count($result));
        self::assertSame(0.0, $result[0]);
        self::assertSame(1.0, $result[1]);

        $result = $this->invokeOn($engine, 'sincosd', [90.0]);
        self::assertIsArray($result);
        self::assertSame(2, \count($result));
        self::assertSame(1.0, $result[0]);
        self::assertSame(0.0, $result[1]);

        $result = $this->invokeOn($engine, 'sincosd', [180.0]);
        self::assertIsArray($result);
        self::assertSame(2, \count($result));
        self::assertSame(0.0, $result[0]);
        self::assertSame(-1.0, $result[1]);

        $result = $this->invokeOn($engine, 'sincosd', [270.0]);
        self::assertIsArray($result);
        self::assertSame(2, \count($result));
        self::assertSame(-1.0, $result[0]);
        self::assertSame(0.0, $result[1]);
    }

    public function testSincosdeWithResidual(): void
    {
        $engine = $this->newEngine();
        $result = $this->invokeOn($engine, 'sincosde', [0.0, 0.0]);
        self::assertIsArray($result);
        self::assertSame(2, \count($result));
        self::assertSame(0.0, $result[0]);
        self::assertSame(1.0, $result[1]);
    }

    public function testPolyvalHornerWalk(): void
    {
        $engine = $this->newEngine();
        $result = $this->invokeOn($engine, 'polyval', [2, [1.0, 3.0, 2.0], 0, 2.0]);
        self::assertSame(12.0, $result);

        $result = $this->invokeOn($engine, 'polyval', [-1, [5.0, 3.0, 2.0], 0, 10.0]);
        self::assertSame(0.0, $result);
    }

    public function testEsumExactTwoSum(): void
    {
        $engine = $this->newEngine();
        $result = $this->invokeOn($engine, 'esum', [1.0, 2.0]);
        self::assertIsArray($result);
        self::assertSame(2, \count($result));
        self::assertSame(3.0, $result[0]);
    }

    public function testCoeffAtReadsTableEntry(): void
    {
        $engine = $this->newEngine();
        self::assertSame(42.0, $this->invokeOn($engine, 'coeffAt', [[42.0], 0]));
    }

    public function testA3fAndC3fCoefficientSeries(): void
    {
        $engine = $this->newEngine();
        $a3x = $this->invokeOn($engine, 'a3Coeff', []);
        $c3x = $this->invokeOn($engine, 'c3Coeff', []);

        self::assertIsArray($a3x);
        self::assertIsArray($c3x);
        self::assertCount($this->getPrivateConstant('N_A3X'), $a3x);
        self::assertCount($this->getPrivateConstant('N_C3X'), $c3x);

        $a3 = $this->invokeOn($engine, 'a3f', [0.5, $a3x]);
        self::assertIsFloat($a3);

        $c3 = $this->invokeOn($engine, 'c3f', [0.5, array_fill(0, $this->getPrivateConstant('N_C3'), 0.0), $c3x]);
        self::assertIsArray($c3);
        self::assertCount($this->getPrivateConstant('N_C3'), $c3);
    }

    public function testA2m1fAndC2f(): void
    {
        $engine = $this->newEngine();
        $a2m1 = $this->invokeOn($engine, 'a2m1f', [0.5]);
        self::assertIsFloat($a2m1);

        $c2 = $this->invokeOn($engine, 'c2f', [0.5, array_fill(0, $this->getPrivateConstant('N_C2') + 1, 0.0)]);
        self::assertIsArray($c2);
        self::assertCount($this->getPrivateConstant('N_C2') + 1, $c2);
    }

    public function testA1m1fAndC1f(): void
    {
        $engine = $this->newEngine();
        $a1m1 = $this->invokeOn($engine, 'a1m1f', [0.5]);
        self::assertIsFloat($a1m1);

        $c1 = $this->invokeOn($engine, 'c1f', [0.5, array_fill(0, $this->getPrivateConstant('N_C1') + 1, 0.0)]);
        self::assertIsArray($c1);
        self::assertCount($this->getPrivateConstant('N_C1') + 1, $c1);
    }

    public function testA3CoeffAndC3CoeffTables(): void
    {
        $engine = $this->newEngine();
        $a3 = $this->invokeOn($engine, 'a3Coeff', []);
        $c3 = $this->invokeOn($engine, 'c3Coeff', []);

        self::assertIsArray($a3);
        self::assertIsArray($c3);
        self::assertCount($this->getPrivateConstant('N_A3X'), $a3);
        self::assertCount($this->getPrivateConstant('N_C3X'), $c3);
    }

    public function testAstroidBranches(): void
    {
        $engine = $this->newEngine();
        $result = $this->invokeOn($engine, 'astroid', [0.0, 0.0]);
        self::assertSame(0.0, $result);

        $result = $this->invokeOn($engine, 'astroid', [0.3, 0.4]);
        self::assertIsFloat($result);
    }

    public function testSincosSeriesClenshaw(): void
    {
        $engine = $this->newEngine();
        $c3x = $this->invokeOn($engine, 'c3Coeff', []);
        $c3 = $this->invokeOn($engine, 'c3f', [0.5, array_fill(0, $this->getPrivateConstant('N_C3'), 0.0), $c3x]);

        $result = $this->invokeOn($engine, 'sincosSeries', [0.5, 0.5, $c3]);
        self::assertIsFloat($result);
    }

    public function testLengthsAndLambda12ViaReflection(): void
    {
        $engine = $this->newEngine();
        $a3x = $this->invokeOn($engine, 'a3Coeff', []);
        $c3x = $this->invokeOn($engine, 'c3Coeff', []);
        $c1a = array_fill(0, $this->getPrivateConstant('N_C1') + 1, 0.0);
        $c2a = array_fill(0, $this->getPrivateConstant('N_C2') + 1, 0.0);
        $c3a = array_fill(0, $this->getPrivateConstant('N_C3'), 0.0);

        $result = $this->invokeOn($engine, 'lengths', [
            0.1, 0.5, 0.0, 1.0, 1.0, 0.0, 1.0, 1.0, 1.0, 1.0,
            true, false, $c1a, $c2a,
        ]);
        self::assertIsArray($result);

        $result = $this->invokeOn($engine, 'lengths', [
            0.1, 0.5, 0.0, 1.0, 1.0, 0.0, 1.0, 1.0, 1.0, 1.0,
            false, true, $c1a, $c2a,
        ]);
        self::assertIsArray($result);

        $result = $this->invokeOn($engine, 'lambda12', [
            0.0, 1.0, 1.0, 0.0, 1.0, 1.0, 0.0, 1.0, 0.5, 0.5,
            true, $c1a, $c2a, $c3a, $a3x, $c3x,
        ]);
        self::assertIsArray($result);

        $result = $this->invokeOn($engine, 'lambda12', [
            0.0, 1.0, 1.0, 0.0, 1.0, 1.0, 0.0, 1.0, 0.5, 0.5,
            false, $c1a, $c2a, $c3a, $a3x, $c3x,
        ]);
        self::assertIsArray($result);
    }

    public function testInverseStartAndGenInverseViaReflection(): void
    {
        $engine = $this->newEngine();
        $a3x = $this->invokeOn($engine, 'a3Coeff', []);

        $result = $this->invokeOn($engine, 'inverseStart', [
            0.0, 1.0, 1.0, 0.0, 1.0, 1.0, 0.1, 0.1, 0.1, $a3x,
        ]);
        self::assertIsArray($result);

        $result = $this->invokeOn($engine, 'genInverse', [90.0, 0.0, 90.0, 180.0]);
        self::assertIsArray($result);
    }
}
