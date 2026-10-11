<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Goldens;

use AlFalaq\AlFalaq\Hijri\Data\MabimsMonthStarts;
use AlFalaq\AlFalaq\Hijri\Data\UmmAlQuraMonthStarts;
use AlFalaq\AlFalaq\Hijri\Enums\MabimsCountry;
use AlFalaq\AlFalaq\Hijri\Strategies\MabimsCalendar;
use AlFalaq\AlFalaq\Hijri\Strategies\TabularCalendar;
use AlFalaq\AlFalaq\Hijri\Strategies\UmmAlQuraCalendar;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use PHPUnit\Framework\TestCase;

/**
 * Exact pin-parity goldens for the M4 Hijri calendars.
 *
 * Sampling is compact (see the M4 plan's "Plan-time corrections" 3): tabular is
 * every 97th Gregorian day across 1900–2100, and each observational calendar
 * contributes day 1 of every supported month. Interior days, month lengths and
 * round-trips are covered by in-PHP invariants in the per-calendar test files,
 * which need no pin rows.
 */
final class HijriGoldensTest extends TestCase
{
    /** Row counts, fixed by the generated tables. */
    private const TABULAR_ROWS = 757;
    private const UQU_ROWS = 936;
    private const MABIMS_ROWS = 2688;

    /** Source tag every generated row carries. */
    private const SOURCE_PREFIX = 'pin:';

    /**
     * Parse one golden Gregorian day.
     *
     * @param string $iso Golden day, YYYY-MM-DD.
     * @return CivilDate Parsed day.
     */
    private static function civilDate(string $iso): CivilDate
    {
        return CivilDate::fromYmd(
            (int) substr($iso, 0, 4),
            (int) substr($iso, 5, 2),
            (int) substr($iso, 8, 2),
        );
    }

    /**
     * Load a golden CSV as header + assoc rows.
     *
     * @param string $file CSV basename under this directory.
     * @return array{header: list<string>, rows: list<array<string, string>>}
     */
    private static function loadGolden(string $file): array
    {
        $path = __DIR__ . '/' . $file;
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
                    $path,
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
                    self::fail(sprintf('Non-string cell in golden file %s line %d.', $path, $line));
                }

