<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Astro;

use AlFalaq\AlFalaq\Astro\Services\KarneyInverse;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

/**
 * Vendored Karney inverse on WGS84: oracle vectors + degenerates.
 *
 * Transcription of the alfalak pin's tests/astronomy/test_geodesy.py
 * (commit a7baa8b). Reference values come from GeographicLib 2.1
 * (Charles Karney), generated via ``Geodesic.WGS84.Inverse`` into
 * Makkah (21.4225241, 39.8261818). The tolerances below encode the slice
 * acceptance bar (<=1e-6 deg / <=1 m), not the observed agreement.
 */
final class KarneyInverseTest extends TestCase
{
    /**
     * Makkah latitude in degrees (pin Constants.py:8).
     */
    private const MAKKA_LAT = 21.4225241;

    /**
     * Makkah longitude in degrees (pin Constants.py:8).
     */
    private const MAKKA_LON = 39.8261818;

    public function testWgs84DefiningConstantsExact(): void
    {
        self::assertSame(6378137.0, KarneyInverse::SEMI_MAJOR_M);
        self::assertEqualsWithDelta(1 / 298.257223563, KarneyInverse::FLATTENING, 1e-18);
    }

    /**
     * City oracle vectors into Makkah (pin parametrize list, verbatim).
     *
     * The gates are degrees for direction and metres for distance; the pin
     * carries them in metres and this file converts to the engine's km.
     */
    public function testInverseCityGoldens(): void
    {
        $cases = [
            [35.7750, -78.6336, 55.739246718, 10961845.992388], // Raleigh
            [40.7128, -74.0060, 58.396026135, 10323912.652457], // New York
            [51.5074, -0.1278, 118.868402852, 4794752.668858], // London
            [30.0444, 31.2357, 135.982352393, 1285592.277039], // Cairo
            [3.1390, 101.6869, -67.557623661, 6979153.497455], // Kuala Lumpur
            [-6.2088, 106.8456, -64.975302310, 7922244.964121], // Jakarta
            [-33.8688, 151.2093, -82.681155653, 13236950.906872], // Sydney
        ];

        foreach ($cases as [$lat, $lon, $azi, $s12]) {
            $solved = (new KarneyInverse())->inverse($lat, $lon, self::MAKKA_LAT, self::MAKKA_LON);

            self::assertTrue(abs($solved['direction'] - $azi) <= 1e-6, sprintf(
                '%s,%s direction: got %.12f, expected %.12f.',
                $lat,
                $lon,
                $solved['direction'],
                $azi,
            ));
            self::assertTrue(abs($solved['distanceKm'] * 1000.0 - $s12) <= 1e-3, sprintf(
                '%s,%s distance: got %.6f m, expected %.6f m.',
                $lat,
                $lon,
                $solved['distanceKm'] * 1000.0,
                $s12,
            ));
            self::assertFalse(is_nan($solved['direction']));
            self::assertFalse(is_nan($solved['distanceKm']));
        }
    }

    public function testInverseNearAntipodalConverges(): void
    {
        // Vincenty's documented failure regime; Karney must converge.
        $solved = (new KarneyInverse())->inverse(-20.4, -139.2, self::MAKKA_LAT, self::MAKKA_LON);

        self::assertTrue(abs($solved['direction'] - 31.740215702) <= 1e-6);
        self::assertTrue(abs($solved['distanceKm'] * 1000.0 - 19862471.480885) <= 1e-3);
    }

    public function testInverseTrueAntipodeNoRaise(): void
    {
        $solved = (new KarneyInverse())->inverse(
            -self::MAKKA_LAT,
            self::MAKKA_LON - 180,
            self::MAKKA_LAT,
            self::MAKKA_LON,
        );

        self::assertTrue(abs($solved['distanceKm'] * 1000.0 - 20003931.458625) <= 1e-3);
        self::assertFalse(is_nan($solved['direction']));
        self::assertTrue(is_finite($solved['direction']));
        // Exact-antipode azimuth is deterministic (+180.0): the swap logic
        // must not flip it by half a turn (mutation gate).
        self::assertTrue(abs($solved['direction'] - 180.0) <= 1e-9);
    }

    public function testInverseCoincidentReturnsZeroDistance(): void
    {
        $solved = (new KarneyInverse())->inverse(
            self::MAKKA_LAT,
            self::MAKKA_LON,
            self::MAKKA_LAT,
            self::MAKKA_LON,
        );

        self::assertTrue(abs($solved['distanceKm'] * 1000.0) <= 1e-9);
        self::assertFalse(is_nan($solved['direction']));
    }

