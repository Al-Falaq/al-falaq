<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Moon\Strategies;

use AlFalaq\AlFalaq\Moon\Contracts\CrescentEvaluator;
use AlFalaq\AlFalaq\Moon\ValueObjects\CrescentGeometry;
use AlFalaq\AlFalaq\Moon\ValueObjects\CrescentVerdict;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;

/**
 * MABIMS-1992 criterion: (alt ≥ 2° and elongation ≥ 3°) or age at moonset ≥ 8 h.
 */
final class Mabims1992Evaluator implements CrescentEvaluator
{
    /**
     * Apply the 1992 OR-gate on age at moonset (M3).
     *
     * Pin Mabims.py (Task 0 refs): all comparisons are >=, age input is
     * age-at-moonset (never sunset age). Null windows must take the
     * composition-root fallback — strategies never see the fallback.
     *
     * @param CrescentGeometry $geometry Sun/moon geometry at local sunset.
     * @return CrescentVerdict 'mabims1992' verdict + elongation score.
     */
    public function evaluate(CrescentGeometry $geometry): CrescentVerdict
    {
        $age = $geometry->ageAtMoonsetHours;

        if ($age === null) {
            throw new ValidationException('MABIMS-1992 needs age at moonset; null window must take the composition-root fallback.');
        }

        $visible = ($geometry->moonAltitude >= 2.0 && $geometry->elongationDeg >= 3.0) || $age >= 8.0;

        return new CrescentVerdict('mabims1992', $visible, $geometry->elongationDeg, $visible ? 'visible' : 'not-visible');
    }
}
