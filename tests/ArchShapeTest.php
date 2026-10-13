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
}