    public function testInversePoles(): void
    {
        $engine = new KarneyInverse();

        $north = $engine->inverse(90.0, 0.0, self::MAKKA_LAT, self::MAKKA_LON);

        self::assertTrue(abs($north['direction'] - 140.1738182) <= 1e-6, 'north pole bearing');
        self::assertTrue(abs($north['distanceKm'] * 1000.0 - 7632107.081517) <= 1e-3, 'north pole distance');

        $south = $engine->inverse(-90.0, 0.0, self::MAKKA_LAT, self::MAKKA_LON);

        self::assertTrue(abs($south['direction'] - 39.8261818) <= 1e-6, 'south pole bearing');
        self::assertTrue(abs($south['distanceKm'] * 1000.0 - 12371824.377108) <= 1e-3, 'south pole distance');
    }

    public function testInverseRejectsBadInput(): void
    {
        $bad = [
            'lat-91' => [[91.0, 0.0], 'latitude'],
            'lat-minus-91' => [[-91.0, 0.0], 'latitude'],
            'lat-nan' => [[NAN, 0.0], 'latitude'],
            'lon-inf' => [[0.0, INF], 'longitude'],
        ];
        $engine = new KarneyInverse();

        foreach ($bad as $name => [$point, $keyword]) {
            try {
                $engine->inverse($point[0], $point[1], self::MAKKA_LAT, self::MAKKA_LON);

                self::fail(sprintf('%s: expected ValidationException.', $name));
            } catch (ValidationException $e) {
                // Pin: pytest.raises(ValidationError, match="(?i)latitude|longitude")
                self::assertStringContainsStringIgnoringCase($keyword, $e->getMessage(), $name);
                self::assertStringContainsString('must be within', $e->getMessage(), $name);

                if ($name === 'lat-nan') {
                    // floatRepr renders NaN as 'nan' (mutation gate).
                    self::assertStringContainsString('nan', $e->getMessage(), $name);
                }

                if ($name === 'lon-inf') {
                    self::assertStringContainsString('inf', $e->getMessage(), $name);
                }
            }
        }
    }

    public function testInverseRejectsBadSecondPoint(): void
    {
        $bad = [
            'lat2-91' => [[91.0, 0.0], 'latitude'],
            'lat2-minus-91' => [[-91.0, 0.0], 'latitude'],
            'lat2-nan' => [[NAN, 0.0], 'latitude'],
            'lon2-inf' => [[0.0, INF], 'longitude'],
        ];
        $engine = new KarneyInverse();

        foreach ($bad as $name => [$point, $keyword]) {
            try {
                $engine->inverse(self::MAKKA_LAT, self::MAKKA_LON, $point[0], $point[1]);

                self::fail(sprintf('%s: expected ValidationException.', $name));
            } catch (ValidationException $e) {
                self::assertStringContainsStringIgnoringCase($keyword, $e->getMessage(), $name);
                self::assertStringContainsString('must be within', $e->getMessage(), $name);
            }
        }
    }

    public function testInverseAzimuthNeverMinus180OrNegativeZero(): void
    {
        // Documented range is (-180, 180]: exact -180 must map to 180, and a
        // zero azimuth must be +0.0 so fmod(-0.0, 360) downstream stays -0.0.
        $solved = (new KarneyInverse())->inverse(0.0, 0.0, 0.0, 180.0);

        self::assertTrue($solved['direction'] !== -180.0);
        self::assertTrue(
            $solved['direction'] !== 0.0 || fdiv(1.0, $solved['direction']) > 0,
            'azimuth must be +0.0, not -0.0',
        );

        $solved = (new KarneyInverse())->inverse(0.0, 0.0, 0.0, -180.0);

        self::assertTrue($solved['direction'] !== -180.0);
        self::assertSame(0.0, $solved['direction']);
        self::assertTrue(fdiv(1.0, $solved['direction']) > 0.0);
    }

    public function testInverseForcedNonConvergenceGuardIsUnreachable(): void
    {
        // The pin forces the guard with monkeypatch.setattr(_MAXIT2, 0), which
        // PHP cannot do (constants are compile-time). The guard is therefore
        // dead-code defense in this transcription: it can only fire if the
        // iteration above it is broken, so the proof is that the near-
        // antipodal case (the hardest regime for the iteration) never throws.
        $solved = (new KarneyInverse())->inverse(-20.4, -139.2, self::MAKKA_LAT, self::MAKKA_LON);

        self::assertTrue(abs($solved['direction'] - 31.740215702) <= 1e-6);
        self::assertTrue($solved['distanceKm'] > 19000.0);
    }

