<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Moon\Strategies;

use AlFalaq\AlFalak\Moon\Contracts\CrescentEvaluator;
use AlFalaq\AlFalak\Moon\ValueObjects\CrescentGeometry;
use AlFalaq\AlFalak\Moon\ValueObjects\CrescentVerdict;

/**
 * MABIMS-1992 criterion: alt ≥ 2°, elongation ≥ 3°, age ≥ 8 h.
 */
final class Mabims1992Evaluator implements CrescentEvaluator
{
    /**
     * Apply the 1992 thresholds.
     *
     * @param CrescentGeometry $geometry Sun/moon geometry at local sunset.
     * @return CrescentVerdict 'mabims1992' verdict + elongation score.
     */
    public function evaluate(CrescentGeometry $geometry): CrescentVerdict
    {
        $visible = $geometry->moonAltitude >= 2.0 && $geometry->elongationDeg >= 3.0 && $geometry->moonAgeHours >= 8.0;

        return new CrescentVerdict('mabims1992', $visible, $geometry->elongationDeg, $visible ? 'visible' : 'not-visible');
    }
}
