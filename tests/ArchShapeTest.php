<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Shape rules: finality, naming, purity (one concern per failure).
 */
#[Group('arch')]
final class ArchShapeTest extends ArchSupport
{
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
     * Service contracts are the only interfaces allowed an `Interface` suffix.
     *
     * The list is exact in both directions: stripping a suffix and adding one
     * to a driven port both fail.
     */
    private const SERVICE_CONTRACTS = [
        'AlFalaq\\AlFalaq\\Prayer\\Contracts\\NextPrayerResolverInterface',
        'AlFalaq\\AlFalaq\\Prayer\\Contracts\\PrayerTimesCalculatorInterface',
        'AlFalaq\\AlFalaq\\Prayer\\Contracts\\TimetableServiceInterface',
    ];

    /**
     * Services built through another public entry point, not a direct factory.
     *
     * `NightResolver` is an internal step wired by
     * `PrayerTimesCalculator::default()`; a standalone factory would duplicate
     * that wiring. `SunnahCalculator` needs the day's two schedules, so
     * `fromCalculator()` is the entry point. The value names the supported
     * construction path and the method that must keep existing.
     *
     * @var array<class-string, array{entry: class-string, method: string}>
     */
    private const SERVICES_WITHOUT_DIRECT_FACTORY = [
        'AlFalaq\\AlFalaq\\Prayer\\Services\\NightResolver' => [
            'entry' => 'AlFalaq\\AlFalaq\\Prayer\\Services\\PrayerTimesCalculator',
            'method' => 'default',
        ],
        'AlFalaq\\AlFalaq\\Prayer\\Services\\SunnahCalculator' => [
            'entry' => 'AlFalaq\\AlFalaq\\Prayer\\Services\\SunnahCalculator',
            'method' => 'fromCalculator',
        ],
    ];

    /**
     * Traits that contribute public `HijriCalendar` methods by design.
     *
     * The method list is exact: a new public helper on either trait is a
     * violation, not a silent extension of the exemption.
     *
     * @var array<class-string, list<string>>
     */
    private const CONTRACT_TRAITS = [
        'AlFalaq\\AlFalaq\\Hijri\\Traits\\LooksUpMonthStarts' => [
            'fromGregorian',
            'toGregorian',
            'monthLength',
        ],
        'AlFalaq\\AlFalaq\\Hijri\\Traits\\ShiftsHijriDays' => [
            'shift',
        ],
    ];

    public function testContractsContainInterfacesOnly(): void
    {
        $checked = 0;
        $root = str_replace('\\', '/', dirname(__DIR__)) . '/src/';

        foreach (self::srcPhpFiles() as $path) {
            $normalized = str_replace('\\', '/', $path);

            if (! str_contains($normalized, '/Contracts/')) {
                continue;
            }

            $checked++;
            $relative = substr($normalized, strlen($root), -4);
            $fqcn = 'AlFalaq\\AlFalaq\\' . str_replace('/', '\\', $relative);

            self::assertTrue(
                interface_exists($fqcn),
                sprintf('%s must declare an interface; Contracts/ holds ports only.', $fqcn),
            );
        }

        self::assertGreaterThanOrEqual(10, $checked, 'The Contracts/ scan found too few files; the gate would pass vacuously.');
    }

    public function testOnlyServiceContractsCarryTheInterfaceSuffix(): void
    {
        $found = [];

        foreach (self::srcClasses() as $class) {
            $reflection = new \ReflectionClass($class);

            if (! $reflection->isInterface()) {
                continue;
            }

            if (str_ends_with($reflection->getShortName(), 'Interface')) {
                $found[] = $class;
            }
        }

        sort($found);
        $expected = self::SERVICE_CONTRACTS;
        sort($expected);

        self::assertSame($expected, $found);

        foreach (self::SERVICE_CONTRACTS as $interface) {
            self::assertStringContainsString(
                '\\Contracts\\',
                $interface,
                sprintf('%s must live under a Contracts/ directory.', $interface),
            );
        }
    }

    public function testOneClassLikePerFile(): void
    {
        $violations = [];

        foreach (self::srcPhpFiles() as $path) {
            $count = self::classLikeDeclarationCount($path);

            if ($count !== 1) {
                $violations[] = sprintf('%s declares %d named class-likes; expected exactly 1.', $path, $count);
            }
        }

        self::assertSame([], $violations);
    }