    public function testInverseAlongEquator(): void
    {
        // Equatorial branch: s12 = a * lam12, due-east azimuth.
        $solved = (new KarneyInverse())->inverse(0.0, 0.0, 0.0, 90.0);

        self::assertTrue(abs($solved['direction'] - 90.0) <= 1e-9);
        self::assertTrue(abs($solved['distanceKm'] * 1000.0 - KarneyInverse::SEMI_MAJOR_M * M_PI / 2) <= 1e-3);
    }

    public function testInverseShortLineUsesAuxiliarySphere(): void
    {
        // Metre-scale line: exercises the really-short-line path.
        $solved = (new KarneyInverse())->inverse(self::MAKKA_LAT, self::MAKKA_LON, 21.42253, 39.82619);

        self::assertTrue($solved['distanceKm'] * 1000.0 > 0.5);
        self::assertTrue($solved['distanceKm'] * 1000.0 < 5.0);
        self::assertFalse(is_nan($solved['direction']));
    }

    public function testInverseSubMillimetreLineTakesShortLineBranch(): void
    {
        // A 1e-8 degree diagonal step (sub-millimetre, and NOT a meridian, so
        // the meridian shortcut is skipped): sigma is far below the _ETOL2
        // threshold, so _inverse_start's really-short-line branch runs and
        // returns the auxiliary-sphere distance (sig12 >= 0).
        $offset = 1e-8 / sqrt(2.0);
        $solved = (new KarneyInverse())->inverse(
            self::MAKKA_LAT,
            self::MAKKA_LON,
            self::MAKKA_LAT + $offset,
            self::MAKKA_LON + $offset,
        );

        self::assertTrue($solved['distanceKm'] * 1000.0 > 0.0);
        self::assertTrue($solved['distanceKm'] * 1000.0 < 0.002);
        self::assertTrue($solved['direction'] > 40.0 && $solved['direction'] < 50.0);
        self::assertFalse(is_nan($solved['distanceKm']));
    }

    public function testInverseRejectsLongitudeOutOfRange(): void
    {
        $pairs = [
            [0.0, 181.0, self::MAKKA_LAT, self::MAKKA_LON],
            [0.0, -181.0, self::MAKKA_LAT, self::MAKKA_LON],
            [self::MAKKA_LAT, self::MAKKA_LON, 0.0, 200.0],
            [0.0, 0.0, 0.0, 540.0],
        ];
        $engine = new KarneyInverse();

        foreach ($pairs as [$lat1, $lon1, $lat2, $lon2]) {
            try {
                $engine->inverse($lat1, $lon1, $lat2, $lon2);

                self::fail(sprintf('expected ValidationException for %s,%s -> %s,%s.', $lat1, $lon1, $lat2, $lon2));
            } catch (ValidationException $e) {
                self::assertStringContainsStringIgnoringCase('longitude', $e->getMessage());
            }
        }
    }

    public function testInverseEdgePairsFinite(): void
    {
        $pairs = [
            [20.0, 0.0, -20.0, 60.0], // symmetric reduced latitudes
            [80.0, 0.0, 80.0, 90.0], // high-latitude equal-latitude pair
            [10.0, 0.0, 80.0, 0.0], // swapped: |lat1| < |lat2|, meridian
            [-21.0, -139.0, self::MAKKA_LAT, self::MAKKA_LON], // near-antipodal, off-axis
            [0.0, -140.17, self::MAKKA_LAT, self::MAKKA_LON], // near-antipodal, equatorial
        ];
        $engine = new KarneyInverse();

        foreach ($pairs as [$lat1, $lon1, $lat2, $lon2]) {
            $solved = $engine->inverse($lat1, $lon1, $lat2, $lon2);

            self::assertTrue(is_finite($solved['direction']));
            self::assertTrue(is_finite($solved['distanceKm']));
            self::assertTrue($solved['distanceKm'] >= 0.0);
        }
    }

