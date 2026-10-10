<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Astro;

use AlFalaq\AlFalaq\Astro\Services\MeanSolarCalculator;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\AstronomicalException;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;
use PHPUnit\Framework\TestCase;

/**
 * Meeus Ch.25 solar position transcribed from pin a7baa8b.
 *
 * Vectors are oracle outputs of pin Astronomical.solar_declination /
 * equation_of_time (author-verified 2026-10-10).
 */
final class MeanSolarCalculatorTest extends TestCase
{
    public function testDeclinationAndEotMatchPin(): void
    {
        $calc = new MeanSolarCalculator();

        self::assertEqualsWithDelta(-23.03251608590199, $calc->declination(2451545.0), 1e-9);
        self::assertEqualsWithDelta(-3.3007602707561516, $calc->equationOfTime(2451545.0), 1e-9);
        self::assertEqualsWithDelta(-22.998077976633684, $calc->declination(2460676.5), 1e-9);
        self::assertEqualsWithDelta(-3.4502404616062683, $calc->equationOfTime(2460676.5), 1e-9);
        self::assertEqualsWithDelta(-19.862498983037842, $calc->declination(2461000.25), 1e-9);
        self::assertEqualsWithDelta(14.267116404509053, $calc->equationOfTime(2461000.25), 1e-9);

        $pos = $calc->position(2451545.0);
        self::assertEqualsWithDelta(-23.03251608590199, $pos->declinationDeg, 1e-9);
        self::assertEqualsWithDelta(-3.3007602707561516, $pos->equationOfTimeMinutes, 1e-9);
    }

    public function testSolarTripleMatchesPin(): void
    {
        $calc = new MeanSolarCalculator();

        // KL 2025-01-15: sunrise/sunset fall on UTC 2025-01-14/15 (lon +6.78h) — expected.
        $kl = $calc->solarTime(
            Coordinates::fromDegrees(3.1390, 101.6869),
            CivilDate::fromYmd(2025, 1, 15),
            0.0,
        );
        self::assertSame('2025-01-15 05:22:39', $kl->transit->format('Y-m-d H:i:s'));
        self::assertSame('2025-01-14 23:23:50', $kl->sunrise->format('Y-m-d H:i:s'));
        self::assertSame('2025-01-15 11:21:28', $kl->sunset->format('Y-m-d H:i:s'));

        $fajr = $calc->hourAngle(
            Coordinates::fromDegrees(3.1390, 101.6869),
            CivilDate::fromYmd(2025, 1, 15),
            0.0,
            -18.0,
            false,
        );
        self::assertNotNull($fajr);
        self::assertSame('2025-01-14 22:10:09', $fajr->format('Y-m-d H:i:s'));

        // Oslo 2025-06-21: fajr/isha angles unreachable (polar-near) → null, never NaN.
        $oslo = Coordinates::fromDegrees(59.9139, 10.7522);
        $osloDate = CivilDate::fromYmd(2025, 6, 21);
        self::assertNull($calc->hourAngle($oslo, $osloDate, 0.0, -18.0, false));
        self::assertNull($calc->hourAngle($oslo, $osloDate, 0.0, -17.0, true));
        self::assertTrue($calc->isSolarDefined($oslo, $osloDate, 0.0));

        // Tromsø polar night: undefined → solarTime throws, probe returns false.
        $tromsoe = Coordinates::fromDegrees(69.6496, 18.9553);
        $polarDate = CivilDate::fromYmd(2025, 12, 15);
        self::assertFalse($calc->isSolarDefined($tromsoe, $polarDate, 0.0));
        $this->expectException(AstronomicalException::class);
        $calc->solarTime($tromsoe, $polarDate, 0.0);
    }

    public function testAfternoonMatchesPin(): void
    {
        // Oracle: pin SolarTime.afternoon (author-verified 2026-10-10).
        $calc = new MeanSolarCalculator();
        $coords = Coordinates::fromDegrees(3.1390, 101.6869);
        $date = CivilDate::fromYmd(2025, 1, 15);

        $shafi = $calc->afternoon($coords, $date, 0.0, 1.0);
        self::assertNotNull($shafi);
        self::assertSame('2025-01-15 08:46:29', $shafi->format('Y-m-d H:i:s'));

        $hanafi = $calc->afternoon($coords, $date, 0.0, 2.0);
        self::assertNotNull($hanafi);
        self::assertSame('2025-01-15 09:41:44', $hanafi->format('Y-m-d H:i:s'));
    }

    public function testElevationShiftsRiseSet(): void
    {
        // Oracle: pin SolarTime(elevation_m=100) (author-verified 2026-10-10).
        $calc = new MeanSolarCalculator();
        $triple = $calc->solarTime(
            Coordinates::fromDegrees(3.1390, 101.6869),
            CivilDate::fromYmd(2025, 1, 15),
            100.0,
        );

        self::assertSame('2025-01-14 23:22:34', $triple->sunrise->format('Y-m-d H:i:s'));
        self::assertSame('2025-01-15 11:22:44', $triple->sunset->format('Y-m-d H:i:s'));
    }

    public function testElevationValidationRejectsBadInput(): void
    {
        $calc = new MeanSolarCalculator();
        $coords = Coordinates::fromDegrees(3.1390, 101.6869);
        $date = CivilDate::fromYmd(2025, 1, 15);

        foreach ([true, '100', NAN, INF, -1.0, null] as $bad) {
            try {
                $calc->hourAngle($coords, $date, $bad, -18.0, false);
                self::fail('Expected ValidationException for elevation ' . var_export($bad, true));
            } catch (ValidationException $e) {
                self::assertNotSame('', $e->getMessage());
            }
        }
    }

    public function testShadowValidationRejectsBadInput(): void
    {
        $calc = new MeanSolarCalculator();
        $coords = Coordinates::fromDegrees(3.1390, 101.6869);
        $date = CivilDate::fromYmd(2025, 1, 15);

        foreach ([0.0, -1.0, NAN, INF, true, '1'] as $bad) {
            try {
                $calc->afternoon($coords, $date, 0.0, $bad);
                self::fail('Expected ValidationException for shadow ' . var_export($bad, true));
            } catch (ValidationException $e) {
                self::assertNotSame('', $e->getMessage());
            }
        }
    }
}
