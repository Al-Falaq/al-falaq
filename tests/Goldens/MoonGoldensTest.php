<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Goldens;

use AlFalaq\AlFalaq\Astro\Services\DeltaTProvider;
use AlFalaq\AlFalaq\Moon\Services\CrescentCalculator;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\AstronomicalException;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;
use PHPUnit\Framework\TestCase;

/**
 * Moon golden parity: 24-row crescent sweep, eclipse anchors, agreement
 * matrix, degenerate trio, KL deep pin (M3 characterization gate).
 *
 * Helper shape mirrors QiblaGoldensTest (loadGolden/field idiom),
 * generalized with a filename parameter for the two moon CSVs.
 */
final class MoonGoldensTest extends TestCase
{
    public function testCrescentRowsMatchGoldens(): void
    {
        $calculator = CrescentCalculator::default();
        $file = 'moon_crescent.csv';
        $loaded = GoldenLoader::load(__DIR__ . '/' . $file);

        self::assertCount(24, $loaded['rows']);

        foreach ($loaded['rows'] as $index => $row) {
            $line = $index + 2;
            [$y, $m, $d] = array_map('intval', explode('-', '2025-02-28'));
            $geometry = $calculator->atSunset(
                Coordinates::fromDegrees((float) self::field($row, 'latitude', $line, $file), (float) self::field($row, 'longitude', $line, $file)),
                CivilDate::fromYmd($y, $m, $d),
            );

            self::assertTrue(abs($geometry->elongationDeg - (float) self::field($row, 'arcl_deg', $line, $file)) <= 0.01, 'arcl line ' . $line);
            self::assertTrue(abs($geometry->moonAltitude - (float) self::field($row, 'moon_alt_topo_deg', $line, $file)) <= 0.01, 'malt line ' . $line);
            self::assertTrue(abs($geometry->arcvGeoDeg - (float) self::field($row, 'arcv_geo_deg', $line, $file)) <= 0.01, 'ageo line ' . $line);
            self::assertTrue(abs(($geometry->moonAltitude - $geometry->sunAltitude) - (float) self::field($row, 'arcv_topo_deg', $line, $file)) <= 0.01, 'atopo line ' . $line);
            self::assertTrue(abs($geometry->widthArcmin - (float) self::field($row, 'width_arcmin', $line, $file)) <= 0.01, 'width line ' . $line);
            $lagCell = $row['lag_hours'] ?? '';
            if ($lagCell === '') {
                self::assertNull($geometry->lagMinutes, 'lag null line ' . $line);
            } else {
                self::assertNotNull($geometry->lagMinutes, 'lag null line ' . $line);
                self::assertTrue(abs($geometry->lagMinutes / 60.0 - (float) $lagCell) <= 0.01, 'lag line ' . $line);
            }
            self::assertTrue(abs($geometry->moonAgeHours / 24.0 - (float) self::field($row, 'moon_age_days', $line, $file)) <= 0.01, 'age line ' . $line);
            $agemCell = $row['moon_age_at_moonset_days'] ?? '';
            if ($agemCell === '') {
                self::assertNull($geometry->ageAtMoonsetHours, 'agem null line ' . $line);
            } else {
                self::assertNotNull($geometry->ageAtMoonsetHours, 'agem null line ' . $line);
                self::assertTrue(abs($geometry->ageAtMoonsetHours / 24.0 - (float) $agemCell) <= 0.01, 'agem line ' . $line);
            }

            $verdicts = $calculator->evaluateGeometry($geometry);

            self::assertSame(self::field($row, 'yallop_zone', $line, $file), $verdicts['yallop']->zone, 'yallop zone line ' . $line);
            self::assertTrue(abs($verdicts['yallop']->score - (float) self::field($row, 'yallop_q', $line, $file)) <= 0.01, 'q line ' . $line);
            self::assertSame(self::field($row, 'odeh_class', $line, $file), $verdicts['odeh']->zone, 'odeh class line ' . $line);
            self::assertTrue(abs($verdicts['odeh']->score - (float) self::field($row, 'odeh_v', $line, $file)) <= 0.01, 'V line ' . $line);
            self::assertSame(self::field($row, 'neo_mabims_2021', $line, $file), $verdicts['neomabims2021']->visible ? 'true' : 'false', 'neo line ' . $line);
            self::assertSame(self::field($row, 'mabims_1992', $line, $file), $verdicts['mabims1992']->visible ? 'true' : 'false', 'old92 line ' . $line);
        }
    }

