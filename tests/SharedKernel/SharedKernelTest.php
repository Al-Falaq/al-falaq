<?php

declare(strict_types=1);

use AlFalaq\AlFalak\SharedKernel\Exceptions\InvalidCoordinatesException;
use AlFalaq\AlFalak\SharedKernel\Exceptions\ValidationException;
use AlFalaq\AlFalak\SharedKernel\ValueObjects\Angle;
use AlFalaq\AlFalak\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalak\SharedKernel\ValueObjects\Coordinates;
use AlFalaq\AlFalak\SharedKernel\ValueObjects\JulianDay;
use AlFalaq\AlFalak\SharedKernel\ValueObjects\Minutes;

it('rejects out-of-range and bool coordinates', function (): void {
    expect(fn() => Coordinates::fromDegrees(91.0, 0.0))->toThrow(InvalidCoordinatesException::class);
    expect(fn() => Coordinates::fromDegrees(true, 0.0))->toThrow(ValidationException::class);
    expect(fn() => Coordinates::fromArray(['lat' => 1.0, 'lon' => 2.0]))->toThrow(InvalidCoordinatesException::class);
});

it('round-trips civil dates and handles leap years', function (): void {
    $date = CivilDate::fromYmd(2024, 2, 29);
    expect($date->isoformat())->toBe('2024-02-29')
        ->and($date->plusDays(1)->isoformat())->toBe('2024-03-01')
        ->and(CivilDate::fromDateTime(new DateTimeImmutable('2015-07-12', new DateTimeZone('UTC')))->isoformat())->toBe('2015-07-12');

    expect(fn() => CivilDate::fromYmd(2023, 2, 29))->toThrow(ValidationException::class);
    expect(fn() => CivilDate::fromYmd(1800, 1, 1))->toThrow(ValidationException::class);
});

it('unwinds angles idempotently', function (): void {
    expect(Angle::fromDegrees(720.0)->normalized()->degrees)->toBe(0.0)
        ->and(Angle::fromDegrees(-90.0)->normalized()->degrees)->toBe(270.0)
        ->and(Angle::fromDegrees(350.0)->normalized()->normalized()->degrees)->toBe(350.0);
});

it('rounds minutes half-up with rollover in UTC only', function (): void {
    $base = new DateTimeImmutable('2015-07-12 12:34:30', new DateTimeZone('UTC'));
    expect(Minutes::roundedHalfUp($base)->format('H:i:s'))->toBe('12:35:00');

    $edge = new DateTimeImmutable('2015-12-31 23:59:45', new DateTimeZone('UTC'));
    expect(Minutes::roundedHalfUp($edge)->format('Y-m-d H:i'))->toBe('2016-01-01 00:00');

    $nonUtc = new DateTimeImmutable('2015-07-12 12:34:30', new DateTimeZone('America/New_York'));
    expect(fn() => Minutes::roundedHalfUp($nonUtc))->toThrow(ValidationException::class);
});

it('computes Julian Day for a known date', function (): void {
    // 2000-01-01 00:00 UTC => JD 2451544.5
    $jd = JulianDay::fromCivilDate(CivilDate::fromYmd(2000, 1, 1))->toFloat();
    expect(abs($jd - 2451544.5))->toBeLessThan(1e-9);
});
