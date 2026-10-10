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
     * @param array<string, mixed> $overrides Field replacements.
     * @return CrescentGeometry Valid geometry.
     */
    private function geometry(array $overrides = []): CrescentGeometry
    {
        $fields = array_merge([
            'sunAltitude' => -0.8325,
            'moonAltitude' => 4.2545,
            'elongationDeg' => 6.1740,
            'moonAgeHours' => 10.0,
            'lagMinutes' => 24.7257,
            'deltaAzimuthDeg' => 1.0279,
            'illuminatedFraction' => 0.0029,
            'arcvGeoDeg' => 6.0880,
            'widthArcmin' => 0.0953,
            'ageAtMoonsetHours' => 10.4121,
        ], $overrides);

        return new CrescentGeometry(
            $fields['sunAltitude'],
            $fields['moonAltitude'],
            $fields['elongationDeg'],
            $fields['moonAgeHours'],
            $fields['lagMinutes'],
            $fields['deltaAzimuthDeg'],
            $fields['illuminatedFraction'],
            $fields['arcvGeoDeg'],
            $fields['widthArcmin'],
            $fields['ageAtMoonsetHours'],
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
        $geometry = $this->geometry(['lagMinutes' => null, 'ageAtMoonsetHours' => null]);

        self::assertNull($geometry->lagMinutes);
        self::assertNull($geometry->ageAtMoonsetHours);
    }

    public function testHalfNullWindowIsRejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->geometry(['lagMinutes' => null]);
    }

    public function testReverseHalfNullWindowIsRejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->geometry(['ageAtMoonsetHours' => null]);
    }

    public function testNonFiniteNullableFieldIsRejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->geometry(['lagMinutes' => INF, 'ageAtMoonsetHours' => 10.4121]);
    }

    public function testNonFiniteStoredFieldIsRejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->geometry(['arcvGeoDeg' => INF]);
    }

    public function testNegativeWidthIsStoredRaw(): void
    {
        $geometry = $this->geometry(['widthArcmin' => -0.5]);

        self::assertSame(-0.5, $geometry->widthArcmin);
    }
}
