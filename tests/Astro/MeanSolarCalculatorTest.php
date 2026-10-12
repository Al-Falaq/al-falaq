<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Astro;

use AlFalaq\AlFalaq\Astro\Services\MeanSolarCalculator;
use AlFalaq\AlFalaq\Astro\ValueObjects\SolarTime;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\AstronomicalException;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\JulianDay;
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

    public function testDeclinationAndEotAtSolsticesAndEquinoxes(): void
    {
        // Noon JDs verified via JulianDay::fromCivilDate()->toFloat() + 0.5
        // (task-listed 2460756.0/2460940.0 are a day off; corrected here).
        $noon = static fn(int $y, int $m, int $d): float => JulianDay::fromCivilDate(CivilDate::fromYmd($y, $m, $d))->toFloat() + 0.5;

        self::assertSame(2460848.0, $noon(2025, 6, 21));
        self::assertSame(2461031.0, $noon(2025, 12, 21));
        self::assertSame(2460755.0, $noon(2025, 3, 20));
        self::assertSame(2460941.0, $noon(2025, 9, 22));

        // Oracle: engine outputs at the verified noon JDs (solstice ~±23.44 deg, equinox ~0 deg).
        // The 2025-03-20 vector also covers the closestAngle |a| > 180 wrap via the
        // public API (pre-wrap raw ~358 deg straddling 0/360 at equinox), so no seam is needed.
        $calc = new MeanSolarCalculator();

        self::assertEqualsWithDelta(23.437986011489127, $calc->declination(2460848.0), 1e-9);
        self::assertEqualsWithDelta(-1.877726531614626, $calc->equationOfTime(2460848.0), 1e-9);
        self::assertEqualsWithDelta(-23.43829565413616, $calc->declination(2461031.0), 1e-9);
        self::assertEqualsWithDelta(1.8118972631640113, $calc->equationOfTime(2461031.0), 1e-9);
        self::assertEqualsWithDelta(0.051876763952494256, $calc->declination(2460755.0), 1e-9);
        self::assertEqualsWithDelta(-7.3861146271274265, $calc->equationOfTime(2460755.0), 1e-9);
        self::assertEqualsWithDelta(0.10361645537528823, $calc->declination(2460941.0), 1e-9);
        self::assertEqualsWithDelta(7.373036802749785, $calc->equationOfTime(2460941.0), 1e-9);
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

    public function testHourAngleRejectsNonFiniteAngle(): void
    {
        $calc = new MeanSolarCalculator();
        $coords = Coordinates::fromDegrees(3.1390, 101.6869);
        $date = CivilDate::fromYmd(2025, 1, 15);

        foreach ([NAN, INF, -INF] as $bad) {
            try {
                $calc->hourAngle($coords, $date, 0.0, $bad, false);
                self::fail('Expected ValidationException for angle ' . var_export($bad, true));
            } catch (ValidationException $e) {
                self::assertNotSame('', $e->getMessage());
            }
        }
    }

    public function testSolarTimeRejectsNonUtc(): void
    {
        $utc = new \DateTimeZone('UTC');
        $transit = new \DateTimeImmutable('2025-01-15 05:24:00', $utc);
        $sunrise = new \DateTimeImmutable('2025-01-14 23:24:00', $utc);
        $sunsetNy = new \DateTimeImmutable('2025-01-15 11:21:00', $utc)->setTimezone(new \DateTimeZone('America/New_York'));

        $this->expectException(ValidationException::class);

        new SolarTime($transit, $sunrise, $sunsetNy);
    }
}
