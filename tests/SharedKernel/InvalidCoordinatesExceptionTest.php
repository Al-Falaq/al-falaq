<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\SharedKernel;

use AlFalaq\AlFalaq\SharedKernel\Exceptions\InvalidCoordinatesException;
use PHPUnit\Framework\TestCase;

/**
 * outOfRange carries its factory inputs as typed props; message unchanged.
 */
final class InvalidCoordinatesExceptionTest extends TestCase
{
    /**
     * Props mirror the factory inputs, message stays byte-identical.
     *
     * @return void
     */
    public function testOutOfRangePropsMirrorFactoryInputs(): void
    {
        $exception = InvalidCoordinatesException::outOfRange('lat', 91.0);

        self::assertSame('lat', $exception->which);
        self::assertSame(91.0, $exception->received);
        self::assertSame(
            'Invalid latitude lat: expected [-90, 90], see docs/prayer.md. Received: 91.0.',
            $exception->getMessage(),
        );
    }

    /**
     * Non-scalar input is stored as-is (message renders its type).
     *
     * @return void
     */
    public function testOutOfRangeKeepsNonScalarReceived(): void
    {
        $received = ['lat' => 1.0];
        $exception = InvalidCoordinatesException::outOfRange('lon', $received);

        self::assertSame('lon', $exception->which);
        self::assertSame($received, $exception->received);
        self::assertStringContainsString('array', $exception->getMessage());
    }
}
