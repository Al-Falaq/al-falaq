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

    public function testNegativeDirectionIsRejected(): void
    {
        // The || guard rejects any negative bearing (mutation gate).
        $this->expectException(ValidationException::class);

        new QiblaResult(-1.0, 100.0);
    }

    public function testOutOfRangeDirectionWithZeroDistanceIsRejected(): void
    {
        // The Makkah-self exemption needs BOTH distance 0 and direction 0.
        $this->expectException(ValidationException::class);

        new QiblaResult(360.0, 0.0);
    }

    public function testNonFiniteDirectionWithZeroDistanceIsRejected(): void
    {
        // Non-finite bearings throw even when the distance is zero: the
        // Makkah-self exemption needs direction exactly 0.0, not NaN.
        $this->expectException(ValidationException::class);

        new QiblaResult(NAN, 0.0);
    }

    public function testMagneticHeadingRejectsStringDeclination(): void
    {
        // Strings are neither int nor float (mutation gate).
        $this->expectException(ValidationException::class);

        (new QiblaResult(55.0, 100.0))->magneticHeading('5');
    }

    public function testMagneticHeadingZeroStaysZero(): void
    {
        // An exact-zero heading is in range and must not wind to 360.
        $result = new QiblaResult(10.0, 100.0);

        self::assertSame(0.0, $result->magneticHeading(10.0));
    }
}
