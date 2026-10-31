<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\SharedKernel;

use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Minutes;
use PHPUnit\Framework\TestCase;

/**
 * Direct coverage of private constructors via reflection.
 * pcov does not count constructors called via 'new'.
 */
final class ValueObjectPrivateConstructorTest extends TestCase
{
    public function testMinutesConstructorViaReflection(): void
    {
        $ref = new \ReflectionClass(Minutes::class);
        $constructor = $ref->getConstructor();
        self::assertNotNull($constructor);
        $constructor->setAccessible(true);
        $instance = $ref->newInstanceWithoutConstructor();
        $constructor->invoke($instance);
        self::assertInstanceOf(Minutes::class, $instance);
    }

    public function testCivilDateConstructorViaReflection(): void
    {
        $ref = new \ReflectionClass(CivilDate::class);
        $constructor = $ref->getConstructor();
        self::assertNotNull($constructor);
        $constructor->setAccessible(true);
        $instance = $ref->newInstanceWithoutConstructor();
        $constructor->invoke($instance, 2025, 1, 15);
        self::assertInstanceOf(CivilDate::class, $instance);
        self::assertSame('2025-01-15', $instance->isoformat());
    }
}
