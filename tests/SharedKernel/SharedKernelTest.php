<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\SharedKernel;

use AlFalaq\AlFalaq\SharedKernel\Exceptions\InvalidCoordinatesException;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Angle;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\JulianDay;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Minutes;
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

    public function testFromArrayRejectsThreeElementPair(): void
    {
        // A 3-element pair fails the count guard even with keys 0 and 1 present.
        $this->expectException(InvalidCoordinatesException::class);
        Coordinates::fromArray([1.0, 2.0, 3.0]);
    }

    public function testFromArrayRejectsMissingZeroKey(): void
    {
        $this->expectException(InvalidCoordinatesException::class);
        Coordinates::fromArray([1 => 1.0, 2 => 2.0]);
    }

    public function testFromArrayRejectsMissingOneKey(): void
    {
        $this->expectException(InvalidCoordinatesException::class);
        Coordinates::fromArray([0 => 1.0, 5 => 2.0]);
    }

    public function testEqualsRequiresBothAxes(): void
    {
        $base = Coordinates::fromDegrees(3.0, 10.0);

        self::assertTrue($base->equals(Coordinates::fromDegrees(3.0, 10.0)));
        self::assertFalse($base->equals(Coordinates::fromDegrees(3.0, 20.0)));
        self::assertFalse($base->equals(Coordinates::fromDegrees(4.0, 10.0)));
    }

    public function testCivilDateEqualsRequiresAllFields(): void
    {
        $base = CivilDate::fromYmd(2025, 1, 15);

        self::assertTrue($base->equals(CivilDate::fromYmd(2025, 1, 15)));
        self::assertFalse($base->equals(CivilDate::fromYmd(2026, 1, 15)));
        self::assertFalse($base->equals(CivilDate::fromYmd(2025, 2, 15)));
        self::assertFalse($base->equals(CivilDate::fromYmd(2025, 1, 16)));
    }

    public function testNormalizedNegativeWraparoundStaysPositiveZero(): void
    {
        // fmod(-360, 360) is -0.0; the + 0.0 guard must yield +0.0, not -0.0.
        $degrees = Angle::fromDegrees(-360.0)->normalized()->degrees;

        self::assertSame(0.0, $degrees);
        self::assertSame(INF, fdiv(1.0, $degrees));
    }

    public function testRoundedHalfUpZeroesMicroseconds(): void
    {
        // setTime pins microseconds to 0, never 1 (mutation gate).
        $base = new \DateTimeImmutable('2015-07-12 12:34:29', new \DateTimeZone('UTC'));

        self::assertSame('000000', Minutes::roundedHalfUp($base)->format('u'));
    }

    public function testOutOfRangeMessageNamesRangeAndValue(): void
    {
        // Both ternaries and the scalar rendering are pinned (mutation gate).
        $lat = InvalidCoordinatesException::outOfRange('lat', 91.0)->getMessage();

        self::assertStringContainsString('latitude', $lat);
        self::assertStringContainsString('[-90, 90]', $lat);
        self::assertStringContainsString('Received: 91', $lat);

        $lon = InvalidCoordinatesException::outOfRange('lon', 200.0)->getMessage();

        self::assertStringContainsString('longitude', $lon);
        self::assertStringContainsString('[-180, 180]', $lon);
    }

    public function testMinutesAddShiftsForwardAcrossMidnight(): void
    {
        $base = new \DateTimeImmutable('2025-12-31 23:59:00', new \DateTimeZone('UTC'));
        $moved = Minutes::add($base, 2);

        self::assertSame('2026-01-01 00:01:00', $moved->format('Y-m-d H:i:s'));
        self::assertSame('2025-12-31 23:59:00', $base->format('Y-m-d H:i:s'));
    }

    public function testMinutesAddShiftsBackwardAcrossYearBoundary(): void
    {
        $base = new \DateTimeImmutable('2025-01-01 00:01:00', new \DateTimeZone('UTC'));
        $moved = Minutes::add($base, -2);

        self::assertSame('2024-12-31 23:59:00', $moved->format('Y-m-d H:i:s'));
    }

    public function testMinutesAddPreservesCallerZone(): void
    {
        $base = new \DateTimeImmutable('2025-03-01 12:00:00', new \DateTimeZone('Asia/Kuala_Lumpur'));
        $moved = Minutes::add($base, 90);

        self::assertSame('Asia/Kuala_Lumpur', $moved->getTimezone()->getName());
        self::assertSame('2025-03-01 13:30:00', $moved->format('Y-m-d H:i:s'));
    }

    public function testAngleToRadians(): void
    {
        self::assertEqualsWithDelta(0.0, Angle::fromDegrees(0.0)->toRadians(), 1e-12);
        self::assertEqualsWithDelta(M_PI / 2.0, Angle::fromDegrees(90.0)->toRadians(), 1e-12);
        self::assertEqualsWithDelta(M_PI, Angle::fromDegrees(180.0)->toRadians(), 1e-12);
        self::assertEqualsWithDelta(2.0 * M_PI, Angle::fromDegrees(360.0)->toRadians(), 1e-12);
        self::assertEqualsWithDelta(-M_PI / 2.0, Angle::fromDegrees(-90.0)->toRadians(), 1e-12);
    }

    public function testCoordinatesToStringUsesSixDecimals(): void
    {
        self::assertSame('3.139000,101.686900', (string) Coordinates::fromDegrees(3.139, 101.6869));
        self::assertSame('-33.868800,151.209300', (string) Coordinates::fromDegrees(-33.8688, 151.2093));
    }

    public function testCivilDateCompareToOrdersByYearMonthDay(): void
    {
        $base = CivilDate::fromYmd(2025, 1, 15);

        self::assertSame(-1, $base->compareTo(CivilDate::fromYmd(2025, 1, 16)));
        self::assertSame(1, $base->compareTo(CivilDate::fromYmd(2025, 1, 14)));
        self::assertSame(-1, $base->compareTo(CivilDate::fromYmd(2025, 2, 1)));
        self::assertSame(1, $base->compareTo(CivilDate::fromYmd(2024, 12, 31)));
        self::assertSame(0, $base->compareTo(CivilDate::fromYmd(2025, 1, 15)));

        $dates = [
            CivilDate::fromYmd(2025, 2, 1),
            CivilDate::fromYmd(2024, 12, 31),
            CivilDate::fromYmd(2025, 1, 15),
        ];
        usort($dates, static fn (CivilDate $a, CivilDate $b): int => $a->compareTo($b));

        self::assertSame(
            ['2024-12-31', '2025-01-15', '2025-02-01'],
            array_map(static fn (CivilDate $date): string => $date->isoformat(), $dates),
        );
    }
}
