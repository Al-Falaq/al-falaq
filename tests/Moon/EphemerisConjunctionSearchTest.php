<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Moon;

use AlFalaq\AlFalaq\Moon\Services\EphemerisConjunctionSearch;
use PHPUnit\Framework\TestCase;

/**
 * Ephemeris-backed conjunction search: great-circle separation + default wiring.
 *
 * Age-search behavior lives in EphemerisConjunctionSearchAgeTest.
 */
final class EphemerisConjunctionSearchTest extends TestCase
{
    public function testSeparationReturnsGreatCircleDegrees(): void
    {
        $search = EphemerisConjunctionSearch::default();

        // Same point: separation 0.
        self::assertSame(0.0, $search->separation(10.0, 20.0, 10.0, 20.0));

        // 90 deg apart on equator: separation 90.
        self::assertEqualsWithDelta(90.0, $search->separation(0.0, 0.0, 90.0, 0.0), 1e-9);

        // Antipodal: separation 180.
        self::assertEqualsWithDelta(180.0, $search->separation(0.0, 0.0, 180.0, 0.0), 1e-9);
    }

    public function testSeparationIsSymmetric(): void
    {
        $search = EphemerisConjunctionSearch::default();

        $a = $search->separation(12.0, 34.0, 56.0, 78.0);
        $b = $search->separation(56.0, 78.0, 12.0, 34.0);

        self::assertEqualsWithDelta($a, $b, 1e-12);
    }

    public function testDefaultUsesCrescentEphemeris(): void
    {
        $search = EphemerisConjunctionSearch::default();

        self::assertInstanceOf(EphemerisConjunctionSearch::class, $search);
        self::assertTrue($search->separation(0.0, 0.0, 1.0, 0.0) > 0.0);
    }
}
