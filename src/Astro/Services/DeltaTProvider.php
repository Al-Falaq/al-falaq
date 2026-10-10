<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Astro\Services;

use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;

/**
 * ΔT (TT − UT1) provider isolating polynomial-vs-table updates (M1).
 *
 * @internal Formula source + year range land in docs/hijri.md.
 */
final class DeltaTProvider
{
    /**
     * ΔT in seconds for a fractional year (Espenak 2005–2050 polynomial).
     *
     * Pin a7baa8b DeltaT.delta_t: 62.92 + 0.32217·t + 0.005589·t² with
     * t = year − 2000.0. Calibrated for 2005–2050; out-of-range years still
     * return an extrapolated value (no warning convention in PHP). The prayer
     * path never calls this — reserved for the M3 lunar path, same as the pin.
     *
     * @param float $year Fractional year (e.g. 2025.5).
     * @return float Seconds to add to UT1.
     */
    public static function forYear(float $year): float
    {
        if (! is_finite($year)) {
            throw new ValidationException(sprintf('year must be finite, got %s.', var_export($year, true)));
        }

        $t = $year - 2000.0;

        return 62.92 + 0.32217 * $t + 0.005589 * $t * $t;
    }
}
