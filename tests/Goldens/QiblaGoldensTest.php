<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Goldens;

use AlFalaq\AlFalaq\Qibla\Services\QiblaCalculator;
use AlFalaq\AlFalaq\Qibla\ValueObjects\QiblaResult;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;
use PHPUnit\Framework\TestCase;

/**
 * Spherical qibla golden structure gate (M2 preservation step).
 *
 * Gates CSV shape (header, 23 rows, ranges, precision, source tags, no
 * degenerate rows) plus magneticHeading vectors. Numeric engine parity of
 * bearings/distances against the PHP spherical implementation lands with
 * the M2 implementation (GeodesyEngine/SphericalCalculator still throw
 * until then).
 */
final class QiblaGoldensTest extends TestCase
{
    private const GOLDEN_FILE = __DIR__ . '/qibla_spherical.csv';

    private const ROW_COUNT = 23;

    public function testGoldenFileHasExpectedHeaderAndRowCount(): void
    {
        $loaded = self::loadGolden();

        self::assertSame(
            ['city', 'latitude', 'longitude', 'bearing_deg', 'distance_km', 'source'],
            $loaded['header'],
        );
        self::assertCount(self::ROW_COUNT, $loaded['rows']);
    }

    public function testRowsHaveValidRangesPrecisionAndSourceTags(): void
    {
        $seen = [];

        foreach (self::loadGolden()['rows'] as $index => $row) {
            $line = $index + 2;
            $latRaw = self::field($row, 'latitude', $line);
            $lonRaw = self::field($row, 'longitude', $line);
            $bearingRaw = self::field($row, 'bearing_deg', $line);
            $distanceRaw = self::field($row, 'distance_km', $line);
            $source = self::field($row, 'source', $line);

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
        foreach (self::loadGolden()['rows'] as $index => $row) {
            $line = $index + 2;
            $lat = (float) self::field($row, 'latitude', $line);
            $lon = (float) self::field($row, 'longitude', $line);
            $distance = (float) self::field($row, 'distance_km', $line);

            self::assertGreaterThan(1.0, $distance, 'no Makkah-self row line ' . $line);
            self::assertGreaterThan(
                0.01,
                abs($lat - 21.4225241) + abs($lon - 39.8261818),
                'no Makkah-self coordinates line ' . $line,
            );
            self::assertGreaterThan(
                0.01,
                abs($lat + 21.4225241) + abs($lon + 140.1738182),
                'no antipode coordinates line ' . $line,
            );
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
     * Load the golden CSV as header + assoc rows.
     *
     * @return array{header: list<string>, rows: list<array<string, string>>}
     */
    private static function loadGolden(): array
    {
        $handle = fopen(self::GOLDEN_FILE, 'r');

        if ($handle === false) {
            self::fail('Cannot open golden file ' . self::GOLDEN_FILE);
        }

        $headerRow = fgetcsv($handle, escape: '');

        if (! is_array($headerRow)) {
            fclose($handle);
            self::fail('Empty golden file ' . self::GOLDEN_FILE);
        }

        $header = [];

        foreach ($headerRow as $cell) {
            if (! is_string($cell)) {
                fclose($handle);
                self::fail('Empty header cell in golden file ' . self::GOLDEN_FILE);
            }

            $header[] = $cell;
        }

        $rows = [];
        $line = 1;

        while (true) {
            $row = fgetcsv($handle, escape: '');

            if (! is_array($row)) {
                break;
            }

            $line++;

            if (count($header) !== count($row)) {
                fclose($handle);
                self::fail(sprintf(
                    'Column-count mismatch in golden file %s line %d: header has %d columns, row has %d.',
                    self::GOLDEN_FILE,
                    $line,
                    count($header),
                    count($row),
                ));
            }

            $data = [];

            foreach ($header as $index => $key) {
                $value = $row[$index] ?? null;

                if (! is_string($value)) {
                    fclose($handle);
                    self::fail(sprintf('Non-string cell in golden file %s line %d.', self::GOLDEN_FILE, $line));
                }

                $data[$key] = $value;
            }

            $rows[] = $data;
        }

        fclose($handle);

        return ['header' => $header, 'rows' => $rows];
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

        foreach (self::loadGolden()['rows'] as $index => $row) {
            $line = $index + 2;
            $result = $calculator->calculate(Coordinates::fromDegrees(
                (float) self::field($row, 'latitude', $line),
                (float) self::field($row, 'longitude', $line),
            ));

            self::assertTrue(
                abs($result->direction - (float) self::field($row, 'bearing_deg', $line)) <= 1e-6,
                'bearing line ' . $line,
            );
            self::assertTrue(
                abs($result->distanceKm - (float) self::field($row, 'distance_km', $line)) <= 1e-3,
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
     * @return string Cell value.
     */
    private static function field(array $row, string $key, int $line): string
    {
        $value = $row[$key] ?? null;

        if (! is_string($value) || $value === '') {
            self::fail(sprintf('Missing %s in golden file %s line %d.', $key, self::GOLDEN_FILE, $line));
        }

        return $value;
    }
}
