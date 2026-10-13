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
        // the documented parameter (regression probe for the extractor).
        $doc = "/**\n * @param callable(int \$value): void \$callback Probe.\n */";

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
}
