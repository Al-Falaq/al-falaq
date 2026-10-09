<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Moon\Strategies;

use AlFalaq\AlFalak\Moon\Contracts\CrescentEvaluator;
use AlFalaq\AlFalak\Moon\ValueObjects\CrescentGeometry;
use AlFalaq\AlFalak\Moon\ValueObjects\CrescentVerdict;

final class Mabims1992Evaluator implements CrescentEvaluator
{
    public function evaluate(CrescentGeometry $geometry): CrescentVerdict
    {
        $visible = $geometry->moonAltitude >= 2.0 && $geometry->elongationDeg >= 3.0 && $geometry->moonAgeHours >= 8.0;

        return new CrescentVerdict('mabims1992', $visible, $geometry->elongationDeg, $visible ? 'visible' : 'not-visible');
    }
}
