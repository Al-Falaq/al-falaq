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

        $names = array_merge(
            get_declared_classes(),
            get_declared_interfaces(),
            get_declared_traits(),
        );
        $classes = array_values(array_filter(
            $names,
            static fn(string $class): bool => str_starts_with($class, 'AlFalaq\\AlFalak\\')
                && ! str_starts_with($class, 'AlFalaq\\AlFalak\\Tests\\'),
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

    /**
     * Extract the one-line summary from a docblock (first non-empty line).
     *
     * @return string empty when the docblock is missing or blank.
     */
    private static function docSummary(string|false $doc): string
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
     * Every class-like needs a docblock summary: DX starts at the FQCN.
     */
    public function testClassesHaveDocblocks(): void
    {
        $violations = [];

        foreach (self::srcClasses() as $class) {
            if (self::docSummary((new \ReflectionClass($class))->getDocComment()) === '') {
                $violations[] = $class . ': missing class-level docblock summary';
            }
        }

        self::assertSame([], $violations);
    }

    /**
     * Every public/protected method needs a docblock: no undocumented API.
     */
    public function testPublicMethodsHaveDocblocks(): void
    {
        $violations = [];

        foreach (self::srcClasses() as $class) {
            foreach ((new \ReflectionClass($class))->getMethods() as $method) {
                if ($method->isInternal()
                    || (! $method->isPublic() && ! $method->isProtected())
                    || $method->getDeclaringClass()->getName() !== $class
                ) {
                    continue;
                }

                if (self::docSummary($method->getDocComment()) === '') {
                    $violations[] = $class . '::' . $method->getName() . ': missing method docblock';
                }
            }
        }

        self::assertSame([], $violations);
    }

    /**
     * Every method parameter must have a matching @param $name tag.
     *
     * @return list<string> documented parameter names.
     */
    private static function docParams(string|false $doc): array
    {
        if ($doc === false) {
            return [];
        }

        preg_match_all('/@param\s+[^\$]*\$(\w+)/', $doc, $matches);

        return $matches[1];
    }

    public function testDocblocksDocumentParams(): void
    {
        $violations = [];

        foreach (self::srcClasses() as $class) {
            foreach ((new \ReflectionClass($class))->getMethods() as $method) {
                if ($method->isInternal()
                    || (! $method->isPublic() && ! $method->isProtected())
                    || $method->getDeclaringClass()->getName() !== $class
                ) {
                    continue;
                }

                $documented = self::docParams($method->getDocComment());

                foreach ($method->getParameters() as $param) {
                    if (! in_array($param->getName(), $documented, true)) {
                        $violations[] = $class . '::' . $method->getName()
                            . ': missing @param $' . $param->getName();
                    }
                }
            }
        }

        self::assertSame([], $violations);
    }

    /**
     * Every declared non-void return type must have an @return tag.
     */
    public function testDocblocksDocumentReturn(): void
    {
        $violations = [];

        foreach (self::srcClasses() as $class) {
            foreach ((new \ReflectionClass($class))->getMethods() as $method) {
                if ($method->isInternal()
                    || (! $method->isPublic() && ! $method->isProtected())
                    || $method->getDeclaringClass()->getName() !== $class
                    || ! $method->hasReturnType()
                ) {
                    continue;
                }

                $type = $method->getReturnType();

                if ($type instanceof \ReflectionNamedType
                    && in_array($type->getName(), ['void', 'never'], true)
                ) {
                    continue;
                }

                if (preg_match('/@return\s+\S+/', (string) $method->getDocComment()) !== 1) {
                    $violations[] = $class . '::' . $method->getName() . ': missing @return tag';
                }
            }
        }

        self::assertSame([], $violations);
    }
}