    public function testClassLikeCounterIgnoresAnonymousClassesAndClassConstants(): void
    {
        $code = <<<'PHP'
<?php
namespace Probe;

enum ProbeEnum { case A; }

interface ProbeInterface {}

trait ProbeTrait {}

final class Probe
{
    public function make(): object
    {
        return new class {};
    }

    public function decorated(): object
    {
        return new #[\AllowDynamicProperties] class {};
    }

    public function name(): string
    {
        return Probe::class;
    }
}

final readonly class ProbeReadonly {}
PHP;

        self::assertSame(5, self::countClassLikeDeclarations($code));
    }

    public function testValueObjectsAreReadonly(): void
    {
        $checked = 0;
        $readonly = 0;

        foreach (self::srcClasses() as $class) {
            if (! str_contains($class, '\\ValueObjects\\')) {
                continue;
            }

            $checked++;
            $reflection = new \ReflectionClass($class);

            if ($reflection->isReadOnly()) {
                $readonly++;
            }

            self::assertTrue(
                $reflection->isReadOnly() || $reflection->getProperties() === [],
                sprintf(
                    'Value object %s must be readonly; only property-free static holders may stay plain final.',
                    $class,
                ),
            );
        }

        self::assertGreaterThanOrEqual(10, $checked, 'The value-object scan found too few classes; the gate would pass vacuously.');
        self::assertGreaterThanOrEqual(
            8,
            $readonly,
            'The readonly scan found too few readonly value objects; the gate would pass vacuously.',
        );
    }

    public function testTraitsExposeOnlyPrivateHelpers(): void
    {
        $checked = 0;
        $violations = [];

        foreach (self::srcClasses() as $class) {
            $reflection = new \ReflectionClass($class);

            if (! $reflection->isTrait()) {
                continue;
            }

            $checked++;
            $allowed = self::CONTRACT_TRAITS[$class] ?? [];

            foreach ($reflection->getProperties() as $property) {
                $violations[] = sprintf(
                    '%s::$%s must not be declared; traits are stateless.',
                    $class,
                    $property->getName(),
                );
            }

            foreach ($reflection->getMethods() as $method) {
                if ($method->isPrivate() || $method->isAbstract()) {
                    continue;
                }

                if (in_array($method->getName(), $allowed, true)) {
                    continue;
                }

                $violations[] = sprintf(
                    '%s::%s() must be private (or be listed in CONTRACT_TRAITS with a reason).',
                    $class,
                    $method->getName(),
                );
            }
        }

        self::assertGreaterThanOrEqual(3, $checked, 'The trait scan found too few traits; the gate would pass vacuously.');
        self::assertSame([], $violations);
    }

    /**
     * Public-context services expose a usable default() factory.
     *
     * `Astro/Services/` is internal engine wiring and is exempt from the
     * factory convention (the stable MeanSolarCalculator adapter is
     * constructed with `new`); the two dependency-bound Prayer services are
     * named in the map below.
     */
    public function testServicesExposeDefaultFactory(): void
    {
        $checked = 0;
        $violations = [];

        foreach (self::srcClasses() as $class) {
            if (! str_contains($class, '\\Services\\') || str_starts_with($class, 'AlFalaq\\AlFalaq\\Astro\\')) {
                continue;
            }

            $checked++;
            $reflection = new \ReflectionClass($class);

            if (self::hasUsableDefaultFactory($reflection)) {
                continue;
            }

            if (! $reflection->hasMethod('default') && array_key_exists($class, self::SERVICES_WITHOUT_DIRECT_FACTORY)) {
                continue;
            }

            $violations[] = sprintf(
                '%s must expose a public static zero-argument default(): self (or join SERVICES_WITHOUT_DIRECT_FACTORY).',
                $class,
            );
        }

        self::assertGreaterThanOrEqual(5, $checked, 'The service scan found too few services; the gate would pass vacuously.');
        self::assertSame([], $violations);
    }

    /**
     * Source must import its own names, never inline a fully-qualified name.
     *
     * The tokenizer sees `use`/`namespace` as T_NAME_QUALIFIED and docblocks
     * as T_DOC_COMMENT, so only a real code reference (`new \AlFalaq\...`)
     * trips the gate.
     */
    public function testNoInlineSourceFqcns(): void
    {
        $violations = [];
        $checked = 0;

        foreach (self::srcPhpFiles() as $path) {
            $checked++;

            foreach (self::inlineSourceFqcns((string) file_get_contents($path)) as $found) {
                $violations[] = sprintf('%s:%d: inline %s; import it instead.', $path, $found['line'], $found['name']);
            }
        }

        self::assertGreaterThanOrEqual(90, $checked, 'The source scan found too few files; the gate would pass vacuously.');
        self::assertSame([], $violations);
    }

