<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Astro;

use AlFalaq\AlFalaq\Astro\Services\DeltaTProvider;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

/**
 * Espenak 2005–2050 ΔT polynomial transcribed from pin a7baa8b.
 *
 * Oracle: pin DeltaT.delta_t (author-verified 2026-10-10):
 * 62.92 + 0.32217·t + 0.005589·t², t = year − 2000.0.
 */
final class DeltaTProviderTest extends TestCase
{
    public function testForYearMatchesPin(): void
    {
        // Oracle: python3 -c "t=2025.5-2000.0; print(repr(62.92+0.32217*t+0.005589*t*t))".
        self::assertEqualsWithDelta(74.76958225, DeltaTProvider::forYear(2025.5), 1e-9);
    }

    public function testOutOfRangeStillReturns(): void
    {
        self::assertEqualsWithDelta(86.59300000000002, DeltaTProvider::forYear(1900.0), 1e-9);
        self::assertEqualsWithDelta(151.02700000000002, DeltaTProvider::forYear(2100.0), 1e-9);
    }

    public function testNonFiniteYearThrows(): void
    {
        $this->expectException(ValidationException::class);
        DeltaTProvider::forYear(NAN);
    }

    public function testNonNumericYearThrowsValidationException(): void
    {
        foreach ([true, false, 'x', null, INF] as $bad) {
            try {
                DeltaTProvider::forYear($bad);
                self::fail('Expected ValidationException for year ' . var_export($bad, true));
            } catch (ValidationException $e) {
                self::assertNotSame('', $e->getMessage());
            }
        }
    }
}
