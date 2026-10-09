<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Tests;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Layering + shape rules (plain reflection, no plugins).
 *
 * Mirrors Technical-PRD.md §7: final value objects / services,
 * no Util/ namespace regrowth, exceptions extend the base type.
 */
#[Group('arch')]
final class ArchTest extends TestCase
{
    /** @return list<class-string> */
    private static function srcClasses(): array
    {
        static $classes = null;

        if ($classes !== null) {
            return $classes;
        }

        $root = dirname(__DIR__);
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root . '/src', \FilesystemIterator::SKIP_DOTS),
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                require_once $file->getPathname();
            }
        }

        $classes = array_values(array_filter(
            get_declared_classes(),
            static fn(string $class): bool => str_starts_with($class, 'AlFalaq\\AlFalak\\'),
        ));

        return $classes;
    }

    public function testValueObjectsAreFinal(): void
    {
        $checked = [];

        foreach (self::srcClasses() as $class) {
            if (str_contains($class, '\\SharedKernel\\ValueObjects\\')) {
                $checked[] = $class;
                self::assertTrue(
                    (new \ReflectionClass($class))->isFinal(),
                    sprintf('Value object %s must be final.', $class),
                );
            }
        }

        self::assertNotEmpty($checked, 'Expected SharedKernel value objects to exist.');
    }

    public function testServicesAreFinal(): void
    {
        $found = false;

        foreach (self::srcClasses() as $class) {
            if (str_contains($class, '\\Prayer\\Services\\')) {
                $found = true;
                self::assertTrue(
                    (new \ReflectionClass($class))->isFinal(),
                    sprintf('Service %s must be final.', $class),
                );
            }
        }

        self::assertTrue($found, 'Expected Prayer services to exist.');
    }

    public function testNoUtilNamespaceRegrowth(): void
    {
        $root = dirname(__DIR__);
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root . '/src', \FilesystemIterator::SKIP_DOTS),
        );
        $checked = 0;

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $checked++;
            $contents = (string) file_get_contents($file->getPathname());
            self::assertDoesNotMatchRegularExpression(
                '/\bUtil\b/',
                $contents,
                sprintf('Framework-style Util/ reference in %s.', $file->getPathname()),
            );
        }

        self::assertGreaterThan(0, $checked, 'Expected src PHP files to scan.');
    }

    public function testExceptionsExtendBase(): void
    {
        $base = 'AlFalaq\\AlFalak\\SharedKernel\\Exceptions\\AlFalakException';
        $found = false;

        foreach (self::srcClasses() as $class) {
            if (str_contains($class, '\\SharedKernel\\Exceptions\\') && $class !== $base) {
                $found = true;
                self::assertTrue(
                    is_subclass_of($class, $base),
                    sprintf('Exception %s must extend %s.', $class, $base),
                );
            }
        }

        self::assertTrue($found, 'Expected SharedKernel exceptions to exist.');
    }

    /** @return list<string> */
    private static function srcPhpFiles(): array
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

    public function testNoErrorSuffixedFiles(): void
    {
        $violations = [];

        foreach (self::srcPhpFiles() as $path) {
            if (str_ends_with($path, 'Error.php')) {
                $violations[] = $path;
            }
        }

        self::assertSame([], $violations, 'Files must use *Exception suffix, never *Error.');
    }

    public function testDateTimeImmutableOnly(): void
    {
        $violations = [];

        foreach (self::srcPhpFiles() as $path) {
            $contents = (string) file_get_contents($path);

            if (str_contains($contents, 'DateTimeInterface')) {
                $violations[] = $path . ': DateTimeInterface union forbidden, use DateTimeImmutable';
            }

            if (preg_match('/new\s+\\\\?DateTime\s*\(/', $contents) === 1) {
                $violations[] = $path . ': mutable DateTime forbidden, use DateTimeImmutable';
            }
        }

        self::assertSame([], $violations);
    }

    public function testOfflineOnly(): void
    {
        $patterns = [
            '/\bcurl_/' => 'cURL',
            '/\bexec\s*\(/' => 'exec()',
            '/\bshell_exec\s*\(/' => 'shell_exec()',
            '/https?:\/\//' => 'http(s) URL',
        ];
        $violations = [];

        foreach (self::srcPhpFiles() as $path) {
            $contents = (string) file_get_contents($path);

            foreach ($patterns as $pattern => $label) {
                if (preg_match($pattern, $contents) === 1) {
                    $violations[] = $path . ': offline invariant violated (' . $label . ')';
                }
            }
        }

        self::assertSame([], $violations);
    }
}