    public function testEclipseAnchorsAreInvisible(): void
    {
        $calculator = CrescentCalculator::default();
        $file = 'moon_eclipse.csv';
        $loaded = GoldenLoader::load(__DIR__ . '/' . $file);

        self::assertCount(2, $loaded['rows']);

        foreach ($loaded['rows'] as $index => $row) {
            $line = $index + 2;
            [$y, $m, $d] = array_map('intval', explode('-', self::field($row, 'date', $line, $file)));
            $coordinates = Coordinates::fromDegrees(
                (float) self::field($row, 'latitude', $line, $file),
                (float) self::field($row, 'longitude', $line, $file),
            );
            $date = CivilDate::fromYmd($y, $m, $d);
            $geometry = $calculator->atSunset($coordinates, $date);
            $verdicts = $calculator->evaluateGeometry($geometry);

            self::assertTrue(abs($geometry->elongationDeg - (float) self::field($row, 'arcl_deg', $line, $file)) <= 0.01, 'arcl line ' . $line);
            self::assertTrue(abs($geometry->moonAltitude - (float) self::field($row, 'moon_alt_topo_deg', $line, $file)) <= 0.01, 'malt line ' . $line);
            self::assertSame('F', $verdicts['yallop']->zone, 'yallop line ' . $line);
            self::assertSame('D', $verdicts['odeh']->zone, 'odeh line ' . $line);
            self::assertFalse($verdicts['neomabims2021']->visible, 'neo line ' . $line);
            self::assertFalse($verdicts['mabims1992']->visible, 'old92 line ' . $line);

            if (str_contains(self::field($row, 'city', $line, $file), 'Kuala Lumpur')) {
                // Pre-conjunction sunset: the moon sets before the sun, so the
                // lag is negative; conjunction falls between sunset and the
                // first hourly age sample, so the age reports the floor.
                self::assertLessThan(0.0, $geometry->lagMinutes ?? NAN, 'KL lag line ' . $line);
                self::assertSame(0.1 * 24.0, $geometry->moonAgeHours, 'KL age floor line ' . $line);
            }
        }
    }

    public function testAgreementMatrix(): void
    {
        $calculator = CrescentCalculator::default();

        // [lat, lon, y, m, d, yallop_zone, odeh_class, yallop_visible, odeh_visible, neo, old].
        $cases = [
            [3.1390, 101.6869, 2025, 2, 28, 'F', 'D', false, false, false, true],
            [21.4225, 39.8262, 2025, 2, 28, 'E', 'C', false, true, true, true],
            [3.1390, 101.6869, 2025, 3, 29, 'F', 'D', false, false, false, false],
            [21.4225, 39.8262, 2025, 3, 29, 'F', 'D', false, false, false, false],
        ];

        foreach ($cases as $case) {
            [$lat, $lon, $y, $m, $d, $zone, $class, $yallopVisible, $odehVisible, $neo, $old] = $case;
            $verdicts = $calculator->evaluate(
                Coordinates::fromDegrees($lat, $lon),
                CivilDate::fromYmd($y, $m, $d),
            );
            $label = sprintf('(%s,%s,%d-%02d-%02d)', $lat, $lon, $y, $m, $d);

            self::assertSame($zone, $verdicts['yallop']->zone, 'yallop ' . $label);
            self::assertSame($yallopVisible, $verdicts['yallop']->visible, 'yallop visible ' . $label);
            self::assertSame($class, $verdicts['odeh']->zone, 'odeh ' . $label);
            self::assertSame($odehVisible, $verdicts['odeh']->visible, 'odeh visible ' . $label);
            self::assertSame($neo, $verdicts['neomabims2021']->visible, 'neo ' . $label);
            self::assertSame($old, $verdicts['mabims1992']->visible, 'old92 ' . $label);
        }
    }

    public function testPolarNightRaisesAstronomicalException(): void
    {
        $this->expectException(AstronomicalException::class);

        CrescentCalculator::default()->atSunset(
            Coordinates::fromDegrees(69.6492, 18.9553),
            CivilDate::fromYmd(2025, 1, 1),
        );
    }

