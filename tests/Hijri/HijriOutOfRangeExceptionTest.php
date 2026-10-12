<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Hijri;

use AlFalaq\AlFalaq\Hijri\Exceptions\HijriOutOfRangeException;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

/**
 * Range errors name the calendar, the rejected date and the supported span.
 */
final class HijriOutOfRangeExceptionTest extends TestCase
{
    /**
     * The message carries all three facts a caller needs.
     *
     * @return void
     */
    public function testMessageNamesCalendarDateAndRange(): void
    {
        $exception = HijriOutOfRangeException::forDate('uqu', '2002-03-14', '1423–1500 AH');

        self::assertStringContainsString('uqu', $exception->getMessage());
        self::assertStringContainsString('2002-03-14', $exception->getMessage());
        self::assertStringContainsString('1423–1500 AH', $exception->getMessage());
    }

    /**
     * It stays catchable as a ValidationException.
     *
     * @return void
     */
    public function testRemainsAValidationException(): void
    {
        self::assertInstanceOf(
            ValidationException::class,
            HijriOutOfRangeException::forDate('tabular', 'x', 'y'),
        );
    }

    /**
     * Props mirror the factory inputs, message stays byte-identical.
     *
     * @return void
     */
    public function testPropsMirrorFactoryInputs(): void
    {
        $exception = HijriOutOfRangeException::forDate('uqu', '2002-03-14', '1423–1500 AH');

        self::assertSame('uqu', $exception->calendar);
        self::assertSame('2002-03-14', $exception->iso);
        self::assertSame('1423–1500 AH', $exception->range);
        self::assertSame(
            'Date 2002-03-14 out of range for uqu calendar (1423–1500 AH).',
            $exception->getMessage(),
        );
    }
}
