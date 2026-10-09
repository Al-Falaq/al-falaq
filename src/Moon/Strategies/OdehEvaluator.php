<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Moon\Strategies;

use AlFalaq\AlFalak\Moon\Contracts\CrescentEvaluator;
use AlFalaq\AlFalak\Moon\ValueObjects\CrescentGeometry;
use AlFalaq\AlFalak\Moon\ValueObjects\CrescentVerdict;

final class OdehEvaluator implements CrescentEvaluator
{
    public function evaluate(CrescentGeometry $geometry): CrescentVerdict
    {
        throw new \LogicException('not implemented: M3 Odeh pending');
    }
}
