<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Moon\Contracts;

use AlFalaq\AlFalak\Moon\ValueObjects\CrescentGeometry;
use AlFalaq\AlFalak\Moon\ValueObjects\CrescentVerdict;

interface CrescentEvaluator
{
    public function evaluate(CrescentGeometry $geometry): CrescentVerdict;
}
