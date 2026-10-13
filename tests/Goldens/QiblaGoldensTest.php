<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Goldens;

use AlFalaq\AlFalaq\Qibla\Services\QiblaCalculator;
use AlFalaq\AlFalaq\Qibla\ValueObjects\QiblaResult;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;
use PHPUnit\Framework\TestCase;

/**
 * Qibla golden structure gate (M2 preservation step).
 *
 * Gates CSV shape (header, 23 rows, ranges, precision, source tags, no
 * degenerate rows) plus magneticHeading vectors for the spherical goldens,
 * and the WGS84-ellipsoidal goldens alongside. The two oracles use
 * different source tags and constants, so the structural tests are
 * per-file rather than shared.
 *
 * Numeric engine parity of bearings/distances against the wired PHP
 * calculators is gated per file.
 */
final class QiblaGoldensTest extends TestCase
{
    private const GOLDEN_FILE = 'qibla_spherical.csv';

    private const WGS84_GOLDEN_FILE = 'qibla_wgs84.csv';

    private const ROW_COUNT = 23;

    public function testGoldenFileHasExpectedHeaderAndRowCount(): void
    {
        $loaded = GoldenLoader::load(__DIR__ . '/' . self::GOLDEN_FILE);

        self::assertSame(
            ['city', 'latitude', 'longitude', 'bearing_deg', 'distance_km', 'source'],
            $loaded['header'],
        );
        self::assertCount(self::ROW_COUNT, $loaded['rows']);
    }

    public function testRowsHaveValidRangesPrecisionAndSourceTags(): void
    {
        $seen = [];

        foreach (GoldenLoader::load(__DIR__ . '/' . self::GOLDEN_FILE)['rows'] as $index => $row) {
            $line = $index + 2;
            $latRaw = self::field($row, 'latitude', $line, self::GOLDEN_FILE);
            $lonRaw = self::field($row, 'longitude', $line, self::GOLDEN_FILE);
            $bearingRaw = self::field($row, 'bearing_deg', $line, self::GOLDEN_FILE);
            $distanceRaw = self::field($row, 'distance_km', $line, self::GOLDEN_FILE);
            $source = self::field($row, 'source', $line, self::GOLDEN_FILE);

            self::assertMatchesRegularExpression('/^-?\d+\.\d{4}$/', $latRaw, 'latitude precision line ' . $line);
            self::assertMatchesRegularExpression('/^-?\d+\.\d{4}$/', $lonRaw, 'longitude precision line ' . $line);
            self::assertMatchesRegularExpression('/^\d+\.\d{6}$/', $bearingRaw, 'bearing precision line ' . $line);
            self::assertMatchesRegularExpression('/^\d+\.\d{3}$/', $distanceRaw, 'distance precision line ' . $line);

            $lat = (float) $latRaw;
            $lon = (float) $lonRaw;
            $bearing = (float) $bearingRaw;
            $distance = (float) $distanceRaw;

            self::assertGreaterThanOrEqual(-90.0, $lat, 'latitude range line ' . $line);
            self::assertLessThanOrEqual(90.0, $lat, 'latitude range line ' . $line);
            self::assertGreaterThanOrEqual(-180.0, $lon, 'longitude range line ' . $line);
            self::assertLessThanOrEqual(180.0, $lon, 'longitude range line ' . $line);
            self::assertGreaterThanOrEqual(0.0, $bearing, 'bearing range line ' . $line);
            self::assertLessThan(360.0, $bearing, 'bearing range line ' . $line);
            self::assertGreaterThanOrEqual(0.0, $distance, 'distance range line ' . $line);

            self::assertStringStartsWith('py:alfalak-spherical-2026-10-10;', $source, 'source tag line ' . $line);
            self::assertStringContainsString('MAKKAH=21.4225241,39.8261818', $source, 'source pin line ' . $line);
            self::assertStringContainsString('R=6371.0088', $source, 'source pin line ' . $line);

            $key = $latRaw . ',' . $lonRaw;

            self::assertArrayNotHasKey($key, $seen, 'duplicate coordinates line ' . $line);

            $seen[$key] = true;
        }
    }

