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

        foreach (self::publicClasses() as $class) {
            $relative = substr($class, strlen($prefix));

            if (! str_contains($reference, $relative)) {
                $missing[] = $relative;
            }
        }

        self::assertSame([], $missing, sprintf(
            'Public classes missing from docs/user/api-reference.md: %s',
            implode(', ', $missing),
        ));
    }

    /**
     * Public class-likes in src/, excluding the internal namespaces.
     *
     * @return list<class-string> Fully-qualified class names.
     */
    private static function publicClasses(): array
    {
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
