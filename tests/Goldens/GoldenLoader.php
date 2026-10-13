<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Goldens;

use PHPUnit\Framework\Assert;

/**
 * Single golden-CSV loader for every context (one owner, one message set).
 *
 * Qibla, Moon, Hijri, and Prayer goldens share this header + assoc-row shape;
 * per-file row assertions stay in the owning test.
 */
final class GoldenLoader
{
    /**
     * Load a golden CSV as header + assoc rows.
     *
     * @param string $path Full CSV path.
     * @return array{header: list<string>, rows: list<array<string, string>>}
     */
    public static function load(string $path): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            Assert::fail('Cannot open golden file ' . $path);
        }

        $headerRow = fgetcsv($handle, escape: '');

        if (! is_array($headerRow)) {
            fclose($handle);
            Assert::fail('Empty golden file ' . $path);
        }

        $header = [];

        foreach ($headerRow as $cell) {
            if (! is_string($cell)) {
                fclose($handle);
                Assert::fail('Empty header cell in golden file ' . $path);
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
                Assert::fail(sprintf(
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
                    Assert::fail(sprintf('Non-string cell in golden file %s line %d.', $path, $line));
                }

                $data[$key] = $value;
            }

            $rows[] = $data;
        }

        fclose($handle);

        return ['header' => $header, 'rows' => $rows];
    }
}