    public function testExcludesDegenerateBearingGoldens(): void
    {
        foreach ([self::GOLDEN_FILE, self::WGS84_GOLDEN_FILE] as $file) {
            foreach (GoldenLoader::load(__DIR__ . '/' . $file)['rows'] as $index => $row) {
                $line = $index + 2;
                $lat = (float) self::field($row, 'latitude', $line, $file);
                $lon = (float) self::field($row, 'longitude', $line, $file);
                $distance = (float) self::field($row, 'distance_km', $line, $file);

                self::assertGreaterThan(1.0, $distance, sprintf('no Makkah-self row in %s line %d.', $file, $line));
                self::assertGreaterThan(
                    0.01,
                    abs($lat - 21.4225241) + abs($lon - 39.8261818),
                    sprintf('no Makkah-self coordinates in %s line %d.', $file, $line),
                );
                self::assertGreaterThan(
                    0.01,
                    abs($lat + 21.4225241) + abs($lon + 140.1738182),
                    sprintf('no antipode coordinates in %s line %d.', $file, $line),
                );
            }
        }
    }

    public function testMagneticHeadingVectors(): void
    {
        $cases = [
            [118.987219, 0.0, 118.987219],
            [118.987219, 5.0, 113.987219],
            [118.987219, -5.0, 123.987219],
            [7.201795, 10.0, 357.201795],
            [350.883076, -15.0, 5.883076],
            [58.481697, 370.0, 48.481697],
        ];

        foreach ($cases as $case) {
            [$true, $declination, $expected] = $case;

            $result = new QiblaResult($true, 1000.0);

            self::assertEqualsWithDelta(
                $expected,
                $result->magneticHeading($declination),
                1e-9,
                sprintf('magneticHeading(true=%f, decl=%f)', $true, $declination),
            );
        }
    }


    /**
     * Numeric parity: PHP spherical implementation vs committed CSV goldens.
     *
     * Gates are fixed (1e-6 bearing / 1e-3 distance) because committed CSV
     * rounding (6dp/3dp) dominates float noise; see docs/qibla.md lock-in.
     */
    public function testEngineParityAgainstPhpSphericalImplementation(): void
    {
        $calculator = QiblaCalculator::default();

        foreach (GoldenLoader::load(__DIR__ . '/' . self::GOLDEN_FILE)['rows'] as $index => $row) {
            $line = $index + 2;
            $result = $calculator->calculate(Coordinates::fromDegrees(
                (float) self::field($row, 'latitude', $line, self::GOLDEN_FILE),
                (float) self::field($row, 'longitude', $line, self::GOLDEN_FILE),
            ));

            self::assertTrue(
                abs($result->direction - (float) self::field($row, 'bearing_deg', $line, self::GOLDEN_FILE)) <= 1e-6,
                'bearing line ' . $line,
            );
            self::assertTrue(
                abs($result->distanceKm - (float) self::field($row, 'distance_km', $line, self::GOLDEN_FILE)) <= 1e-3,
                'distance line ' . $line,
            );
        }
    }

    public function testWgs84GoldenFileHasExpectedHeaderAndRowCount(): void
    {
        $loaded = GoldenLoader::load(__DIR__ . '/' . self::WGS84_GOLDEN_FILE);

        self::assertSame(
            ['city', 'latitude', 'longitude', 'bearing_deg', 'distance_km', 'source'],
            $loaded['header'],
        );
        self::assertCount(self::ROW_COUNT, $loaded['rows']);
    }

