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
 * Empty marker fields in rows with at least one expectation must appear in
 * ALLOWED_EMPTY_CELLS with their reason (unpublished markers in T2 tables,
 * documented no-fit residuals, or a pending-issue link); any other empty
 * cell fails the build. The three fully-empty rows are not allow-listed:
 * they are owned separately by testOnlyDocumentedRowsAreEmpty.
 * Ramadan rows are exactly
 * the UMM_AL_QURA rows dated 2025-03-02 (exporter sets Ramadan mode
 * only there). Expected file set is explicit: 18 present (11 pin + 4
 * provisional + tehran/morocco/uoif drift-gated by tools/gen_self_prayer_goldens.php;
 * turkey/kemenag/brunei drift rows by tools/gen_t2_drift_goldens.php:
 * Tehran cross-checked against batoulapps/adhan-js@a2c4bda, Morocco a labelled
 * Habous convention with no upstream preset, UOIF a 12/12 zero-offset preset
 * with no pin cell — never claimed as pin parity, see issue #7).
 */
final class PrayerGoldensTest extends TestCase
{
    private const GOLDEN_DIR = __DIR__;

    /**
     * Allow-listed empty cells, keyed by method|date|latitude|longitude|marker.
     *
     * Every per-cell empty in the prayer goldens appears here with its
     * non-empty reason; checkFile() fails any empty cell not on this list so future
     * gaps cannot land silently, and testAllowedEmptyCellsAreExactlyTheActualEmpties()
     * fails any allow-list entry with no matching empty cell so stale pins
     * cannot linger (issue #29). Coordinates are part of the key so an
     * exemption for one location cannot leak to another row sharing the same
     * method|date|marker. Fully-empty rows stay owned by
     * testOnlyDocumentedRowsAreEmpty. Reasons cite docs/prayer.md
     * (Emptied golden cells) and the linked follow-up issues.
     *
     * @var array<string, string>
     */
    private const ALLOWED_EMPTY_CELLS = [
        // Turkey (Diyanet publishes no ishraq/dhuha; Dec maghrib +1 no-fit).
        'turkey|2025-06-21|41.0082|28.9784|ishraq' => 'unpublished by Diyanet table',
        'turkey|2025-06-21|41.0082|28.9784|dhuha' => 'unpublished by Diyanet table',
        'turkey|2025-12-15|41.0082|28.9784|ishraq' => 'unpublished by Diyanet table',
        'turkey|2025-12-15|41.0082|28.9784|dhuha' => 'unpublished by Diyanet table',
        'turkey|2025-12-15|41.0082|28.9784|maghrib' => 'no-fit +1 seasonal dusk-side (docs/prayer.md)',
        // Brunei (KHEU publishes no ishraq and no citable Doha rule, issue #11).
        'brunei|2025-06-21|4.9031|114.9398|ishraq' => 'unpublished by KHEU table',
        'brunei|2025-06-21|4.9031|114.9398|dhuha' => 'pending issue #11 (KHEU Doha +24 structural, no citable rule)',
        'brunei|2025-12-15|4.9031|114.9398|imsak' => 'no-fit +/-1 model noise (issue #11)',
        'brunei|2025-12-15|4.9031|114.9398|fajr' => 'no-fit +/-1 model noise (issue #11)',
        'brunei|2025-12-15|4.9031|114.9398|ishraq' => 'unpublished by KHEU table',
        'brunei|2025-12-15|4.9031|114.9398|dhuha' => 'pending issue #11 (KHEU Doha +24 structural, no citable rule)',
        'brunei|2025-12-15|4.9031|114.9398|maghrib' => 'no-fit +/-1 model noise (issue #11)',
        // Singapore/MUIS (publishes no imsak/ishraq/dhuha; June fajr -1 rounding).
        'muis|2025-06-21|1.3521|103.8198|imsak' => 'unpublished by MUIS table',
        'muis|2025-06-21|1.3521|103.8198|fajr' => 'no-fit -1 single-cell rounding (docs/prayer.md)',
        'muis|2025-06-21|1.3521|103.8198|ishraq' => 'unpublished by MUIS table',
        'muis|2025-06-21|1.3521|103.8198|dhuha' => 'unpublished by MUIS table',
        'muis|2025-12-15|1.3521|103.8198|imsak' => 'unpublished by MUIS table',
        'muis|2025-12-15|1.3521|103.8198|ishraq' => 'unpublished by MUIS table',
        'muis|2025-12-15|1.3521|103.8198|dhuha' => 'unpublished by MUIS table',
        // MUIS 2025-01-15 (official MUIS 2025 timetable): the published Isyak
        // is one minute later than our output; every other published marker
        // matches exactly.
        'muis|2025-01-15|1.3521|103.8198|imsak' => 'unpublished by MUIS table',
        'muis|2025-01-15|1.3521|103.8198|ishraq' => 'unpublished by MUIS table',
        'muis|2025-01-15|1.3521|103.8198|dhuha' => 'unpublished by MUIS table',
        'muis|2025-01-15|1.3521|103.8198|isha' => 'no-fit -1 single-cell rounding (docs/prayer.md)',
        // MUIS 2025-09-22 (official MUIS 2025 timetable): only Subuh matches
        // exactly; the other published markers are one minute later.
        'muis|2025-09-22|1.3521|103.8198|imsak' => 'unpublished by MUIS table',
        'muis|2025-09-22|1.3521|103.8198|sunrise' => 'no-fit -1 single-cell rounding (docs/prayer.md)',
        'muis|2025-09-22|1.3521|103.8198|syuruk' => 'no-fit -1 single-cell rounding (docs/prayer.md)',
        'muis|2025-09-22|1.3521|103.8198|ishraq' => 'unpublished by MUIS table',
        'muis|2025-09-22|1.3521|103.8198|dhuha' => 'unpublished by MUIS table',
        'muis|2025-09-22|1.3521|103.8198|dhuhr' => 'no-fit -1 single-cell rounding (docs/prayer.md)',
        'muis|2025-09-22|1.3521|103.8198|asr' => 'no-fit -1 single-cell rounding (docs/prayer.md)',
        'muis|2025-09-22|1.3521|103.8198|maghrib' => 'no-fit -1 single-cell rounding (docs/prayer.md)',
        'muis|2025-09-22|1.3521|103.8198|isha' => 'no-fit -1 single-cell rounding (docs/prayer.md)',
        // Indonesia/Kemenag (publishes no ishraq; the rest +/-1-2 mixed-sign wobble).
        'kemenag|2025-06-21|-6.2000|106.8500|sunrise' => 'no-fit +/-1-2 mixed-sign wobble (docs/prayer.md)',
        'kemenag|2025-06-21|-6.2000|106.8500|syuruk' => 'no-fit +/-1-2 mixed-sign wobble (docs/prayer.md)',
        'kemenag|2025-06-21|-6.2000|106.8500|ishraq' => 'unpublished by Kemenag table',
        'kemenag|2025-06-21|-6.2000|106.8500|dhuha' => 'no-fit +/-1-2 mixed-sign wobble (docs/prayer.md)',
        'kemenag|2025-06-21|-6.2000|106.8500|asr' => 'no-fit +/-1-2 mixed-sign wobble (docs/prayer.md)',
        'kemenag|2025-06-21|-6.2000|106.8500|maghrib' => 'no-fit +/-1-2 mixed-sign wobble (docs/prayer.md)',
        'kemenag|2025-06-21|-6.2000|106.8500|isha' => 'no-fit +/-1-2 mixed-sign wobble (docs/prayer.md)',
        'kemenag|2025-12-15|-6.2000|106.8500|imsak' => 'no-fit +/-1-2 mixed-sign wobble (docs/prayer.md)',
        'kemenag|2025-12-15|-6.2000|106.8500|fajr' => 'no-fit +/-1-2 mixed-sign wobble (docs/prayer.md)',
        'kemenag|2025-12-15|-6.2000|106.8500|sunrise' => 'no-fit +/-1-2 mixed-sign wobble (docs/prayer.md)',
        'kemenag|2025-12-15|-6.2000|106.8500|syuruk' => 'no-fit +/-1-2 mixed-sign wobble (docs/prayer.md)',
        'kemenag|2025-12-15|-6.2000|106.8500|ishraq' => 'unpublished by Kemenag table',
        'kemenag|2025-12-15|-6.2000|106.8500|dhuhr' => 'no-fit +/-1-2 mixed-sign wobble (docs/prayer.md)',
        'kemenag|2025-12-15|-6.2000|106.8500|maghrib' => 'no-fit +/-1-2 mixed-sign wobble (docs/prayer.md)',
        'kemenag|2025-12-15|-6.2000|106.8500|isha' => 'no-fit +/-1-2 mixed-sign wobble (docs/prayer.md)',
    ];

    /**
     * Build the allow-list key for one golden cell.
     *
     * Single owner for the method|date|latitude|longitude|marker shape so checkFile() and
     * testAllowedEmptyCellsAreExactlyTheActualEmpties() cannot drift apart.
     * The method segment is the filename-derived CalculationMethod value,
     * matching checkFile()'s $method source; latitude/longitude are the raw
     * CSV strings so an exemption cannot leak to another location sharing
     * the same method|date|marker.
     *
     * @param CalculationMethod $method Method derived from the golden filename.
     * @param string $dateRaw YYYY-MM-DD date from the golden row.
     * @param string $latRaw Latitude string from the golden row.
     * @param string $lonRaw Longitude string from the golden row.
     * @param Prayer $prayer Marker column.
     * @return string Allow-list key.
     */
    private static function emptyCellKey(CalculationMethod $method, string $dateRaw, string $latRaw, string $lonRaw, Prayer $prayer): string
    {
        return sprintf('%s|%s|%s|%s|%s', $method->value, $dateRaw, $latRaw, $lonRaw, $prayer->value);
    }

    /**
     * Build the row identity for duplicate detection.
     *
     * Single owner for the method|date|latitude|longitude shape so uniqueness
     * checks cannot drift from the cell-key prefix. Uses the same
     * filename-derived method and raw CSV strings as emptyCellKey().
     *
     * @param CalculationMethod $method Method derived from the golden filename.
     * @param string $dateRaw Date string from the golden row.
     * @param string $latRaw Latitude string from the golden row.
     * @param string $lonRaw Longitude string from the golden row.
     * @return string Row identity.
     */
    private static function goldenRowKey(CalculationMethod $method, string $dateRaw, string $latRaw, string $lonRaw): string
    {
        return sprintf('%s|%s|%s|%s', $method->value, $dateRaw, $latRaw, $lonRaw);
    }

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
            'prayer_uoif.csv',
        ];
    }

    /**
     * Expected golden header shared by every prayer CSV.
     *
     * @return list<string>
     */
    private static function expectedHeader(): array
    {
        return ['method', 'date', 'latitude', 'longitude', 'imsak', 'fajr', 'sunrise', 'syuruk', 'ishraq', 'dhuha', 'dhuhr', 'asr', 'maghrib', 'isha', 'source'];
    }

    /**
     * Assert a loaded golden header carries the full schema.
     *
     * A removed marker column would otherwise read as '' via ?? '' and pass
     * whenever the affected cells are already allow-listed empties, so the
     * schema is checked before any row logic.
     *
     * @param array<array-key, mixed> $header Header row from the golden file.
     * @param string $file Golden filename for failure messages.
     */
    private static function assertGoldenHeader(array $header, string $file): void
    {
        self::assertSame(
            self::expectedHeader(),
            array_values($header),
            sprintf('Golden file %s header drifted; marker columns must not be added or removed silently.', $file),
        );
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

    /**
     * Every prayer golden carries the full marker schema.
     *
     * Guards the ?? '' row logic: without this, dropping a marker column
     * already covered by allow-listed empties would read as empty and pass.
     */
    public function testGoldenHeadersAreComplete(): void
    {
        foreach (self::expectedFiles() as $file) {
            $loaded = GoldenLoader::load(self::GOLDEN_DIR . '/' . $file);

            self::assertGoldenHeader($loaded['header'], $file);
        }
    }

    /**
     * The four widened T2 methods carry the full six-date grid.
     *
     * Partial regeneration — one file rewritten, the rest stale or truncated
     * after an interrupted run — would otherwise look green, because the row
     * assertions only check the rows that are present. This pins the date set
     * the widening exists for.
     */
    public function testT2MethodsCarryTheSixDateGrid(): void
    {
        $expected = [
            '2025-01-15',
            '2025-03-02',
            '2025-03-20',
            '2025-06-21',
            '2025-09-22',
            '2025-12-15',
        ];
        sort($expected);

        foreach (['muis', 'turkey', 'kemenag', 'brunei'] as $method) {
            $loaded = GoldenLoader::load(self::GOLDEN_DIR . '/prayer_' . $method . '.csv');
            $dates = [];

            foreach ($loaded['rows'] as $row) {
                $dates[] = $row['date'] ?? '';
            }

            sort($dates);

            self::assertSame(
                $expected,
                $dates,
                sprintf('prayer_%s.csv must carry the full six-date grid exactly once.', $method),
            );
        }
    }

    public function testAllGoldenRowsMatchPinMinutes(): void
    {
        foreach (self::expectedFiles() as $file) {
            $rows = $this->checkFile(self::GOLDEN_DIR . '/' . $file);

            self::assertGreaterThan(0, $rows, sprintf('Golden file %s contributed 0 rows.', $file));
        }
    }

    /**
     * No duplicate golden vectors: each method|date|location row is unique per file.
     *
     * A duplicate row with the same allow-listed empties would collapse in the
     * $actual keyed set and pass parity twice, so row uniqueness is enforced
     * here to keep the exact-match comparison sound. Covers all rows,
     * including fully-empty ones owned by testOnlyDocumentedRowsAreEmpty.
     */
    public function testGoldenRowsAreUnique(): void
    {
        foreach (self::expectedFiles() as $file) {
            $path = self::GOLDEN_DIR . '/' . $file;

            $loaded = GoldenLoader::load($path);

            self::assertGoldenHeader($loaded['header'], $file);

            $method = CalculationMethod::from(substr(basename($path, '.csv'), 7));

            $seen = [];

            foreach ($loaded['rows'] as $data) {
                $dateRaw = $data['date'] ?? '';
                $latRaw = $data['latitude'] ?? '';
                $lonRaw = $data['longitude'] ?? '';

                if (! is_string($dateRaw) || ! is_string($latRaw) || ! is_string($lonRaw)) {
                    self::fail(sprintf('Golden row in %s has non-string identity cells.', $file));
                }

                $rowKey = self::goldenRowKey($method, $dateRaw, $latRaw, $lonRaw);

                self::assertArrayNotHasKey(
                    $rowKey,
                    $seen,
                    sprintf('Duplicate golden row %s in %s; duplicate vectors must not land silently.', $rowKey, $file),
                );

                $seen[$rowKey] = true;
            }
        }
    }

    /**
     * Known fully-empty rows, each with its documented reason.
     *
     * Two categories exist:
     *
     * 1. Unreachable geometry — Tehran AngleMaghrib is unreachable during
     *    the Tromsoe polar day, so the row cannot be computed at all.
     * 2. A documented no-fit against the official table — on both Ramadan
     *    dates of 2025 every marker MUIS publishes is a ±1–2 minute
     *    residual against our output (our value is 1 min early on every
     *    marker and 2 min early on maghrib), so no cell can be pinned to
     *    the minute. The rows stay in the file so the disagreement is loud
     *    rather than silently absent.
     *
     * Any other empty row means lost golden coverage and must fail loudly
     * instead of being skipped.
     */
    public function testOnlyDocumentedRowsAreEmpty(): void
    {
        $empty = [];

        foreach (self::expectedFiles() as $file) {
            $handle = fopen(self::GOLDEN_DIR . '/' . $file, 'r');

            if ($handle === false) {
                self::fail('Cannot open golden file ' . $file);
            }

            $header = fgetcsv($handle, escape: '');

            if (! is_array($header)) {
                fclose($handle);
                self::fail('Empty golden file ' . $file);
            }

            self::assertGoldenHeader($header, $file);

            while (is_array($row = fgetcsv($handle, escape: ''))) {
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
                    $empty[] = sprintf(
                        '%s|%s|%s|%s',
                        $data['method'] ?? '?',
                        $data['date'] ?? '?',
                        $data['latitude'] ?? '?',
                        $data['longitude'] ?? '?',
                    );
                }
            }

            fclose($handle);
        }

        self::assertSame([
            'muis|2025-03-02|1.3521|103.8198',
            'muis|2025-03-20|1.3521|103.8198',
            'tehran|2025-06-21|69.6496|18.9553',
        ], $empty);
    }

    /**
     * Allow-list matches actual per-cell empties exactly: no new gaps, no stale pins.
     *
     * checkFile() already fails new gaps during parity; this scan fails stale
     * pins (an allow-list entry with no matching empty cell, e.g. a golden
     * that later fills in) by comparing the full sorted key sets. Fully-empty
     * rows stay owned by testOnlyDocumentedRowsAreEmpty. Rows with expectations
     * but missing date/coordinates fail loudly instead of being skipped so
     * unlisted gaps cannot hide there. Duplicate rows cannot collapse the
     * keyed set silently: testGoldenRowsAreUnique() forbids them per file.
     */
    public function testAllowedEmptyCellsAreExactlyTheActualEmpties(): void
    {
        foreach (self::ALLOWED_EMPTY_CELLS as $key => $reason) {
            self::assertNotSame(
                '',
                trim($reason),
                sprintf('Allow-list entry %s has an empty reason; every emptied cell needs its documented reason (issue #29).', $key),
            );
        }

        $actual = [];

        foreach (self::expectedFiles() as $file) {
            $path = self::GOLDEN_DIR . '/' . $file;

            $loaded = GoldenLoader::load($path);

            self::assertGoldenHeader($loaded['header'], $file);

            $method = CalculationMethod::from(substr(basename($path, '.csv'), 7));

            foreach ($loaded['rows'] as $data) {
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

                $dateRaw = $data['date'] ?? null;
                $latRaw = $data['latitude'] ?? null;
                $lonRaw = $data['longitude'] ?? null;

                if (! is_string($dateRaw) || $dateRaw === '' || ! is_string($latRaw) || $latRaw === '' || ! is_string($lonRaw) || $lonRaw === '') {
                    self::fail(sprintf('Golden row in %s has expectations but missing date/coordinates; refusing to skip allow-list check.', $file));
                }

                foreach (Prayer::cases() as $prayer) {
                    if (($data[$prayer->value] ?? '') === '') {
                        $actual[self::emptyCellKey($method, $dateRaw, $latRaw, $lonRaw, $prayer)] = true;
                    }
                }
            }
        }

        $actualKeys = array_keys($actual);
        sort($actualKeys);
        $expectedKeys = array_keys(self::ALLOWED_EMPTY_CELLS);
        sort($expectedKeys);

        self::assertSame(
            $expectedKeys,
            $actualKeys,
            'Prayer golden empty cells drifted from ALLOWED_EMPTY_CELLS: missing entries are new gaps, extra entries are stale pins (issue #29).',
        );
    }

    /**
     * Check one golden file row by row.
     *
     * @param string $path CSV path.
     * @return int Rows checked.
     */
    private function checkFile(string $path): int
    {
        $loaded = GoldenLoader::load($path);

        self::assertGoldenHeader($loaded['header'], basename($path));

        $method = CalculationMethod::from(substr(basename($path, '.csv'), 7));

        $rows = 0;

        foreach ($loaded['rows'] as $data) {

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
            $latRaw = $data['latitude'] ?? null;
            $lonRaw = $data['longitude'] ?? null;

            if (! is_string($dateRaw) || $dateRaw === '' || ! is_string($latRaw) || $latRaw === '' || ! is_string($lonRaw) || $lonRaw === '') {
                self::fail(sprintf('Golden row in %s has expectations but missing date/coordinates; refusing to skip allow-list check.', basename($path)));
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
                    $cellKey = self::emptyCellKey($method, $dateRaw, $latRaw, $lonRaw, $prayer);

                    self::assertArrayHasKey(
                        $cellKey,
                        self::ALLOWED_EMPTY_CELLS,
                        sprintf(
                            'Golden cell %s %s %s %s %s is empty but not allow-listed in PrayerGoldensTest::ALLOWED_EMPTY_CELLS (issue #29).',
                            $method->value,
                            $dateRaw,
                            $latRaw,
                            $lonRaw,
                            $prayer->value,
                        ),
                    );

                    self::assertNotSame(
                        '',
                        trim(self::ALLOWED_EMPTY_CELLS[$cellKey]),
                        sprintf('Allow-list entry %s has an empty reason; every emptied cell needs its documented reason (issue #29).', $cellKey),
                    );

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

        return $rows;
    }
}
