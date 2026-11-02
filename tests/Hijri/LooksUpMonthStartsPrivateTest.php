<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Hijri;

use AlFalaq\AlFalaq\Hijri\Enums\MabimsCountry;
use AlFalaq\AlFalaq\Hijri\Strategies\MabimsCalendar;
use PHPUnit\Framework\TestCase;

/**
 * Direct coverage of LooksUpMonthStarts trait private methods via reflection,
 * accessed through MabimsCalendar which uses the trait.
 */
final class LooksUpMonthStartsPrivateTest extends TestCase
{
    /** @param array<int, mixed> $args */
    private function invokeOn(object $instance, string $method, array $args): mixed
    {
        $ref = new \ReflectionMethod($instance, $method);
        $ref->setAccessible(true);

        return $ref->invokeArgs($instance, $args);
    }

    public function testTableIndexViaReflection(): void
    {
        $calendar = new MabimsCalendar(MabimsCountry::MY);
        $result = $this->invokeOn($calendar, 'tableIndex', [1446, 9]);
        self::assertIsInt($result);
        self::assertTrue($result >= 0);
    }
}
