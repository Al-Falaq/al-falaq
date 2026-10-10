<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Moon\Strategies;

use AlFalaq\AlFalaq\Moon\Contracts\CrescentEvaluator;
use AlFalaq\AlFalaq\Moon\ValueObjects\CrescentGeometry;
use AlFalaq\AlFalaq\Moon\ValueObjects\CrescentVerdict;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;

/**
 * Yallop (1997) q-criterion with zones A–F (M3).
 */
final class YallopEvaluator implements CrescentEvaluator
{
    /**
     * Compute q = f(ARCV_geo, W) and map to Yallop zones A–F (M3).
     *
     * Pin Yallop.py (Task 0 refs): limit cubic with +11.8371, q divisor 10,
     * strict > edges. Widths below 1e-6 arcmin clamp to 1e-6.
     *
     * @param CrescentGeometry $geometry Sun/moon geometry at local sunset.
     * @return CrescentVerdict Yallop verdict, q score, zone.
     */
    public function evaluate(CrescentGeometry $geometry): CrescentVerdict
    {
        $arcv = $geometry->arcvGeoDeg;
        $width = $geometry->widthArcmin;

        if (! is_finite($arcv) || ! is_finite($width)) {
            throw new ValidationException('Yallop inputs must be finite.');
        }

        if ($width < 0.0) {
            throw new ValidationException('Yallop width must be >= 0.');
        }

        $w = max($width, 1e-6);
        $limit = -0.1018 * $w ** 3 + 0.7319 * $w ** 2 - 6.3226 * $w + 11.8371;
        $q = ($arcv - $limit) / 10.0;

        $zone = match (true) {
            $q > 0.216 => 'A',
            $q > -0.014 => 'B',
            $q > -0.160 => 'C',
            $q > -0.232 => 'D',
            $q > -0.293 => 'E',
            default => 'F',
        };

        $visible = $zone === 'A' || $zone === 'B' || $zone === 'C' || $zone === 'D';

        return new CrescentVerdict('yallop', $visible, $q, $zone);
    }
}
