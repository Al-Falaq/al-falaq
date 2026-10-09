<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Moon\Strategies;

use AlFalaq\AlFalak\Moon\Contracts\CrescentEvaluator;
use AlFalaq\AlFalak\Moon\ValueObjects\CrescentGeometry;
use AlFalaq\AlFalak\Moon\ValueObjects\CrescentVerdict;

final class NeoMabims2021Evaluator implements CrescentEvaluator
{
    public function evaluate(CrescentGeometry $geometry): CrescentVerdict
    {
        $visible = $geometry->moonAltitude >= 3.0 && $geometry->elongationDeg >= 6.4;

        return new CrescentVerdict('neo-mabims2021', $visible, $geometry->elongationDeg, $visible ? 'visible' : 'not-visible');
    }
}
