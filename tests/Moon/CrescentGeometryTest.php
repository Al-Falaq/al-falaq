<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Moon;

use AlFalaq\AlFalaq\Moon\ValueObjects\CrescentGeometry;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

/**
 * Extended geometry bundle: new fields, null-window pairing, validation.
 */
final class CrescentGeometryTest extends TestCase
{
    /**
     * Build a valid bundle, overriding selected fields.
     *
     * @param ?float $sunAltitude Sun altitude override.
     * @param ?float $moonAltitude Moon altitude override.
     * @param ?float $elongationDeg Elongation override.
     * @param ?float $moonAgeHours Moon age override.
     * @param ?float $deltaAzimuthDeg Azimuth difference override.
     * @param ?float $illuminatedFraction Illuminated fraction override.
     * @param ?float $arcvGeoDeg Geocentric ARCV override.
     * @param ?float $widthArcmin Width override.
     * @param array{lagMinutes?: float|null, ageAtMoonsetHours?: float|null} $window Nullable window overrides.
     * @return CrescentGeometry Valid geometry.
     */
    private function geometry(
        ?float $sunAltitude = null,
        ?float $moonAltitude = null,
        ?float $elongationDeg = null,
        ?float $moonAgeHours = null,
        ?float $deltaAzimuthDeg = null,
        ?float $illuminatedFraction = null,
        ?float $arcvGeoDeg = null,
        ?float $widthArcmin = null,
        array $window = [],
    ): CrescentGeometry {
        $lag = array_key_exists('lagMinutes', $window) ? $window['lagMinutes'] : 24.7257;
        $agem = array_key_exists('ageAtMoonsetHours', $window) ? $window['ageAtMoonsetHours'] : 10.4121;

        return new CrescentGeometry(
            $sunAltitude ?? -0.8325,
            $moonAltitude ?? 4.2545,
            $elongationDeg ?? 6.1740,
            $moonAgeHours ?? 10.0,
            $lag,
            $deltaAzimuthDeg ?? 1.0279,
            $illuminatedFraction ?? 0.0029,
            $arcvGeoDeg ?? 6.0880,
            $widthArcmin ?? 0.0953,
            $agem,
        );
    }

    public function testValidBundleStoresAllFields(): void
    {
        $geometry = $this->geometry();

        self::assertSame(-0.8325, $geometry->sunAltitude);
        self::assertSame(4.2545, $geometry->moonAltitude);
        self::assertSame(24.7257, $geometry->lagMinutes);
        self::assertSame(1.0279, $geometry->deltaAzimuthDeg);
        self::assertSame(6.0880, $geometry->arcvGeoDeg);
        self::assertSame(0.0953, $geometry->widthArcmin);
        self::assertSame(10.4121, $geometry->ageAtMoonsetHours);
    }

    public function testNullWindowPairIsAccepted(): void
    {
        $geometry = $this->geometry(window: ['lagMinutes' => null, 'ageAtMoonsetHours' => null]);

        self::assertNull($geometry->lagMinutes);
        self::assertNull($geometry->ageAtMoonsetHours);
    }

    public function testHalfNullWindowIsRejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->geometry(window: ['lagMinutes' => null]);
    }

    public function testReverseHalfNullWindowIsRejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->geometry(window: ['ageAtMoonsetHours' => null]);
    }

    public function testNonFiniteNullableFieldIsRejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->geometry(window: ['lagMinutes' => INF, 'ageAtMoonsetHours' => 10.4121]);
    }

    public function testNonFiniteStoredFieldIsRejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->geometry(arcvGeoDeg: INF);
    }

    public function testNegativeWidthIsStoredRaw(): void
    {
        $geometry = $this->geometry(widthArcmin: -0.5);

        self::assertSame(-0.5, $geometry->widthArcmin);
    }
}
