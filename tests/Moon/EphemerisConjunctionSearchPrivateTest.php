<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Moon;

use AlFalaq\AlFalaq\Moon\Services\EphemerisConjunctionSearch;
use PHPUnit\Framework\TestCase;

/**
 * Direct coverage of EphemerisConjunctionSearch private methods via reflection.
 */
final class EphemerisConjunctionSearchPrivateTest extends TestCase
{
    /** @param array<int, mixed> $args */
    private function invokeOn(object $instance, string $method, array $args): mixed
    {
        $ref = new \ReflectionMethod($instance, $method);
        $ref->setAccessible(true);

        return $ref->invokeArgs($instance, $args);
    }

    public function testArclAtViaReflection(): void
    {
        $search = EphemerisConjunctionSearch::default();
        $result = $this->invokeOn($search, 'arclAt', [2451545.0]);
        self::assertIsFloat($result);
        self::assertTrue(is_finite($result));
    }
}
