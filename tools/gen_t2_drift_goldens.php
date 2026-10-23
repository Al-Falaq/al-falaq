<?php

declare(strict_types=1);

/**
 * Regenerate the drift-gated rows of the three T2 prayer goldens that have
 * no obtainable official table for the remaining grid dates.
 *
 * Turkey, Kemenag and Brunei ship official-table rows for two dates
 * (2025-06-21 and 2025-12-15) taken from the published timetables. The
 * official monthly tables for the remaining grid dates could not be
 * retrieved on 2026-10-22 (Diyanet has no archived monthly tables for
 * those months; the Kemenag `getShalatbln` endpoint answers HTTP 200 with
 * `{"status":"Illegal key"}` and yields no table; the KHEU `mora.gov.bn`
 * host resolves but its HTTPS endpoint fails on an expired certificate),
 * and no obtainable archive exists for them — the same situation recorded
 * for Tehran and Morocco.
 *
 * So the remaining grid dates are drift-gated: our own output, recorded as
 * a regression guard and explicitly NOT a parity claim. The `source` tag
 * says so on every row.
 *
 * MUIS is NOT handled by this tool: its 2025 timetable is published in full
 * on data.gov.sg, so every MUIS row is an official `takwim:` row, hand-pinned
 * rather than generated.
 *
 * Merge semantics: existing `takwim:` rows are preserved verbatim (they
 * are the official claim and this tool never touches them); `drift:` rows
 * are regenerated. Re-running is idempotent and byte-stable.
 *
 * Regenerate and diff-review with: php tools/gen_t2_drift_goldens.php
 */

require __DIR__ . '/../vendor/autoload.php';

use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Enums\Prayer;
use AlFalaq\AlFalaq\Prayer\Services\PrayerTimesCalculator;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\AstronomicalException;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

/** The dates the official tables do not cover for the T2 methods. */
$driftDates = ['2025-01-15', '2025-03-02', '2025-03-20', '2025-09-22'];

/** Each T2 method's own city, as published in its official table. */
$cities = [
    CalculationMethod::TURKEY->value => [41.0082, 28.9784],
    CalculationMethod::KEMENAG->value => [-6.2000, 106.8500],
    CalculationMethod::BRUNEI->value => [4.9031, 114.9398],
];

$markers = array_map(static fn(Prayer $p): string => $p->value, Prayer::cases());

$sourceTag = 'drift:no-official-table-retrievable-2026-10-22;not-parity;drift-gate;'
    . 'diyanet-no-archived-monthly-tables;kemenag-endpoint-rejects-request;kheu-mora-https-unavailable;'
    . 'takwim-rows-preserved';

