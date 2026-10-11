<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Hijri;

use AlFalaq\AlFalaq\Hijri\Enums\MabimsCountry;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

/**
 * MABIMS country key + national proxy points (pin MabimsCalendar).
 */
final class MabimsCountryTest extends TestCase
{
    /**
     * fromString is case-insensitive and rejects unknown codes.
     *
     * @return void
     */
    public function testFromStringIsCaseInsensitive(): void
    {
        self::assertSame(MabimsCountry::MY, MabimsCountry::fromString('my'));
        self::assertSame(MabimsCountry::ID, MabimsCountry::fromString('Id'));
        self::assertSame(MabimsCountry::SG, MabimsCountry::fromString('SG'));
        self::assertSame(MabimsCountry::BN, MabimsCountry::fromString('bn'));
    }

    /**
     * Unknown codes raise a ValidationException.
     *
     * @return void
     */
    public function testFromStringRejectsUnknownCode(): void
    {
        $this->expectException(ValidationException::class);
        MabimsCountry::fromString('xx');
    }

    /**
     * Proxy coordinates match the pin's 4-dp reference points.
     *
     * @return void
     */
    public function testProxyCoordinatesMatchThePin(): void
    {
        $expected = [
            'MY' => [3.1390, 101.6869],
            'ID' => [-6.2088, 106.8456],
            'SG' => [1.3521, 103.8198],
            'BN' => [4.8903, 114.9420],
        ];

        foreach (MabimsCountry::cases() as $country) {
            $coordinates = $country->referenceCoordinates();
            [$latitude, $longitude] = $expected[$country->value];

            self::assertEqualsWithDelta($latitude, $coordinates->latitude, 1e-9, $country->value);
            self::assertEqualsWithDelta($longitude, $coordinates->longitude, 1e-9, $country->value);
        }
    }
}
