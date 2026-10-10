<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Astro\Services;

/**
 * ΔT (TT − UT1) provider isolating polynomial-vs-table updates (M1).
 *
 * @internal Formula source + year range land in docs/hijri.md.
 */
final class DeltaTProvider
{
    /**
     * ΔT in seconds for a fractional year (M1).
     *
     * @param float $year Fractional year (e.g. 2025.17).
     * @return float Seconds to add to UT1.
     */
    public static function forYear(float $year): float
    {
        throw new \LogicException('not implemented: M1 DeltaT pending Appendix B');
    }
}