    /**
     * Scanner regression probe: imports, namespaces, and docblocks are not
     * inline references.
     */
    public function testInlineFqcnScannerIgnoresImportsAndComments(): void
    {
        $code = <<<'PHP'
<?php
namespace Probe;

use AlFalaq\AlFalaq\Imported;

/** \AlFalaq\AlFalaq\Docblock is prose, not code. */
final class Probe
{
    public function make(): object
    {
        return new \AlFalaq\AlFalaq\Inline();
    }
}
PHP;

        self::assertSame(
            ['\\AlFalaq\\AlFalaq\\Inline'],
            array_column(self::inlineSourceFqcns($code), 'name'),
        );
    }

    /**
     * Every src file uses at most one consecutive blank line.
     *
     * Blank lines inside multi-line strings, heredocs, nowdocs, and
     * docblocks are not formatting violations; the check runs on source
     * with those token contents neutralized so their internal newlines
     * cannot trigger the gate.
     */
    public function testNoConsecutiveBlankLines(): void
    {
        $violations = [];
        $checked = 0;

        foreach (self::srcPhpFiles() as $path) {
            $checked++;
            $code = self::neutralizeMultilineTokens((string) file_get_contents($path));

            if (preg_match_all('/(?:\r?\n[ \t]*){3,}/', $code, $matches, PREG_OFFSET_CAPTURE) === false) {
                continue;
            }

            foreach ($matches[0] as [$match, $offset]) {
                $line = substr_count(substr($code, 0, (int) $offset), "\n") + 1;
                $violations[] = sprintf('%s:%d: %d consecutive blank lines.', $path, $line, substr_count($match, "\n") - 1);
            }
        }

        self::assertGreaterThanOrEqual(90, $checked, 'The source scan found too few files; the gate would pass vacuously.');
        self::assertSame([], $violations);
    }

    /**
     * Scanner regression probe: blank lines inside multiline strings,
     * heredocs, nowdocs, and docblocks are not formatting violations.
     */
    public function testBlankLineScannerIgnoresStringsAndComments(): void
    {
        $code = <<<'PHP'
<?php
namespace Probe;

final class Probe
{
    public function make(): object
    {
        $sql = "SELECT * FROM


            users";

        $heredoc = <<<HTML


        <div></div>
        HTML;

        /**
         * Docblock with blank lines inside.
         *
         * First paragraph.


         * Second paragraph.
         */
        return new \AlFalaq\AlFalaq\Inline();
    }
}
PHP;

        self::assertSame([], self::blankLineViolations($code), 'Blank lines inside strings/heredocs/docblocks must not be flagged.');
    }

    /**
     * Neutralize newlines inside multi-line string/comment/heredoc/nowdoc
     * tokens so their internal blank lines cannot trigger the gate, while
     * preserving byte offsets for line-number reporting.
     *
     * @param string $code PHP source.
     * @return string Source with multiline token newlines replaced by spaces.
     */
    private static function neutralizeMultilineTokens(string $code): string
    {
        $code = str_replace(["\r\n", "\r"], "\n", $code);
        $result = '';
        $allMultilineIds = [
            T_DOC_COMMENT,
            T_COMMENT,
            T_CONSTANT_ENCAPSED_STRING,
            T_START_HEREDOC,
            T_END_HEREDOC,
            T_ENCAPSED_AND_WHITESPACE,
        ];
        // nowdoc constants may not exist in every PHP version; add only defined ones.
        foreach (['T_START_NOWDOC', 'T_NOWDOC', 'T_END_NOWDOC'] as $name) {
            if (defined($name)) {
                $allMultilineIds[] = constant($name);
            }
        }
        $multilineIds = array_unique($allMultilineIds);

        foreach (token_get_all($code) as $token) {
            if (is_array($token) && in_array($token[0], $multilineIds, true)) {
                $result .= str_replace("\n", ' ', $token[1]);
            } else {
                $result .= is_array($token) ? $token[1] : $token;
            }
        }

        return $result;
    }

