<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Public-API reference gate: the documented inventory must cover every
 * public class-like.
 *
 * The reference page is the map of the public surface; when a class is
 * added and the page is not updated, the page silently stops being a map.
 * This test makes the omission fail the build.
 *
 * Internal namespaces are exempt: Astro/ is the @internal engine and
 * Hijri/Data/ holds generated tables, neither of which is public API.
 * The one stable Astro export — the `SolarDayEngine` port callers implement
 * for wiring — is documented by hand on the reference page; it is not
 * gate-enforced because the scan excludes Astro/ wholesale.
 */
#[Group('arch')]
final class ArchApiReferenceTest extends TestCase
{
    /**
     * Every public class-like appears in the API reference.
     */
    public function testEveryPublicClassIsDocumented(): void
    {
        $reference = (string) file_get_contents(dirname(__DIR__) . '/docs/user/api-reference.md');
        $prefix = 'AlFalaq\\AlFalaq\\';
        $missing = [];
        $classes = self::publicClasses();
        $documented = self::documentedClasses($reference);

        // Non-vacuity: a broken scan (wrong root, failed autoload, a skip that
        // swallows a whole context) would make every assertion below pass
        // trivially. The floor is just under today's 81 public class-likes and
        // above any single-context loss, so it only fires on real breakage.
        self::assertGreaterThanOrEqual(
            80,
            count($classes),
            'The public-class scan found too few entries; this gate would pass vacuously.',
        );

        foreach ($classes as $class) {
            $relative = substr($class, strlen($prefix));

            if (! isset($documented[$relative])) {
                $missing[] = $relative;
            }
        }

        self::assertSame([], $missing, sprintf(
            'Public classes missing from docs/user/api-reference.md: %s',
            implode(', ', $missing),
        ));
    }

    /**
     * Every class named in the reference has a source file.
     *
     * The forward gate catches an undocumented class; this one catches a
     * documented class that no longer exists (a deleted seam left in the
     * table). Both directions are needed for the page to be a map.
     */
    public function testEveryDocumentedClassHasASourceFile(): void
    {
        $reference = (string) file_get_contents(dirname(__DIR__) . '/docs/user/api-reference.md');
        $root = dirname(__DIR__) . '/src/';
        $documented = self::documentedClasses($reference);
        $missing = [];

        foreach (array_keys($documented) as $name) {
            if (! is_file($root . str_replace('\\', '/', $name) . '.php')) {
                $missing[] = $name;
            }
        }

        self::assertGreaterThanOrEqual(
            80,
            count($documented),
            'The reference scan found too few entries; this gate would pass vacuously.',
        );

        self::assertSame([], $missing, sprintf(
            'docs/user/api-reference.md names classes with no source file: %s',
            implode(', ', $missing),
        ));
    }

    /**
     * Class names documented in the first column of a reference table.
     *
     * Only the Class column counts: a name mentioned in prose, or in a
     * "Key members" cell, does not document the class. Each entry is the
     * exact backticked span, so a longer name can never stand in for a
     * shorter one (QiblaResultX does not document QiblaResult).
     *
     * @param string $reference Full text of the reference page.
     * @return array<string, true> Namespace-relative names as keys.
     */
    private static function documentedClasses(string $reference): array
    {
        $documented = [];
        $lines = preg_split('/\R/', $reference);

        if ($lines === false) {
            $lines = [];
        }

        foreach ($lines as $line) {
            if (! str_starts_with($line, '|')) {
                continue;
            }

            $cells = explode('|', $line);

            // | Class | Key members | — cells[0] is the empty edge, cells[1]
            // is the Class column itself.
            if (count($cells) < 3) {
                continue;
            }

            preg_match_all('/`([A-Za-z0-9_\\\\]+)`/', $cells[1], $matches);

            foreach ($matches[1] as $name) {
                $documented[$name] = true;
            }
        }

        return $documented;
    }

    /**
     * Public class-likes in src/, excluding the internal namespaces.
     *
     * @return list<class-string> Fully-qualified class names.
     */
    private static function publicClasses(): array
    {
        // Normalize separators: the Windows release gate runs this suite, and
        // a mismatch between the iterator's paths and the prefix would make
        // the internal-namespace skips silently stop matching.
        $root = str_replace('\\', '/', dirname(__DIR__));
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

            $pathname = str_replace('\\', '/', $file->getPathname());
            $relative = substr($pathname, strlen($root . '/src/'), -4);

            if (str_starts_with($relative, 'Astro/') || str_starts_with($relative, 'Hijri/Data/')) {
                continue;
            }

            $fqcn = $prefix . str_replace('/', '\\', $relative);

            if (class_exists($fqcn) || interface_exists($fqcn) || trait_exists($fqcn) || enum_exists($fqcn)) {
                $classes[] = $fqcn;
            }
        }

        sort($classes);

        return $classes;
    }
}
