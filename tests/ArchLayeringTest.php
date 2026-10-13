<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Layering rules: the three directed dependency edges.
 */
#[Group('arch')]
final class ArchLayeringTest extends ArchSupport
{
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
                if (preg_match('/AlFalaq\\\\AlFalaq\\\\(?:' . $context . '(?![\\w])|\\{(?:[^},]*,)*\\s*' . $context . '(?![\\w]))/', $code) === 1) {
                    $violations[] = $file->getPathname() . ': Astro must not reference ' . $context;
                }
            }
        }

        // Regression (#20): bare-use evasion + string-literal false positive.
        $context = 'Astro'; // probe the loop-built pattern with a bare-use context.
        $probeBare = 'use AlFalaq\\AlFalaq\\Astro;';
        self::assertSame(1, preg_match('/AlFalaq\\\\AlFalaq\\\\(?:' . $context . '(?![\\w])|\\{(?:[^},]*,)*\\s*' . $context . '(?![\\w]))/', $probeBare));
        self::assertSame(1, preg_match('/AlFalaq\\\\AlFalaq\\\\(?:' . $context . '(?![\\w])|\\{(?:[^},]*,)*\\s*' . $context . '(?![\\w]))/', 'use AlFalaq\\AlFalaq\\Astro as Engine;'));
        self::assertSame(1, preg_match('/AlFalaq\\\\AlFalaq\\\\(?:' . $context . '(?![\\w])|\\{(?:[^},]*,)*\\s*' . $context . '(?![\\w]))/', 'use AlFalaq\\AlFalaq\\{Astro, Qibla};'));
        self::assertSame(0, preg_match('/AlFalaq\\\\AlFalaq\\\\(?:' . $context . '(?![\\w])|\\{(?:[^},]*,)*\\s*' . $context . '(?![\\w]))/', 'use AlFalaq\\AlFalaq\\Astrology;'));
        $probeStringBlanked = "\n";
        self::assertSame(0, preg_match('/AlFalaq\\\\AlFalaq\\\\(?:' . $context . '(?![\\w])|\\{(?:[^},]*,)*\\s*' . $context . '(?![\\w]))/', $probeStringBlanked));

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

            if (preg_match('/AlFalaq\\\\AlFalaq\\\\(?:Astro(?![\\w])|\\{(?:[^},]*,)*\\s*Astro(?![\\w]))/', $code) === 1) {
                $violations[] = $file->getPathname() . ': engine import outside Services/non-Moon Strategies';
            }
        }

        // Regression (#20): bare-use evasion + string-literal false positive.
        $probeBare = 'use AlFalaq\\AlFalaq\\Astro;';
        self::assertSame(1, preg_match('/AlFalaq\\\\AlFalaq\\\\(?:Astro(?![\\w])|\\{(?:[^},]*,)*\\s*Astro(?![\\w]))/', $probeBare));
        self::assertSame(1, preg_match('/AlFalaq\\\\AlFalaq\\\\(?:Astro(?![\\w])|\\{(?:[^},]*,)*\\s*Astro(?![\\w]))/', 'use AlFalaq\\AlFalaq\\Astro as Engine;'));
        self::assertSame(1, preg_match('/AlFalaq\\\\AlFalaq\\\\(?:Astro(?![\\w])|\\{(?:[^},]*,)*\\s*Astro(?![\\w]))/', 'use AlFalaq\\AlFalaq\\{Astro, Qibla};'));
        self::assertSame(0, preg_match('/AlFalaq\\\\AlFalaq\\\\(?:Astro(?![\\w])|\\{(?:[^},]*,)*\\s*Astro(?![\\w]))/', 'use AlFalaq\\AlFalaq\\Astrology;'));
        $probeStringBlanked = "\n";
        self::assertSame(0, preg_match('/AlFalaq\\\\AlFalaq\\\\(?:Astro(?![\\w])|\\{(?:[^},]*,)*\\s*Astro(?![\\w]))/', $probeStringBlanked));

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

            if (preg_match('/AlFalaq\\\\AlFalaq\\\\(?:(?:Astro|Moon)(?![\\w])|\\{(?:[^},]*,)*\\s*(?:Astro|Moon)(?![\\w]))/', $code) === 1) {
                $violations[] = $file->getPathname() . ': Hijri must not reference Astro or Moon';
            }
        }

        // Regression (#20): bare-use evasion + string-literal false positive.
        $probeBare = 'use AlFalaq\\AlFalaq\\Astro;';
        self::assertSame(1, preg_match('/AlFalaq\\\\AlFalaq\\\\(?:(?:Astro|Moon)(?![\\w])|\\{(?:[^},]*,)*\\s*(?:Astro|Moon)(?![\\w]))/', $probeBare));
        self::assertSame(1, preg_match('/AlFalaq\\\\AlFalaq\\\\(?:(?:Astro|Moon)(?![\\w])|\\{(?:[^},]*,)*\\s*(?:Astro|Moon)(?![\\w]))/', 'use AlFalaq\\AlFalaq\\Moon as Engine;'));
        self::assertSame(1, preg_match('/AlFalaq\\\\AlFalaq\\\\(?:(?:Astro|Moon)(?![\\w])|\\{(?:[^},]*,)*\\s*(?:Astro|Moon)(?![\\w]))/', 'use AlFalaq\\AlFalaq\\{Moon, Qibla};'));
        self::assertSame(0, preg_match('/AlFalaq\\\\AlFalaq\\\\(?:(?:Astro|Moon)(?![\\w])|\\{(?:[^},]*,)*\\s*(?:Astro|Moon)(?![\\w]))/', 'use AlFalaq\\AlFalaq\\Astrology;'));
        $probeStringBlanked = "\n";
        self::assertSame(0, preg_match('/AlFalaq\\\\AlFalaq\\\\(?:(?:Astro|Moon)(?![\\w])|\\{(?:[^},]*,)*\\s*(?:Astro|Moon)(?![\\w]))/', $probeStringBlanked));

        self::assertSame([], $violations);
    }
}
