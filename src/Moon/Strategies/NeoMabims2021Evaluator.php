<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Moon\Strategies;

use AlFalaq\AlFalaq\Moon\Contracts\CrescentEvaluator;
use AlFalaq\AlFalaq\Moon\ValueObjects\CrescentGeometry;
use AlFalaq\AlFalaq\Moon\ValueObjects\CrescentVerdict;

/**
 * Neo-MABIMS-2021 criterion: alt ≥ 3°, elongation ≥ 6.4° (no age gate).
 */
final class NeoMabims2021Evaluator implements CrescentEvaluator
{
    /**
     * Apply the 2021 thresholds.
     *
     * @param CrescentGeometry $geometry Sun/moon geometry at local sunset.
     * @return CrescentVerdict 'neo-mabims2021' verdict + elongation score.
     */
    public function evaluate(CrescentGeometry $geometry): CrescentVerdict
    {
        $visible = $geometry->moonAltitude >= 3.0 && $geometry->elongationDeg >= 6.4;

        return new CrescentVerdict('neo-mabims2021', $visible, $geometry->elongationDeg, $visible ? 'visible' : 'not-visible');
    }
}