                $data[$key] = $value;
            }

            $rows[] = $data;
        }

        fclose($handle);

        return ['header' => $header, 'rows' => $rows];
    }

    /**
     * Read a required golden field.
     *
     * @param array<string, string> $row Parsed golden row.
     * @param string $key Column name.
     * @param int $line 1-based line number for diagnostics.
     * @param string $file Golden basename for diagnostics.
     * @return string Cell value.
     */
    private static function field(array $row, string $key, int $line, string $file): string
    {
        if (! isset($row[$key])) {
            self::fail(sprintf('Missing column %s in %s line %d.', $key, $file, $line));
        }

        return $row[$key];
    }

    /**
     * Tabular parity over the sampled 1900–2100 window.
     *
     * @return void
     */
    public function testTabularParity(): void
    {
        $calendar = new TabularCalendar();
        $golden = self::loadGolden('hijri_tabular.csv');

        self::assertSame(['gregorian', 'hijri', 'source'], $golden['header']);
        self::assertCount(self::TABULAR_ROWS, $golden['rows']);

        foreach ($golden['rows'] as $index => $row) {
            $line = $index + 2;
            $gregorian = self::field($row, 'gregorian', $line, 'hijri_tabular.csv');

            self::assertSame(
                self::field($row, 'hijri', $line, 'hijri_tabular.csv'),
                $calendar->fromGregorian(self::civilDate($gregorian))->isoformat(),
                sprintf('tabular %s (line %d)', $gregorian, $line),
            );
            self::assertStringStartsWith(self::SOURCE_PREFIX, self::field($row, 'source', $line, 'hijri_tabular.csv'));
        }
    }

    /**
     * Umm al-Qura parity for every supported month boundary.
     *
     * @return void
     */
    public function testUquParity(): void
    {
        $calendar = new UmmAlQuraCalendar();
        $golden = self::loadGolden('hijri_uqu.csv');

        self::assertSame(['gregorian', 'hijri', 'source'], $golden['header']);
        self::assertCount(self::UQU_ROWS, $golden['rows']);

        foreach ($golden['rows'] as $index => $row) {
            $line = $index + 2;
            $gregorian = self::field($row, 'gregorian', $line, 'hijri_uqu.csv');

            self::assertSame(
                self::field($row, 'hijri', $line, 'hijri_uqu.csv'),
                $calendar->fromGregorian(self::civilDate($gregorian))->isoformat(),
                sprintf('uqu %s (line %d)', $gregorian, $line),
            );
        }
    }

    /**
     * MABIMS parity for every supported month boundary, per country.
     *
     * @return void
     */
    public function testMabimsParity(): void
    {
        $golden = self::loadGolden('hijri_mabims.csv');

        self::assertSame(['country', 'gregorian', 'hijri', 'source'], $golden['header']);
        self::assertCount(self::MABIMS_ROWS, $golden['rows']);

        foreach ($golden['rows'] as $index => $row) {
            $line = $index + 2;
            $country = MabimsCountry::fromString(self::field($row, 'country', $line, 'hijri_mabims.csv'));
            $gregorian = self::field($row, 'gregorian', $line, 'hijri_mabims.csv');

            self::assertSame(
                self::field($row, 'hijri', $line, 'hijri_mabims.csv'),
                (new MabimsCalendar($country))->fromGregorian(self::civilDate($gregorian))->isoformat(),
                sprintf('mabims/%s %s (line %d)', $country->value, $gregorian, $line),
            );
        }
    }

    /**
     * Both observational tables are strictly increasing and 29/30 days apart.
     *
     * @return void
     */
    public function testObservationalTablesAreContiguous(): void
    {
        $tables = ['uqu' => UmmAlQuraMonthStarts::STARTS];

        foreach (MabimsCountry::cases() as $country) {
            $tables['mabims/' . $country->value] = MabimsMonthStarts::forCountry($country);
        }

        foreach ($tables as $label => $starts) {
            $previous = self::civilDate($starts[0])->toUtcMidnight();

            for ($index = 1; $index < count($starts); $index++) {
                $current = self::civilDate($starts[$index])->toUtcMidnight();
                $gap = (int) $previous->diff($current)->days;

                self::assertContains($gap, [29, 30], sprintf('%s index %d', $label, $index));
                $previous = $current;
            }
        }
    }

    /**
     * Table digests match the canonical recomputation over the raw strings.
     *
     * @return void
     */
    public function testTableDigestsMatch(): void
    {
        self::assertSame(
            UmmAlQuraMonthStarts::SHA256,
            hash('sha256', implode("\n", UmmAlQuraMonthStarts::STARTS)),
        );

        foreach (MabimsCountry::cases() as $country) {
            self::assertSame(
                MabimsMonthStarts::digestFor($country),
                hash('sha256', implode("\n", MabimsMonthStarts::forCountry($country))),
                $country->value,
            );
        }
    }

    /**
     * The golden CSVs and the shipped tables cover the same months, so no row
     * count can drift without this test noticing.
     *
     * @return void
     */
    public function testGoldenRowCountsMatchTheShippedTables(): void
    {
        self::assertSame(
            self::UQU_ROWS,
            (UmmAlQuraMonthStarts::LAST_YEAR - UmmAlQuraMonthStarts::FIRST_YEAR + 1) * 12,
        );
        self::assertCount(self::UQU_ROWS + 1, UmmAlQuraMonthStarts::STARTS);
        self::assertCount(intdiv(self::MABIMS_ROWS, 4) + 1, MabimsMonthStarts::forCountry(MabimsCountry::MY));

        foreach (MabimsCountry::cases() as $country) {
            self::assertCount(
                (MabimsMonthStarts::LAST_YEAR - MabimsMonthStarts::FIRST_YEAR + 1) * 12 + 1,
                MabimsMonthStarts::forCountry($country),
                $country->value,
            );
        }
    }
}