    public function testInverseSeededSweepInvariants(): void
    {
        // PHP mt_srand/mt_rand cannot reproduce the pin's
        // random.Random(20261003) stream, so this is invariant-only (never
        // value parity against Python): finiteness, sign, range, and the
        // distance symmetry that must hold for a true metric.
        mt_srand(1234);

        for ($i = 0; $i < 100; $i++) {
            $lat1 = mt_rand(-9000, 9000) / 100.0;
            $lon1 = mt_rand(-18000, 18000) / 100.0;
            $lat2 = mt_rand(-9000, 9000) / 100.0;
            $lon2 = mt_rand(-18000, 18000) / 100.0;
            $engine = new KarneyInverse();

            $forward = $engine->inverse($lat1, $lon1, $lat2, $lon2);
            $backward = $engine->inverse($lat2, $lon2, $lat1, $lon1);

            self::assertTrue(is_finite($forward['direction']), sprintf('direction %d', $i));
            self::assertTrue(is_finite($forward['distanceKm']), sprintf('distance %d', $i));
            self::assertTrue($forward['distanceKm'] >= 0.0, sprintf('non-negative %d', $i));
            self::assertTrue($forward['direction'] > -180.0 && $forward['direction'] <= 180.0, sprintf(
                'range %d: %g',
                $i,
                $forward['direction'],
            ));

            if ($i < 10) {
                self::assertTrue(
                    abs($forward['distanceKm'] - $backward['distanceKm'])
                        <= 1e-9 * $forward['distanceKm'],
                    sprintf('symmetry %d: %.12f vs %.12f', $i, $forward['distanceKm'], $backward['distanceKm']),
                );
            }
        }
    }

    public function testInverseDeterministicSweepInvariants(): void
    {
        // Branch-forcing sweep on top of the seeded one: poles, antipodes,
        // equator, meridians, equal latitudes, near-antipodes.
        $points = [
            [90.0, 0.0],
            [-90.0, 0.0],
            [90.0, 180.0],
            [-90.0, -180.0],
            [0.0, 0.0],
            [0.0, 180.0],
            [0.0, -180.0],
            [0.0, 90.0],
            [0.0, -90.0],
            [0.0, 179.9],
            [0.0, -179.9],
            [45.0, 0.0],
            [-45.0, 0.0],
            [45.0, 180.0],
            [-45.0, -180.0],
            [45.0, 45.0],
            [45.0, -45.0],
            [89.9, 0.0],
            [-89.9, 0.0],
            [89.9, 179.0],
            [-89.9, -179.0],
            [21.4225241, 39.8261818],
            [10.0, 0.0],
            [80.0, 0.0],
            [-20.4, -139.2],
            [-21.0, -139.0],
            [0.0, -140.17],
            [20.0, 60.0],
            [-20.0, 60.0],
            [12.0, 8.0],
            [-12.0, -8.0],
        ];
        $engine = new KarneyInverse();

        foreach ($points as $i => $from) {
            foreach ($points as $j => $to) {
                $solved = $engine->inverse($from[0], $from[1], $to[0], $to[1]);

                self::assertTrue(is_finite($solved['direction']), sprintf('%d,%d direction', $i, $j));
                self::assertTrue(is_finite($solved['distanceKm']), sprintf('%d,%d distance', $i, $j));
                self::assertTrue($solved['distanceKm'] >= 0.0, sprintf('%d,%d sign', $i, $j));
                self::assertTrue(
                    $solved['direction'] > -180.0 && $solved['direction'] <= 180.0,
                    sprintf('%d,%d range %g', $i, $j, $solved['direction']),
                );

                $reversed = $engine->inverse($to[0], $to[1], $from[0], $from[1]);

                self::assertTrue(
                    abs($solved['distanceKm'] - $reversed['distanceKm']) <= 1e-9 * $solved['distanceKm'],
                    sprintf('%d,%d symmetry %.12f vs %.12f', $i, $j, $solved['distanceKm'], $reversed['distanceKm']),
                );
            }
        }
    }

    public function testInverseAntipodalPairsStayFiniteAndOpposite(): void
    {
        mt_srand(20261003);
        $engine = new KarneyInverse();

        for ($i = 0; $i < 50; $i++) {
            $lat = mt_rand(-9000, 9000) / 100.0;
            $lon = mt_rand(-18000, 17999) / 100.0;
            // Antipode longitude wrapped back into [-180, 180].
            $antilon = $lon >= 0.0 ? $lon - 180.0 : $lon + 180.0;
            $solved = $engine->inverse($lat, $lon, -$lat, $antilon);

            self::assertTrue(is_finite($solved['direction']), sprintf('direction %d', $i));
            self::assertTrue(is_finite($solved['distanceKm']), sprintf('distance %d', $i));
            self::assertTrue($solved['distanceKm'] > 19000.0, sprintf('half circumference %d', $i));
            self::assertTrue($solved['direction'] > -180.0 && $solved['direction'] <= 180.0, sprintf('range %d', $i));
        }
    }

