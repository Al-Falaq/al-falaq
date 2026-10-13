<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Shared file-walk, tokenizer, and docblock helpers for the arch groups.
 */
abstract class ArchSupport extends TestCase
{
    protected static function srcClasses(): array
    {
        static $classes = null;

        if ($classes !== null) {
            return $classes;
        }

        // PSR-4 file path → FQCN, resolved through the composer autoloader.
        // No manual require_once: that depends on filesystem iteration order
        // for parent-before-child, while the autoloader resolves parents on demand.
        $root = dirname(__DIR__);
        $prefix = 'AlFalaq\\AlFalaq\\';
        $classes = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root . '/src', \FilesystemIterator::SKIP_DOTS),
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relative = substr($file->getPathname(), strlen($root . '/src/'), -4);
            $fqcn = $prefix . str_replace('/', '\\', $relative);

            if (str_starts_with($fqcn, $prefix . 'Tests\\')) {
                continue;
            }

            $isClassLike = class_exists($fqcn)
                || interface_exists($fqcn)
                || trait_exists($fqcn)
                || enum_exists($fqcn);

            self::assertTrue(
                $isClassLike,
                sprintf('Source file %s must declare %s.', $file->getPathname(), $fqcn),
            );

            $classes[] = $fqcn;
        }

        $classes = array_values(array_unique($classes));

        return $classes;
    }

    protected static function srcPhpFiles(): array
    {
        $root = dirname(__DIR__);
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root . '/src', \FilesystemIterator::SKIP_DOTS),
        );
        $files = [];

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /**
     * Code with comments and string literals stripped (code tokens only).
     *
     * Layering scans match real code tokens: doc prose and string-literal
     * contents (e.g. an FQCN inside an example string) are invisible.
     *
     * @param string $path PHP file to read.
     * @return string Code with comment and string-literal tokens replaced by newlines.
     */
    protected static function codeWithoutCommentsAndStrings(string $path): string
    {
        $code = (string) file_get_contents($path);
        $out = '';

        foreach (token_get_all($code) as $token) {
            if (is_array($token)) {
                if ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
                    $out .= str_repeat("\n", substr_count($token[1], "\n"));

                    continue;
                }

                if ($token[0] === T_CONSTANT_ENCAPSED_STRING
                    || $token[0] === T_ENCAPSED_AND_WHITESPACE
                ) {
                    $out .= str_repeat("\n", substr_count($token[1], "\n"));

                    continue;
                }

                $out .= $token[1];
            } else {
                $out .= $token;
            }
        }

        return $out;
    }

    /**
     * Code with comments stripped (strings kept).
     *
     * Scans must ignore doc prose (e.g. a doc link or a "not DateTimeInterface"
     * sentence) but still catch runtime strings such as 'https://…'.
     *
     * @param string $path PHP file to read.
     * @return string Code with comment tokens replaced by newlines.
     */
    protected static function codeWithoutComments(string $path): string
    {
        $code = (string) file_get_contents($path);
        $out = '';

        foreach (token_get_all($code) as $token) {
            if (is_array($token)) {
                if ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
                    $out .= str_repeat("\n", substr_count($token[1], "\n"));

                    continue;
                }

                $out .= $token[1];
            } else {
                $out .= $token;
            }
        }

        return $out;
    }

    /**
     * Extract the one-line summary from a docblock (first non-empty line).
     *
     * @return string empty when the docblock is missing or blank.
     */
    protected static function docSummary(string|false $doc): string
    {
        if ($doc === false) {
            return '';
        }

        $body = trim(preg_replace(['/^\/\*\*/', '/\*\/$/'], '', $doc));

        foreach (explode("\n", $body) as $line) {
            $cleaned = trim(preg_replace('/^\s*\*\s?/', '', $line));

            if ($cleaned !== '' && ! str_starts_with($cleaned, '@')) {
                return $cleaned;
            }
        }

        return '';
    }

    /**
     * Every method parameter must have a matching @param $name tag.
     *
     * The name is the first $identifier at bracket depth zero, so named
     * parameters inside callable/array shapes (e.g. `callable(int $value)`)
     * are skipped in favour of the documented parameter itself.
     *
     * @return list<string> documented parameter names.
     */
    protected static function docParams(string|false $doc): array
    {
        if ($doc === false) {
            return [];
        }

        $names = [];

        foreach (explode("\n", $doc) as $line) {
            $at = strpos($line, '@param');

            if ($at === false) {
                continue;
            }

            $name = self::docParamName(substr($line, $at + 6));

            if ($name !== null) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * First $identifier at bracket depth zero on one @param line.
     *
     * @param string $tail Line remainder after `@param`.
     * @return string|null Parameter name, null when the line names none.
     */
    protected static function docParamName(string $tail): ?string
    {
        $depth = 0;
        $length = strlen($tail);

        for ($i = 0; $i < $length; $i++) {
            $char = $tail[$i];

            if ($char === '(' || $char === '[' || $char === '{') {
                $depth++;
            } elseif ($char === ')' || $char === ']' || $char === '}') {
                $depth = max(0, $depth - 1);
            } elseif ($char === '$' && $depth === 0) {
                if (preg_match('/\$(\w+)/', substr($tail, $i), $matches) === 1) {
                    return $matches[1];
                }
            }
        }

        return null;
    }
}
