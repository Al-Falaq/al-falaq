<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Hijri;

use AlFalaq\AlFalaq\Hijri\Strategies\TabularCalendar;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\Hijri\ValueObjects\HijriDate;
use PHPUnit\Framework\TestCase;

/**
 * Direct coverage of TabularCalendar private methods via reflection.
 */
final class TabularCalendarPrivateTest extends TestCase
{
    /** @param array<int, mixed> $args */
    private function invokeOn(object $instance, string $method, array $args): mixed
    {
        $ref = new \ReflectionMethod($instance, $method);
        $ref->setAccessible(true);

        return $ref->invokeArgs($instance, $args);
    }

    public function testHijriToJdViaReflection(): void
    {
        $calendar = new TabularCalendar();
        $result = $this->invokeOn($calendar, 'hijriToJd', [1446, 9, 1]);
        self::assertIsFloat($result);
        self::assertTrue(is_finite($result));
    }

    public function testJdToHijriViaReflection(): void
    {
        $calendar = new TabularCalendar();
        $result = $this->invokeOn($calendar, 'jdToHijri', [2460756.5]);
        self::assertInstanceOf(HijriDate::class, $result);
    }

    public function testJdToGregorianViaReflection(): void
    {
        $calendar = new TabularCalendar();
        $result = $this->invokeOn($calendar, 'jdToGregorian', [2460756.5]);
        self::assertInstanceOf(CivilDate::class, $result);
    }
}
