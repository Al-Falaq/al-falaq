<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Qibla;

use AlFalaq\AlFalaq\Qibla\ValueObjects\QiblaResult;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

/**
 * QiblaResult validation + magnetic heading (§5.3 contract).
 */
final class QiblaResultTest extends TestCase
{
    public function testOutOfRangeDirectionIsRejected(): void
    {
        $this->expectException(ValidationException::class);

        new QiblaResult(360.0, 100.0);
    }

    public function testMakkahSelfPairIsAccepted(): void
    {
        $result = new QiblaResult(0.0, 0.0);

        self::assertSame(0.0, $result->direction);
        self::assertSame(0.0, $result->distanceKm);
    }

    public function testMagneticHeadingSubtractsEastDeclination(): void
    {
        $result = new QiblaResult(55.825083, 10943.598);

        self::assertTrue(abs($result->magneticHeading(10.0) - 45.825083) <= 1e-9);
        self::assertSame($result->direction, $result->magneticHeading(0.0));
    }

    public function testMagneticHeadingRejectsBoolDeclination(): void
    {
        $this->expectException(ValidationException::class);

        (new QiblaResult(55.0, 100.0))->magneticHeading(true);
    }

    public function testMagneticHeadingRejectsNonFiniteDeclination(): void
    {
        $this->expectException(ValidationException::class);

        (new QiblaResult(55.0, 100.0))->magneticHeading(INF);
    }

    public function testNegativeDistanceIsRejected(): void
    {
        $this->expectException(ValidationException::class);

        new QiblaResult(45.0, -1.0);
    }
}
