<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Inline-docs rules: every class-like and member documents its contract.
 */
#[Group('arch')]
final class ArchDocsTest extends ArchSupport
{
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

    public function testDocParamsSkipsCallableInnerNames(): void
    {
        // Named parameters inside callable shapes belong to the shape, not
        // the documented parameter; @param-out never satisfies @param.
        // (Regression probes for the extractor.)
        $doc = "/**\n * @param callable(int \$value): void \$callback Probe.\n * @param-out string \$onlyOut.\n */";

        self::assertSame(['callback'], self::docParams($doc));
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
     * Every `docs/*.md` path cited by the code exists.
     *
     * The error messages are why docs/ cannot be renamed freely:
     * InvalidCoordinatesException cites docs/prayer.md and
     * UndefinedScheduleException cites docs/polar.md.
     */
    public function testErrorMessagesCiteExistingDocs(): void
    {
        $references = [];

        foreach (self::srcPhpFiles() as $path) {
            preg_match_all('/docs\/[a-z0-9-]+\.md/', (string) file_get_contents($path), $matches);

            foreach ($matches[0] as $reference) {
                $references[$reference] = true;
            }
        }

        self::assertGreaterThanOrEqual(3, count($references), 'The docs-reference scan found too few citations.');

        foreach (array_keys($references) as $reference) {
            self::assertTrue(
                is_file(dirname(__DIR__) . '/' . $reference),
                sprintf('%s is cited by src/ but does not exist.', $reference),
            );
        }
    }

    /**
     * Every top-level contributor page is listed in the docs map.
     */
    public function testContributorDocsAreMapped(): void
    {
        $mapPath = dirname(__DIR__) . '/docs/README.md';
        self::assertFileExists($mapPath);
        $map = (string) file_get_contents($mapPath);
        $pages = glob(dirname(__DIR__) . '/docs/*.md') ?: [];
        $pages = array_values(array_filter(
            $pages,
            static fn(string $path): bool => basename($path) !== 'README.md',
        ));

        self::assertGreaterThanOrEqual(8, count($pages), 'The docs scan found too few contributor pages.');

        foreach ($pages as $path) {
            $file = basename($path);
            self::assertStringContainsString(
                '](' . $file . ')',
                $map,
                sprintf('docs/README.md does not map %s.', $file),
            );
        }
    }

    /**
     * Every relative Markdown link in the docs map resolves.
     */
    public function testDocsMapLinksResolve(): void
    {
        $mapPath = dirname(__DIR__) . '/docs/README.md';
        self::assertFileExists($mapPath);
        $map = (string) file_get_contents($mapPath);
        preg_match_all('/\]\(([^)#]+\.md)(?:#[^)]+)?\)/', $map, $matches);
        $targets = array_values(array_unique($matches[1]));

        self::assertGreaterThanOrEqual(8, count($targets), 'The docs map found too few links.');

        foreach ($targets as $target) {
            if (str_starts_with($target, 'http://') || str_starts_with($target, 'https://')) {
                continue;
            }

            self::assertTrue(
                is_file(dirname(__DIR__) . '/docs/' . $target),
                sprintf('docs/README.md link target %s does not exist.', $target),
            );
        }
    }
}
