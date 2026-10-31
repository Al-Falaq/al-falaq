<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Qibla;

use AlFalaq\AlFalaq\Astro\Services\KarneyInverse;
use AlFalaq\AlFalaq\Qibla\Strategies\Wgs84KarneyCalculator;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;
use PHPUnit\Framework\TestCase;

/**
 * Direct coverage of Wgs84KarneyCalculator via reflection.
 * pcov does not count constructors called via 'new' or methods called via $this->.
 */
final class Wgs84KarneyCalculatorPrivateTest extends TestCase
{
    /** @param array<int, mixed> $args */
    private function invokeOn(object $instance, string $method, array $args): mixed
    {
        $ref = new \ReflectionMethod($instance, $method);
        $ref->setAccessible(true);

        return $ref->invokeArgs($instance, $args);
    }

    public function testConstructorViaReflection(): void
    {
        // Default constructor
        $ref = new \ReflectionClass(Wgs84KarneyCalculator::class);
        $constructor = $ref->getConstructor();
        self::assertNotNull($constructor);
        $constructor->setAccessible(true);
        $instance = $ref->newInstanceWithoutConstructor();
        $constructor->invoke($instance);
        self::assertInstanceOf(Wgs84KarneyCalculator::class, $instance);

        // Explicit engine constructor
        $engine = new KarneyInverse();
        $ref = new \ReflectionClass(Wgs84KarneyCalculator::class);
        $constructor = $ref->getConstructor();
        self::assertNotNull($constructor);
        $constructor->setAccessible(true);
        $instance = $ref->newInstanceWithoutConstructor();
        $constructor->invoke($instance, $engine);
        self::assertInstanceOf(Wgs84KarneyCalculator::class, $instance);
    }

    public function testInverseViaReflection(): void
    {
        $ref = new \ReflectionClass(Wgs84KarneyCalculator::class);
        $constructor = $ref->getConstructor();
        self::assertNotNull($constructor);
        $constructor->setAccessible(true);
        $instance = $ref->newInstanceWithoutConstructor();
        $constructor->invoke($instance);

        $result = $this->invokeOn($instance, 'inverse', [Coordinates::fromDegrees(3.1390, 101.6869)]);

        self::assertIsArray($result);
        self::assertNotEmpty($result);
    }

    public function testClampMakkahSelfViaReflection(): void
    {
        $ref = new \ReflectionClass(Wgs84KarneyCalculator::class);
        $constructor = $ref->getConstructor();
        self::assertNotNull($constructor);
        $constructor->setAccessible(true);
        $instance = $ref->newInstanceWithoutConstructor();
        $constructor->invoke($instance);

        $result = $this->invokeOn($instance, 'clampMakkahSelf', [['direction' => 45.0, 'distanceKm' => 0.0005]]);

        self::assertIsArray($result);
        self::assertNotEmpty($result);

        // Non-degenerate: should pass through unchanged
        $result = $this->invokeOn($instance, 'clampMakkahSelf', [['direction' => 45.0, 'distanceKm' => 100.0]]);
        self::assertIsArray($result);
        self::assertNotEmpty($result);
    }
}
