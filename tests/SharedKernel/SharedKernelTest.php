<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Tests\SharedKernel;

use AlFalaq\AlFalak\SharedKernel\Exceptions\InvalidCoordinatesException;
use AlFalaq\AlFalak\SharedKernel\Exceptions\ValidationException;
use AlFalaq\AlFalak\SharedKernel\ValueObjects\Angle;
use AlFalaq\AlFalak\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalak\SharedKernel\ValueObjects\Coordinates;
use AlFalaq\AlFalak\SharedKernel\ValueObjects\JulianDay;
use AlFalaq\AlFalak\SharedKernel\ValueObjects\Minutes;
use PHPUnit\Framework\TestCase;

final class SharedKernelTest extends TestCase
{
    public function testRejectsOutOfRangeAndBoolCoordinates(): void
    {
        $this->expectException(InvalidCoordinatesException::class);
        Coordinates::fromDegrees(91.0, 0.0);
    }

    public function testRejectsBoolCoordinates(): void
    {
        $this->expectException(ValidationException::class);
        Coordinates::fromDegrees(true, 0.0);
    }

    public function testRejectsAssocCoordinateArrays(): void
    {
        $this->expectException(InvalidCoordinatesException::class);
        Coordinates::fromArray(['lat' => 1.0, 'lon' => 2.0]);
    }

    public function testRoundTripsCivilDatesAndHandlesLeapYears(): void
    {
        $date = CivilDate::fromYmd(2024, 2, 29);
        self::assertSame('2024-02-29', $date->isoformat());
        self::assertSame('2024-03-01', $date->plusDays(1)->isoformat());
        self::assertSame(
            '2015-07-12',
            CivilDate::fromDateTime(new \DateTimeImmutable('2015-07-12', new \DateTimeZone('UTC')))->isoformat(),
        );
    }

    public function testRejectsInvalidCivilDates(): void
    {
        $this->expectException(ValidationException::class);
        CivilDate::fromYmd(2023, 2, 29);
    }

    public function testRejectsOutOfRangeYears(): void
    {
        $this->expectException(ValidationException::class);
        CivilDate::fromYmd(1800, 1, 1);
    }

    public function testUnwindsAnglesIdempotently(): void
    {
        self::assertSame(0.0, Angle::fromDegrees(720.0)->normalized()->degrees);
        self::assertSame(270.0, Angle::fromDegrees(-90.0)->normalized()->degrees);
        self::assertSame(350.0, Angle::fromDegrees(350.0)->normalized()->normalized()->degrees);
    }

    public function testRoundsMinutesHalfUpWithRolloverInUtcOnly(): void
    {
        $base = new \DateTimeImmutable('2015-07-12 12:34:30', new \DateTimeZone('UTC'));
        self::assertSame('12:35:00', Minutes::roundedHalfUp($base)->format('H:i:s'));

        $edge = new \DateTimeImmutable('2015-12-31 23:59:45', new \DateTimeZone('UTC'));
        self::assertSame('2016-01-01 00:00', Minutes::roundedHalfUp($edge)->format('Y-m-d H:i'));
    }

    public function testRejectsNonUtcMinutesInput(): void
    {
        $this->expectException(ValidationException::class);
        Minutes::roundedHalfUp(new \DateTimeImmutable('2015-07-12 12:34:30', new \DateTimeZone('America/New_York')));
    }

    public function testComputesJulianDayForAKnownDate(): void
    {
        // 2000-01-01 00:00 UTC => JD 2451544.5
        $jd = JulianDay::fromCivilDate(CivilDate::fromYmd(2000, 1, 1))->toFloat();
        self::assertLessThan(1e-9, abs($jd - 2451544.5));
    }
}
