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
     * Registry id for the Espenak 2005–2050 polynomial.
     */
    public const MODEL_ID = 'moon.delta-t.espenak-2005-2050';

    /**
     * Calibrated model range carried by the metadata source.
     */
    public const MODEL_RANGE = '2005..2050';

    /**
     * Inclusive lower bound of the calibrated interval (decimal year).
     */
    public const VALID_FROM_YEAR = 2005.0;

    /**
     * Inclusive upper bound of the calibrated interval (decimal year).
     */
    public const VALID_TO_YEAR = 2050.0;

    /**
     * ΔT in seconds for a fractional year (Espenak 2005–2050 polynomial).
     *
     * Pin a7baa8b DeltaT.delta_t: 62.92 + 0.32217·t + 0.005589·t² with
     * t = year − 2000.0. Calibrated for 2005–2050; out-of-range years still
     * return an extrapolated value. The metadata path reports an out-of-range
     * year with `delta_t_extrapolated`; this provider never throws for it.
     * The prayer path never calls this — reserved for the M3 lunar path, same
     * as the pin.
     *
     * @param mixed $year Fractional year (e.g. 2025.5); finite int|float, bools rejected.
     * @return float Seconds to add to UT1.
     */
    public static function forYear(mixed $year): float
    {
        if (is_bool($year) || (! is_int($year) && ! is_float($year))) {
            throw new ValidationException(sprintf('Year must be a finite number, got %s.', var_export($year, true)));
        }

        $yearFloat = (float) $year;

        if (! is_finite($yearFloat)) {
            throw new ValidationException(sprintf('Year must be a finite number, got %s.', var_export($year, true)));
        }

        $t = $yearFloat - 2000.0;

        return 62.92 + 0.32217 * $t + 0.005589 * $t * $t;
    }
}