    public function testWgs84RowsHaveValidRangesPrecisionAndSourceTags(): void
    {
        $seen = [];

        foreach (GoldenLoader::load(__DIR__ . '/' . self::WGS84_GOLDEN_FILE)['rows'] as $index => $row) {
            $line = $index + 2;
            $latRaw = self::field($row, 'latitude', $line, self::WGS84_GOLDEN_FILE);
            $lonRaw = self::field($row, 'longitude', $line, self::WGS84_GOLDEN_FILE);
            $bearingRaw = self::field($row, 'bearing_deg', $line, self::WGS84_GOLDEN_FILE);
            $distanceRaw = self::field($row, 'distance_km', $line, self::WGS84_GOLDEN_FILE);
            $source = self::field($row, 'source', $line, self::WGS84_GOLDEN_FILE);

            self::assertMatchesRegularExpression('/^-?\d+\.\d{4}$/', $latRaw, 'latitude precision line ' . $line);
            self::assertMatchesRegularExpression('/^-?\d+\.\d{4}$/', $lonRaw, 'longitude precision line ' . $line);
            self::assertMatchesRegularExpression('/^\d+\.\d{6}$/', $bearingRaw, 'bearing precision line ' . $line);
            self::assertMatchesRegularExpression('/^\d+\.\d{3}$/', $distanceRaw, 'distance precision line ' . $line);

            $lat = (float) $latRaw;
            $lon = (float) $lonRaw;
            $bearing = (float) $bearingRaw;
            $distance = (float) $distanceRaw;

            self::assertGreaterThanOrEqual(-90.0, $lat, 'latitude range line ' . $line);
            self::assertLessThanOrEqual(90.0, $lat, 'latitude range line ' . $line);
            self::assertGreaterThanOrEqual(-180.0, $lon, 'longitude range line ' . $line);
            self::assertLessThanOrEqual(180.0, $lon, 'longitude range line ' . $line);
            self::assertGreaterThanOrEqual(0.0, $bearing, 'bearing range line ' . $line);
            self::assertLessThan(360.0, $bearing, 'bearing range line ' . $line);
            self::assertGreaterThanOrEqual(0.0, $distance, 'distance range line ' . $line);

            self::assertStringStartsWith('py:alfalak-a7baa8b;wgs84-karney;', $source, 'source tag line ' . $line);
            self::assertStringContainsString('MAKKAH=21.4225241,39.8261818', $source, 'source pin line ' . $line);
            self::assertStringContainsString('a=6378137.0', $source, 'source pin line ' . $line);
            self::assertStringContainsString('f=1/298.257223563', $source, 'source pin line ' . $line);

            $key = $latRaw . ',' . $lonRaw;

            self::assertArrayNotHasKey($key, $seen, 'duplicate coordinates line ' . $line);

            $seen[$key] = true;
        }
    }

    /**
     * Numeric parity: wired WGS84 strategy vs committed CSV goldens.
     *
     * Routed through QiblaCalculator::wgs84() rather than the raw engine so
     * the strategy's unwind / re-clamp / degenerate clamp is gated too.
     * Gates are the same fixed 1e-6 bearing / 1e-3 distance as the
     * spherical file because committed CSV rounding (6dp/3dp) dominates
     * float noise.
     */
    public function testWgs84EngineParityAgainstWiredImplementation(): void
    {
        $calculator = QiblaCalculator::wgs84();

        foreach (GoldenLoader::load(__DIR__ . '/' . self::WGS84_GOLDEN_FILE)['rows'] as $index => $row) {
            $line = $index + 2;
            $result = $calculator->calculate(Coordinates::fromDegrees(
                (float) self::field($row, 'latitude', $line, self::WGS84_GOLDEN_FILE),
                (float) self::field($row, 'longitude', $line, self::WGS84_GOLDEN_FILE),
            ));

            self::assertTrue(
                abs($result->direction - (float) self::field($row, 'bearing_deg', $line, self::WGS84_GOLDEN_FILE)) <= 1e-6,
                'bearing line ' . $line,
            );
            self::assertTrue(
                abs($result->distanceKm - (float) self::field($row, 'distance_km', $line, self::WGS84_GOLDEN_FILE)) <= 1e-3,
                'distance line ' . $line,
            );
        }
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
