<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Layering + shape rules (plain reflection, no plugins).
 *
 * Final value objects / services,
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

            if (class_exists($fqcn) || interface_exists($fqcn) || trait_exists($fqcn) || enum_exists($fqcn)) {
                $classes[] = $fqcn;
            }
        }

        $classes = array_values(array_unique($classes));

        return $classes;
    }

    public function testValueObjectsAreFinal(): void
    {
        $checked = [];

        foreach (self::srcClasses() as $class) {
            if (str_contains($class, '\\ValueObjects\\')) {
                $checked[] = $class;
                self::assertTrue(
                    (new \ReflectionClass($class))->isFinal(),
                    sprintf('Value object %s must be final.', $class),
                );
            }
        }

        self::assertNotEmpty($checked, 'Expected value objects to exist.');
    }

    public function testServicesAreFinal(): void
    {
        $checked = [];

        foreach (self::srcClasses() as $class) {
            if (str_contains($class, '\\Services\\')) {
                $checked[] = $class;
                self::assertTrue(
                    (new \ReflectionClass($class))->isFinal(),
                    sprintf('Service %s must be final.', $class),
                );
            }
        }

        self::assertNotEmpty($checked, 'Expected services to exist.');
    }

    public function testNoUtilNamespaceRegrowth(): void
    {
        $checked = 0;

        foreach (self::srcPhpFiles() as $path) {
            $checked++;
            $code = self::codeWithoutComments($path);
            self::assertDoesNotMatchRegularExpression(
                '/\bUtils?\b/i',
                $code,
                sprintf('Framework-style Util/ reference in %s.', $path),
            );
        }

        self::assertGreaterThan(0, $checked, 'Expected src PHP files to scan.');
    }

    public function testExceptionsExtendBase(): void
    {
        $base = 'AlFalaq\\AlFalaq\\SharedKernel\\Exceptions\\AlFalaqException';
        $checked = [];

        foreach (self::srcClasses() as $class) {
            if (str_contains($class, '\\Exceptions\\') && $class !== $base) {
                $checked[] = $class;
                self::assertTrue(
                    is_subclass_of($class, $base),
                    sprintf('Exception %s must extend %s.', $class, $base),
                );
            }
        }

        self::assertNotEmpty($checked, 'Expected exceptions to exist.');
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

    /**
     * Code with comments and string literals stripped (code tokens only).
     *
     * Layering scans match real code tokens: doc prose and string-literal
     * contents (e.g. an FQCN inside an example string) are invisible.
     *
     * @param string $path PHP file to read.
     * @return string Code with comment and string-literal tokens replaced by newlines.
     */
    private static function codeWithoutCommentsAndStrings(string $path): string
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
    private static function codeWithoutComments(string $path): string
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

    public function testNoErrorSuffixedFiles(): void
    {
        $violations = [];

        foreach (self::srcPhpFiles() as $path) {
            if (str_ends_with($path, 'Error.php')) {
                $violations[] = $path;
            }
        }

        foreach (self::srcClasses() as $class) {
            $short = (new \ReflectionClass($class))->getShortName();

            if (str_ends_with($short, 'Error')) {
                $violations[] = $class . ': class name must use *Exception suffix, never *Error.';
            }
        }

        self::assertSame([], $violations, 'Files must use *Exception suffix, never *Error.');
    }

    public function testDateTimeImmutableOnly(): void
    {
        $violations = [];

        foreach (self::srcPhpFiles() as $path) {
            $code = self::codeWithoutComments($path);

            if (str_contains($code, 'DateTimeInterface')) {
                $violations[] = $path . ': DateTimeInterface union forbidden, use DateTimeImmutable';
            }

            if (preg_match('/new\s+\\\\?DateTime\s*\(/', $code) === 1) {
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
            $code = self::codeWithoutComments($path);

            foreach ($patterns as $pattern => $label) {
                if (preg_match($pattern, $code) === 1) {
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

    /**
     * Astro engine depends only inward on Domain value objects, never on contexts.
     */
    public function testAstroDependsOnlyOnDomainValueObjects(): void
    {
        $root = dirname(__DIR__);
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root . '/src/Astro', \FilesystemIterator::SKIP_DOTS),
        );
        $violations = [];

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $code = self::codeWithoutCommentsAndStrings($file->getPathname());

            foreach (['Prayer', 'Qibla', 'Moon', 'Hijri'] as $context) {
                if (preg_match('/AlFalaq\\\\AlFalaq\\\\' . $context . '(?=\\\\|;)/', $code) === 1) {
                    $violations[] = $file->getPathname() . ': Astro must not reference ' . $context;
                }
            }
        }

        // Regression (#20): bare-use evasion + string-literal false positive.
        $context = 'Astro'; // probe the loop-built pattern with a bare-use context.
        $probeBare = 'use AlFalaq\\AlFalaq\\Astro;';
        self::assertSame(1, preg_match('/AlFalaq\\\\AlFalaq\\\\' . $context . '(?=\\\\|;)/', $probeBare));
        $probeStringBlanked = "\n";
        self::assertSame(0, preg_match('/AlFalaq\\\\AlFalaq\\\\' . $context . '(?=\\\\|;)/', $probeStringBlanked));

        self::assertSame([], $violations);
    }

    /**
     * Domain purity + evaluator purity: Contracts/ValueObjects/Enums never
     * touch the Astro engine, and Moon Strategies stay engine-free (pure
     * criterion polynomials); only Services and non-Moon Strategies may.
     */
    public function testEngineImportsStayOutOfDomainAndMoonStrategies(): void
    {
        $root = dirname(__DIR__);
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root . '/src', \FilesystemIterator::SKIP_DOTS),
        );
        $violations = [];

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relative = substr($file->getPathname(), strlen($root . '/src/'));
            // Astro's own Contracts/ValueObjects carry the Astro\ namespace by definition; the reverse direction is covered by testAstroDependsOnlyOnDomainValueObjects.
            if (str_starts_with($relative, 'Astro/')) {
                continue;
            }

            $isDomain = (bool) preg_match('#/(Contracts|ValueObjects|Enums)/#', $relative);
            $isMoonStrategy = str_starts_with($relative, 'Moon/Strategies/');

            if (! $isDomain && ! $isMoonStrategy) {
                continue;
            }

            $code = self::codeWithoutCommentsAndStrings($file->getPathname());

            if (preg_match('/AlFalaq\\\\AlFalaq\\\\Astro(?=\\\\|;)/', $code) === 1) {
                $violations[] = $file->getPathname() . ': engine import outside Services/non-Moon Strategies';
            }
        }

        // Regression (#20): bare-use evasion + string-literal false positive.
        $probeBare = 'use AlFalaq\\AlFalaq\\Astro;';
        self::assertSame(1, preg_match('/AlFalaq\\\\AlFalaq\\\\Astro(?=\\\\|;)/', $probeBare));
        $probeStringBlanked = "\n";
        self::assertSame(0, preg_match('/AlFalaq\\\\AlFalaq\\\\Astro(?=\\\\|;)/', $probeStringBlanked));

        self::assertSame([], $violations);
    }

    /**
     * The Hijri context is data + arithmetic: it must never reach for the
     * astronomy engine or the Moon context. The M4 design composes prayer
     * times through `PrayerTimesCalculator` when it needs a sunset, so no
     * Hijri file has any reason to reference Astro or Moon.
     */
    public function testHijriStaysFreeOfAstronomyAndMoon(): void
    {
        $root = dirname(__DIR__);
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root . '/src/Hijri', \FilesystemIterator::SKIP_DOTS),
        );
        $violations = [];

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $code = self::codeWithoutCommentsAndStrings($file->getPathname());

            if (preg_match('/AlFalaq\\\\AlFalaq\\\\(Astro|Moon)(?=\\\\|;)/', $code) === 1) {
                $violations[] = $file->getPathname() . ': Hijri must not reference Astro or Moon';
            }
        }

        // Regression (#20): bare-use evasion + string-literal false positive.
        $probeBare = 'use AlFalaq\\AlFalaq\\Astro;';
        self::assertSame(1, preg_match('/AlFalaq\\\\AlFalaq\\\\(Astro|Moon)(?=\\\\|;)/', $probeBare));
        $probeStringBlanked = "\n";
        self::assertSame(0, preg_match('/AlFalaq\\\\AlFalaq\\\\(Astro|Moon)(?=\\\\|;)/', $probeStringBlanked));

        self::assertSame([], $violations);
    }
}
