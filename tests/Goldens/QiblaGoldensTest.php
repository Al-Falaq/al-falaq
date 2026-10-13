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
 * and the WGS84-ellipsoidal goldens alongside. Row shape and parity checks
 * are shared across both files (only source tags differ); numeric engine
 * parity of bearings/distances against the wired PHP calculators is gated
 * per file.
 */
final class QiblaGoldensTest extends TestCase
{
    private const GOLDEN_FILE = 'qibla_spherical.csv';

    private const WGS84_GOLDEN_FILE = 'qibla_wgs84.csv';

    private const ROW_COUNT = 23;

    public function testGoldenFileHasExpectedHeaderAndRowCount(): void
    {
        $loaded = GoldenLoader::load(__DIR__ . '/' . self::GOLDEN_FILE);

        GoldenAsserts::headerAndRowCount(
            $loaded,
            ['city', 'latitude', 'longitude', 'bearing_deg', 'distance_km', 'source'],
            self::ROW_COUNT,
            self::GOLDEN_FILE,
        );
    }

    public function testRowsHaveValidRangesPrecisionAndSourceTags(): void
    {
        self::checkRowRanges(
            self::GOLDEN_FILE,
            'py:alfalak-spherical-2026-10-10;',
            ['MAKKAH=21.4225241,39.8261818', 'R=6371.0088'],
        );
    }

    public function testExcludesDegenerateBearingGoldens(): void
    {
        foreach ([self::GOLDEN_FILE, self::WGS84_GOLDEN_FILE] as $file) {
            foreach (GoldenLoader::load(__DIR__ . '/' . $file)['rows'] as $index => $row) {
                $line = $index + 2;
                $lat = (float) GoldenAsserts::field($row, 'latitude', $line, $file);
                $lon = (float) GoldenAsserts::field($row, 'longitude', $line, $file);
                $distance = (float) GoldenAsserts::field($row, 'distance_km', $line, $file);

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
        self::checkParity(QiblaCalculator::default(), self::GOLDEN_FILE);
    }

    public function testWgs84GoldenFileHasExpectedHeaderAndRowCount(): void
    {
        $loaded = GoldenLoader::load(__DIR__ . '/' . self::WGS84_GOLDEN_FILE);

        GoldenAsserts::headerAndRowCount(
            $loaded,
            ['city', 'latitude', 'longitude', 'bearing_deg', 'distance_km', 'source'],
            self::ROW_COUNT,
            self::WGS84_GOLDEN_FILE,
        );
    }

    public function testWgs84RowsHaveValidRangesPrecisionAndSourceTags(): void
    {
        self::checkRowRanges(
            self::WGS84_GOLDEN_FILE,
            'py:alfalak-a7baa8b;wgs84-karney;',
            ['MAKKAH=21.4225241,39.8261818', 'a=6378137.0', 'f=1/298.257223563'],
        );
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
        self::checkParity(QiblaCalculator::wgs84(), self::WGS84_GOLDEN_FILE);
    }

    /**
     * Ranges, precision, source tags, and duplicate coordinates for one file.
     *
     * @param string $file CSV basename under this directory.
     * @param non-empty-string $sourcePrefix Required source-tag prefix.
     * @param list<string> $sourceContains Required source-tag fragments.
     * @return void
     */
    private static function checkRowRanges(string $file, string $sourcePrefix, array $sourceContains): void
    {
        $seen = [];

        foreach (GoldenLoader::load(__DIR__ . '/' . $file)['rows'] as $index => $row) {
            $line = $index + 2;
            $latRaw = GoldenAsserts::field($row, 'latitude', $line, $file);
            $lonRaw = GoldenAsserts::field($row, 'longitude', $line, $file);
            $bearingRaw = GoldenAsserts::field($row, 'bearing_deg', $line, $file);
            $distanceRaw = GoldenAsserts::field($row, 'distance_km', $line, $file);
            $source = GoldenAsserts::field($row, 'source', $line, $file);

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

            self::assertStringStartsWith($sourcePrefix, $source, 'source tag line ' . $line);

            foreach ($sourceContains as $fragment) {
                self::assertStringContainsString($fragment, $source, 'source pin line ' . $line);
            }

            $key = $latRaw . ',' . $lonRaw;

            self::assertArrayNotHasKey($key, $seen, 'duplicate coordinates line ' . $line);

            $seen[$key] = true;
        }
    }

    /**
     * Numeric parity of bearings/distances against one wired calculator.
     *
     * Gates are fixed (1e-6 bearing / 1e-3 distance) because committed CSV
     * rounding (6dp/3dp) dominates float noise; see docs/qibla.md lock-in.
     *
     * @param QiblaCalculator $calculator Wired spherical or WGS84 calculator.
     * @param string $file CSV basename under this directory.
     * @return void
     */
    private static function checkParity(QiblaCalculator $calculator, string $file): void
    {
        foreach (GoldenLoader::load(__DIR__ . '/' . $file)['rows'] as $index => $row) {
            $line = $index + 2;
            $result = $calculator->calculate(Coordinates::fromDegrees(
                (float) GoldenAsserts::field($row, 'latitude', $line, $file),
                (float) GoldenAsserts::field($row, 'longitude', $line, $file),
            ));

            self::assertTrue(
                abs($result->direction - (float) GoldenAsserts::field($row, 'bearing_deg', $line, $file)) <= 1e-6,
                'bearing line ' . $line,
            );
            self::assertTrue(
                abs($result->distanceKm - (float) GoldenAsserts::field($row, 'distance_km', $line, $file)) <= 1e-3,
                'distance line ' . $line,
            );
        }
    }

}
