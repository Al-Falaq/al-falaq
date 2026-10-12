<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Prayer;

use AlFalaq\AlFalaq\Prayer\Exceptions\UndefinedScheduleException;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;
use PHPUnit\Framework\TestCase;

/**
 * polar() carries its factory inputs as typed props; message unchanged.
 */
final class UndefinedScheduleExceptionTest extends TestCase
{
    /**
     * Props mirror the factory inputs, message stays byte-identical.
     *
     * @return void
     */
    public function testPolarPropsMirrorFactoryInputs(): void
    {
        $coordinates = Coordinates::fromDegrees(78.2232, 15.6267);
        $date = CivilDate::fromYmd(2025, 6, 21);
        $exception = UndefinedScheduleException::polar($coordinates, $date, 'none');

        self::assertSame($coordinates, $exception->coordinates);
        self::assertSame($date, $exception->date);
        self::assertSame('none', $exception->rule);
        self::assertSame(
            sprintf(
                'Undefined schedule at %s on %s (rule %s). Try PolarCircleRule::NEAREST_LATITUDE. See docs/polar.md.',
                (string) $coordinates,
                $date->isoformat(),
                'none',
            ),
            $exception->getMessage(),
        );
    }
}
