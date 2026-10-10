<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Moon\Strategies;

use AlFalaq\AlFalaq\Moon\Contracts\CrescentEvaluator;
use AlFalaq\AlFalaq\Moon\ValueObjects\CrescentGeometry;
use AlFalaq\AlFalaq\Moon\ValueObjects\CrescentVerdict;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;

/**
 * Odeh (2006) V-criterion with visibility classes A/B/C/D (M3).
 */
final class OdehEvaluator implements CrescentEvaluator
{
    /**
     * Compute V = f(ARCV_topo, W) and map to Odeh classes (M3).
     *
     * Topocentric ARCV derives exactly as moonAltitude − sunAltitude.
     * Danjon floor is strict (arcl < 6.4); classes are >= edges.
     * Widths below 1e-6 arcmin clamp to 1e-6.
     *
     * @param CrescentGeometry $geometry Sun/moon geometry at local sunset.
     * @return CrescentVerdict Odeh verdict, V score, class zone.
     */
    public function evaluate(CrescentGeometry $geometry): CrescentVerdict
    {
        $arcl = $geometry->elongationDeg;
        $width = $geometry->widthArcmin;

        if (! is_finite($arcl) || ! is_finite($width)) {
            throw new ValidationException('Odeh inputs must be finite.');
        }

        if ($width < 0.0) {
            throw new ValidationException('Odeh width must be >= 0.');
        }

        if ($arcl < 6.4) {
            return new CrescentVerdict('odeh', false, $this->v($geometry, $width), 'D');
        }

        $v = $this->v($geometry, $width);

        $class = match (true) {
            $v >= 5.65 => 'A',
            $v >= 2.0 => 'B',
            $v >= -0.96 => 'C',
            default => 'D',
        };

        return new CrescentVerdict('odeh', $class !== 'D', $v, $class);
    }

    /**
     * Odeh V value for derived topocentric ARCV.
     *
     * @param CrescentGeometry $geometry Source geometry.
     * @param float $width Raw width in arcmin (>= 0).
     * @return float V score.
     */
    private function v(CrescentGeometry $geometry, float $width): float
    {
        $w = max($width, 1e-6);
        $arcvTopo = $geometry->moonAltitude - $geometry->sunAltitude;
        $limit = -0.1018 * $w ** 3 + 0.7319 * $w ** 2 - 6.3226 * $w + 7.1651;

        return $arcvTopo - $limit;
    }
}
