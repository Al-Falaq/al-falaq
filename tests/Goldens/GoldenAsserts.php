<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Goldens;

use PHPUnit\Framework\Assert;

/**
 * Single golden-assertion module for every suite (one owner, one dialect).
 *
 * GoldenLoader owns loading; this owns the shared assertions (header,
 * row-count, required cells) so tolerance and message changes land once.
 * Suite-specific numeric checks stay in the owning test.
 */
final class GoldenAsserts
{
    /**
     * Assert the CSV header and row count.
     *
     * @param array{header: list<string>, rows: list<array<string, string>>} $loaded Loader output.
     * @param list<string> $expectedHeader Exact header cells in order.
     * @param int $rows Expected row count.
     * @param string $file CSV basename for failure messages.
     * @return void
     */
    public static function headerAndRowCount(array $loaded, array $expectedHeader, int $rows, string $file): void
    {
        Assert::assertSame($expectedHeader, $loaded['header'], 'header in ' . $file);
        Assert::assertCount($rows, $loaded['rows'], 'row count in ' . $file);
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
    public static function field(array $row, string $key, int $line, string $file): string
    {
        $value = $row[$key] ?? null;

        if (! is_string($value) || $value === '') {
            Assert::fail(sprintf('Missing %s in golden file %s line %d.', $key, $file, $line));
        }

        return $value;
    }
}