    /**
     * Implementation-pinned regression goldens at tight tolerance.
     *
     * These are NOT independent oracles (no GeographicLib here to re-derive
     * them): they pin this transcription's deterministic outputs at 1e-9 deg
     * / 1e-6 m so any arithmetic or branch perturbation that propagates to an
     * output fails loudly. Absolute correctness is anchored by
     * testInverseCityGoldens (GeographicLib 2.1 vectors); the sweeps above
     * separately pin forward/backward distance symmetry.
     */
    public function testInverseExtendedGoldensTight(): void
    {
        $mLat = self::MAKKA_LAT;
        $mLon = self::MAKKA_LON;
        $cases = [
            // Near-antipodal (tripolar iteration regime).
            [-20.4, -139.2, $mLat, $mLon, 31.740215702329, 19862471.480885372],
            [-21.0, -139.0, $mLat, $mLon, 57.125544853124, 19896782.918205902],
            [0.0, -140.17, $mLat, $mLon, 0.009538144550, 17634072.775451433],
            [0.5, 0.5, -0.5, 179.5, 90.001730116549, 19926190.521422211],
            // Symmetric reduced latitudes, high-latitude, swapped meridian.
            [20.0, 0.0, -20.0, 60.0, 120.470313168976, 7895615.962187378],
            [80.0, 0.0, 80.0, 90.0, 45.438518976712, 1575399.990903556],
            [10.0, 0.0, 80.0, 0.0, 0.0, 7779285.038702502],
            // Cross-hemisphere and antimeridian pairs.
            [12.0, 8.0, -12.0, -8.0, -145.767947246878, 3189152.326687072],
            [45.0, 45.0, 45.0, -45.0, -54.726097228269, 6690232.932542715],
            [89.9, 0.0, 89.9, 179.0, 0.500000761506, 22337.945088767],
            [0.0, 179.9, 0.0, -179.9, 90.0, 22263.898158653],
            [48.8566, 2.3522, -33.9249, 18.4241, 166.591387571295, 9306838.237282539],
            // Southern hemisphere and European cities into Makkah.
            [35.6762, 139.6503, $mLat, $mLon, -66.927911594798, 9487882.454906663],
            [-23.5505, -46.6333, $mLat, $mLon, 69.094344494204, 10601047.883590108],
            [55.7558, 37.6173, $mLat, $mLon, 176.345583403008, 3815937.840578200],
            [64.1466, -21.9426, $mLat, $mLon, 106.027463055326, 6522778.870971395],
            [-41.2865, 174.7762, $mLat, $mLon, -103.871911040397, 15271815.533873595],
            [71.0, 25.0, 71.5, 26.0, 32.281525096503, 66334.357251106],
        ];
        $engine = new KarneyInverse();

        foreach ($cases as [$lat1, $lon1, $lat2, $lon2, $azi, $s12m]) {
            $solved = $engine->inverse($lat1, $lon1, $lat2, $lon2);
            $tag = sprintf('%s,%s -> %s,%s', $lat1, $lon1, $lat2, $lon2);

            self::assertTrue(abs($solved['direction'] - $azi) <= 1e-9, sprintf(
                '%s direction: got %.12f, expected %.12f.',
                $tag,
                $solved['direction'],
                $azi,
            ));
            self::assertTrue(abs($solved['distanceKm'] * 1000.0 - $s12m) <= 1e-6, sprintf(
                '%s distance: got %.9f m, expected %.9f m.',
                $tag,
                $solved['distanceKm'] * 1000.0,
                $s12m,
            ));
        }
    }

    public function testInverseShortLineGoldensTight(): void
    {
        // Metre-scale and sub-millimetre lines pin the auxiliary-sphere
        // short-line branch (xthresh/etol2 regime) at tight tolerance.
        $engine = new KarneyInverse();

        $metre = $engine->inverse(self::MAKKA_LAT, self::MAKKA_LON, 21.42253, 39.82619);

        self::assertTrue(abs($metre['direction'] - 52.460497476826) <= 1e-9, sprintf(
            'metre direction: got %.12f.',
            $metre['direction'],
        ));
        self::assertTrue(abs($metre['distanceKm'] * 1000.0 - 1.072138991) <= 1e-6, sprintf(
            'metre distance: got %.9f m.',
            $metre['distanceKm'] * 1000.0,
        ));

        $offset = 1e-8 / sqrt(2.0);
        $submm = $engine->inverse(
            self::MAKKA_LAT,
            self::MAKKA_LON,
            self::MAKKA_LAT + $offset,
            self::MAKKA_LON + $offset,
        );

        self::assertTrue(abs($submm['direction'] - 43.117267988326) <= 1e-9, sprintf(
            'sub-mm direction: got %.12f.',
            $submm['direction'],
        ));
        self::assertTrue(abs($submm['distanceKm'] * 1000.0 - 0.001072567) <= 1e-6, sprintf(
            'sub-mm distance: got %.9f m.',
            $submm['distanceKm'] * 1000.0,
        ));
    }
}
