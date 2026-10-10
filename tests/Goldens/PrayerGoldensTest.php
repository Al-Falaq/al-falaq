<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Goldens;

use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Enums\Prayer;
use AlFalaq\AlFalaq\Prayer\Services\PrayerTimesCalculator;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;
use PHPUnit\Framework\TestCase;

/**
 * Pinned-Python parity: minute-rounded UTC equality on golden vectors.
 *
 * Skips empty marker fields (unpublished markers in T2 tables). Ramadan rows are exactly
 * the UMM_AL_QURA rows dated 2025-03-02 (exporter sets Ramadan mode
 * only there). Expected file set is explicit: 17 present (11 pin + 4
 * provisional + tehran/morocco self-established by tools/gen_self_prayer_goldens.php).
 */
final class PrayerGoldensTest extends TestCase
{
    private const GOLDEN_DIR = __DIR__;

    /** @return list<string> */
    private static function expectedFiles(): array
    {
        return [
            'prayer_muslim_world_league.csv',
            'prayer_egyptian.csv',
            'prayer_karachi.csv',
            'prayer_umm_al_qura.csv',
            'prayer_dubai.csv',
            'prayer_moon_sighting_committee.csv',
            'prayer_north_america.csv',
            'prayer_kuwait.csv',
            'prayer_qatar.csv',
            'prayer_singapore.csv',
            'prayer_jakim.csv',
            'prayer_turkey.csv',
            'prayer_kemenag.csv',
            'prayer_muis.csv',
            'prayer_brunei.csv',
            'prayer_tehran.csv',
            'prayer_morocco.csv',
        ];
    }

    public function testGoldenFileSetIsComplete(): void
    {
        $found = [];

        $paths = glob(self::GOLDEN_DIR . '/prayer_*.csv');

        if ($paths === false) {
            $paths = [];
        }

        foreach ($paths as $path) {
            $found[] = basename($path);
        }

        sort($found);
        $expected = self::expectedFiles();
        sort($expected);

        self::assertSame($expected, $found);
    }

    public function testAllGoldenRowsMatchPinMinutes(): void
    {
        foreach (self::expectedFiles() as $file) {
            $rows = $this->checkFile(self::GOLDEN_DIR . '/' . $file);

            self::assertGreaterThan(0, $rows, sprintf('Golden file %s contributed 0 rows.', $file));
        }
    }

    /**
     * Check one golden file row by row.
     *
     * @param string $path CSV path.
     * @return int Rows checked.
     */
    private function checkFile(string $path): int
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            self::fail('Cannot open golden file ' . $path);
        }

        $headerRow = fgetcsv($handle, escape: '');

        if (! is_array($headerRow)) {
            fclose($handle);
            self::fail('Empty golden file ' . $path);
        }

        $header = [];

        foreach ($headerRow as $cell) {
            if (! is_string($cell)) {
                fclose($handle);
                self::fail('Empty header cell in golden file ' . $path);
            }

            $header[] = $cell;
        }

        $method = CalculationMethod::from(substr(basename($path, '.csv'), 7));

        $rows = 0;
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
                    $path,
                    $line,
                    count($header),
                    count($row),
                ));
            }

            $data = [];

            foreach ($header as $index => $key) {
                $data[$key] = $row[$index] ?? null;
            }

            $hasExpectation = false;

            foreach (Prayer::cases() as $prayer) {
                if (($data[$prayer->value] ?? '') !== '') {
                    $hasExpectation = true;

                    break;
                }
            }

            if (! $hasExpectation) {
                continue;
            }

            $params = $method->defaultParameters();

            $dateRaw = $data['date'] ?? null;

            if (! is_string($dateRaw) || $dateRaw === '') {
                continue;
            }

            if ($method === CalculationMethod::UMM_AL_QURA && $dateRaw === '2025-03-02') {
                $params = $params->withRamadan(true);
            }

            [$y, $m, $d] = array_map('intval', explode('-', $dateRaw));

            $schedule = PrayerTimesCalculator::default()->calculate(
                Coordinates::fromDegrees((float) $data['latitude'], (float) $data['longitude']),
                CivilDate::fromYmd($y, $m, $d),
                $params,
            );

            foreach (Prayer::cases() as $prayer) {
                $expected = $data[$prayer->value] ?? '';

                if ($expected === '') {
                    continue;
                }

                $expectedDt = new \DateTimeImmutable($expected, new \DateTimeZone('UTC'));

                self::assertTrue(
                    Comparator::minutesEqual($schedule->timeFor($prayer), $expectedDt),
                    sprintf(
                        '%s %s %s: expected %s, got %s',
                        $data['method'],
                        $data['date'],
                        $prayer->value,
                        $expected,
                        $schedule->timeFor($prayer)->format('Y-m-d H:i'),
                    ),
                );
            }

            $rows++;
        }

        fclose($handle);

        return $rows;
    }
}