    /**
     * Consecutive-blank-line violations in a source string (multiline
     * tokens neutralized).
     *
     * @param string $code PHP source.
     * @return list<array{line: int, count: int}>
     */
    private static function blankLineViolations(string $code): array
    {
        $violations = [];
        $code = self::neutralizeMultilineTokens($code);

        if (preg_match_all('/(?:\r?\n[ \t]*){3,}/', $code, $matches, PREG_OFFSET_CAPTURE) === false) {
            return $violations;
        }

        foreach ($matches[0] as [$match, $offset]) {
            $line = substr_count(substr($code, 0, (int) $offset), "\n") + 1;
            $violations[] = ['line' => $line, 'count' => substr_count($match, "\n") - 1];
        }

        return $violations;
    }

    /**
     * Inline FQCNs in a source string (comments and imports excluded).
     *
     * @param string $code PHP source.
     * @return list<array{name: string, line: int}> Fully-qualified names in code position.
     */
    private static function inlineSourceFqcns(string $code): array
    {
        $names = [];

        foreach (token_get_all($code) as $token) {
            if (is_array($token)
                && $token[0] === T_NAME_FULLY_QUALIFIED
                && str_starts_with($token[1], '\\AlFalaq\\AlFalaq\\')
            ) {
                $names[] = ['name' => $token[1], 'line' => $token[2]];
            }
        }

        return $names;
    }

    public function testConventionAllowListsDoNotRot(): void
    {
        foreach (self::SERVICES_WITHOUT_DIRECT_FACTORY as $class => $factory) {
            self::assertTrue(
                class_exists($class),
                sprintf('SERVICES_WITHOUT_DIRECT_FACTORY entry %s no longer exists.', $class),
            );
            self::assertTrue(
                method_exists($factory['entry'], $factory['method']),
                sprintf(
                    'SERVICES_WITHOUT_DIRECT_FACTORY entry %s names a missing entry point %s::%s().',
                    $class,
                    $factory['entry'],
                    $factory['method'],
                ),
            );
            $entryPoint = new \ReflectionMethod($factory['entry'], $factory['method']);

            self::assertTrue(
                $entryPoint->isPublic() && $entryPoint->isStatic(),
                sprintf(
                    'SERVICES_WITHOUT_DIRECT_FACTORY entry point %s::%s() must be public static.',
                    $factory['entry'],
                    $factory['method'],
                ),
            );
            self::assertFalse(
                self::hasUsableDefaultFactory(new \ReflectionClass($class)),
                sprintf(
                    '%s now has a usable default(); remove its SERVICES_WITHOUT_DIRECT_FACTORY exemption.',
                    $class,
                ),
            );
        }

        foreach (self::CONTRACT_TRAITS as $trait => $methods) {
            self::assertTrue(
                trait_exists($trait),
                sprintf('CONTRACT_TRAITS entry %s no longer exists.', $trait),
            );

            $reflection = new \ReflectionClass($trait);

            foreach ($methods as $method) {
                self::assertTrue(
                    $reflection->hasMethod($method),
                    sprintf('CONTRACT_TRAITS entry %s no longer declares %s().', $trait, $method),
                );

                $traitMethod = $reflection->getMethod($method);

                self::assertTrue(
                    $traitMethod->isPublic(),
                    sprintf('CONTRACT_TRAITS entry %s::%s() must be public.', $trait, $method),
                );
            }
        }

        foreach (self::SERVICE_CONTRACTS as $interface) {
            self::assertTrue(
                interface_exists($interface),
                sprintf('SERVICE_CONTRACTS entry %s no longer exists.', $interface),
            );
            self::assertStringContainsString(
                '\\Contracts\\',
                $interface,
                sprintf('SERVICE_CONTRACTS entry %s must live under a Contracts/ directory.', $interface),
            );
        }
    }

    /**
     * Whether a class exposes a usable public static zero-argument factory.
     *
     * @param \ReflectionClass<object> $reflection Class to inspect.
     * @return bool True when default() is public, static, argument-free, and returns self.
     */
    private static function hasUsableDefaultFactory(\ReflectionClass $reflection): bool
    {
        if (! $reflection->hasMethod('default')) {
            return false;
        }

        $method = $reflection->getMethod('default');
        $returnType = $method->getReturnType();

        return $method->isPublic()
            && $method->isStatic()
            && $method->getNumberOfRequiredParameters() === 0
            && $returnType instanceof \ReflectionNamedType
            && ! $returnType->isBuiltin()
            && ! $returnType->allowsNull()
            && in_array($returnType->getName(), ['self', 'static', $reflection->getName()], true);
    }
}