foreach ($cities as $methodValue => [$lat, $lon]) {
    $method = CalculationMethod::from($methodValue);
    $path = __DIR__ . '/../tests/Goldens/prayer_' . $method->value . '.csv';

    $raw = (string) file_get_contents($path);

    if ($raw === '') {
        throw new RuntimeException('Empty golden file ' . $path);
    }

    // The three files this tool touches are CRLF (the LF drift files
    // tehran/morocco/uoif belong to gen_self_prayer_goldens.php and are
    // never read here). Keep whatever the target file already uses.
    $eol = str_contains($raw, "\r\n") ? "\r\n" : "\n";
    $lines = explode($eol, rtrim($raw, "\r\n"));
    $headerLine = array_shift($lines);

    if ($headerLine === null) {
        throw new RuntimeException('Empty golden file ' . $path);
    }

    // Keep the official rows verbatim, drop any previously generated drift
    // rows. The official rows are kept as RAW LINES, not re-serialised
    // arrays: fputcsv re-quotes a field that contains a space, which would
    // rewrite the official `takwim:` source tags and turn a four-row diff
    // into a whole-file diff.
    $kept = [];

    foreach ($lines as $line) {
        if ($line === '') {
            continue;
        }

        $fields = str_getcsv($line, ',', '"', '');
        $source = $fields === [] ? '' : (string) ($fields[count($fields) - 1] ?? '');

        if (str_starts_with($source, 'drift:')) {
            continue;
        }

        $kept[] = $line;
    }

    $params = $method->defaultParameters();
    $generated = [];

    foreach ($driftDates as $datestr) {
        [$y, $m, $d] = array_map('intval', explode('-', $datestr));
        $row = [$method->value, $datestr, sprintf('%.4f', $lat), sprintf('%.4f', $lon)];

        try {
            $schedule = PrayerTimesCalculator::default()->calculate(
                Coordinates::fromDegrees($lat, $lon),
                CivilDate::fromYmd($y, $m, $d),
                $params,
            );

            foreach ($markers as $marker) {
                $row[] = $schedule->timeFor(Prayer::from($marker))->format('Y-m-d\TH:i:s+00:00');
            }
        } catch (AstronomicalException $e) {
            throw new RuntimeException(sprintf(
                'Unresolvable drift row %s %s: %s',
                $method->value,
                $datestr,
                $e->getMessage(),
            ));
        }

        $row[] = $sourceTag;
        $generated[] = csvLine($row);
    }

    $out = $headerLine . $eol;

    foreach ($kept as $line) {
        $out .= $line . $eol;
    }

    foreach ($generated as $line) {
        $out .= $line . $eol;
    }

    $tmpPath = $path . '.tmp';

    // Binary mode so the CRLF bytes are written verbatim on every platform.
    $handle = fopen($tmpPath, 'wb');

    if ($handle === false) {
        throw new RuntimeException('Cannot open ' . $tmpPath);
    }

    if (fwrite($handle, $out) !== strlen($out) || ! fflush($handle)) {
        fclose($handle);

        throw new RuntimeException('Cannot write ' . $tmpPath);
    }

    fclose($handle);

    // Verify the temp file before publishing it: a partial write must never
    // replace a shipped golden. Same guard as gen_self_prayer_goldens.php.
    $check = fopen($tmpPath, 'rb');

    if ($check === false) {
        throw new RuntimeException('Cannot verify ' . $tmpPath);
    }

    $rows = 0;
    $width = 0;

    while (($cells = fgetcsv($check, 0, ',', '"', '')) !== false) {
        $rows++;

        if ($rows === 1) {
            $width = count($cells);
        }
    }

    fclose($check); // Read-handle close intentionally unchecked: rows already counted, nothing to lose.

    $expectedRows = 1 + count($kept) + count($generated);
    $expectedWidth = 4 + count($markers) + 1;

    if ($rows !== $expectedRows || $width !== $expectedWidth) {
        throw new RuntimeException(sprintf(
            'Refusing to publish truncated %s: got %d rows x %d cols, want %d x %d.',
            $tmpPath,
            $rows,
            $width,
            $expectedRows,
            $expectedWidth,
        ));
    }

    if (! rename($tmpPath, $path)) {
        throw new RuntimeException('Cannot replace ' . $path);
    }

    echo 'wrote ' . $path . PHP_EOL;
}

/**
 * One CSV record as a single line, without a trailing newline.
 *
 * fputcsv() quotes a field that contains a space; the source tag is
 * deliberately written without spaces so the generated rows stay unquoted,
 * matching the hand-maintained takwim rows they sit next to.
 *
 * @param list<string> $fields Record fields.
 * @return string The record as one line.
 */
function csvLine(array $fields): string
{
    $stream = fopen('php://memory', 'r+');

    if ($stream === false) {
        throw new RuntimeException('Cannot open the in-memory stream.');
    }

    if (fputcsv($stream, $fields, ',', '"', '') === false) {
        fclose($stream);

        throw new RuntimeException('Cannot format the CSV record.');
    }

    rewind($stream);
    $line = (string) stream_get_contents($stream);
    fclose($stream);

    return rtrim($line, "\r\n");
}