    public function testNullWindowsReturnFiniteGeometryWithFallback(): void
    {
        // Null-window rows: finite geometry, no sunset-to-moonset window.
        // Tromso Jan-15 arcl ≈ 160.86, Reykjavik Apr-02 arcl ≈ 61.26.
        $nullWindowCases = [
            [69.6492, 18.9553, 2025, 1, 15, 160.86],
            [64.1466, -21.9426, 2025, 4, 2, 61.26],
        ];

        foreach ($nullWindowCases as $case) {
            [$lat, $lon, $y, $m, $d, $arcl] = $case;
            $coordinates = Coordinates::fromDegrees($lat, $lon);
            $date = CivilDate::fromYmd($y, $m, $d);
            $calculator = CrescentCalculator::default();
            $geometry = $calculator->atSunset($coordinates, $date);
            $label = sprintf('(%s,%s,%d-%02d-%02d)', $lat, $lon, $y, $m, $d);

            self::assertTrue(is_finite($geometry->elongationDeg), $label);
            self::assertTrue(abs($geometry->elongationDeg - $arcl) <= 0.01, 'arcl ' . $label);
            self::assertNull($geometry->lagMinutes, $label);
            self::assertNull($geometry->ageAtMoonsetHours, $label);

            // Composition-root fallback must not throw on the null window
            // and must follow the alt/elongation-only branch.
            $verdicts = $calculator->evaluateGeometry($geometry);
            $expected = $geometry->moonAltitude >= 2.0 && $geometry->elongationDeg >= 3.0;

            self::assertSame($expected, $verdicts['mabims1992']->visible);
        }
    }

    public function testKualaLumpurAppendixDeepPin(): void
    {
        // The VO carries no used_delta_t_s/sunset_jd (engine-internal); dt is
        // asserted directly from the provider, geometry from the VO.
        $date = CivilDate::fromYmd(2025, 2, 28);

        self::assertSame(59, $date->dayOfYear());

        $deltaT = DeltaTProvider::forYear(2025 + ($date->dayOfYear() - 1) / 365.25);

        self::assertTrue(abs($deltaT - 74.56305038786272) <= 1e-6, 'used_delta_t_s = 74.56305038786272');

        $geometry = CrescentCalculator::default()->atSunset(
            Coordinates::fromDegrees(3.1390, 101.6869),
            $date,
        );

        self::assertTrue(abs($geometry->elongationDeg - 6.173972051753964) <= 1e-6, 'arcl');
        self::assertTrue(abs($geometry->arcvGeoDeg - 6.088018222865059) <= 1e-6, 'ageo');
        self::assertTrue(abs(($geometry->moonAltitude - $geometry->sunAltitude) - 5.086970505414731) <= 1e-6, 'atopo');
        self::assertTrue(abs($geometry->sunAltitude - (-0.8325033650264293)) <= 1e-6, 'salt');
        self::assertTrue(abs($geometry->moonAltitude - 4.254467140388302) <= 1e-6, 'malt');
        self::assertTrue(abs($geometry->deltaAzimuthDeg - 1.0278791375318406) <= 1e-6, 'daz');
        self::assertTrue(abs($geometry->widthArcmin - 0.09531389154651401) <= 1e-6, 'width');
        self::assertTrue(abs($geometry->illuminatedFraction - 0.002900038687049744) <= 1e-6, 'illum');
        self::assertNotNull($geometry->lagMinutes, 'lag null');
        self::assertTrue(abs($geometry->lagMinutes / 60.0 - 0.4120943493906335) <= 1e-6, 'lag');
        self::assertTrue(abs($geometry->moonAgeHours / 24.0 - 0.4166666666666667) <= 1e-6, 'age');
        self::assertNotNull($geometry->ageAtMoonsetHours, 'agem null');
        self::assertTrue(abs($geometry->ageAtMoonsetHours / 24.0 - 0.4338372645579431) <= 1e-6, 'agem');
    }


    /**
     * Require a non-empty string cell.
     *
     * @param array<string, string> $row Assoc row.
     * @param string $key Column name.
     * @param int $line CSV line number for failure messages.
     * @param string $file CSV basename for failure messages.
     * @return string Cell value.
     */
    private static function field(array $row, string $key, int $line, string $file): string
    {
        $value = $row[$key] ?? null;

        if (! is_string($value) || $value === '') {
            self::fail(sprintf('Missing %s in golden file %s line %d.', $key, $file, $line));
        }

        return $value;
    }
}
