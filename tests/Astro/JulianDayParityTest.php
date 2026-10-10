<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Astro;

use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\JulianDay;
use PHPUnit\Framework\TestCase;

/**
 * JulianDay exact parity vs pin CalendricalHelper.julian_day.
 *
 * Vectors are oracle outputs (author-verified 2026-10-10). Exact bitwise
 * equality is the claim (Fliegel–Van Flandern ≡ Meeus Ch.7 here).
 */
final class JulianDayParityTest extends TestCase
{
    public function testJulianDayMatchesPinExactly(): void
    {
        $cases = [
            [2025, 1, 15, 2460690.5],
            [2025, 2, 28, 2460734.5],
            [2024, 2, 29, 2460369.5],
            [2025, 12, 15, 2461024.5],
            [1900, 1, 1, 2415020.5],
            [2100, 12, 31, 2488433.5],
        ];

        foreach ($cases as [$y, $m, $d, $expected]) {
            self::assertSame($expected, JulianDay::fromCivilDate(CivilDate::fromYmd($y, $m, $d))->toFloat());
        }
    }
}
