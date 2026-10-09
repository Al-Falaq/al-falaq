<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Moon\Strategies;

use AlFalaq\AlFalak\Moon\Contracts\CrescentEvaluator;
use AlFalaq\AlFalak\Moon\ValueObjects\CrescentGeometry;
use AlFalaq\AlFalak\Moon\ValueObjects\CrescentVerdict;

/**
 * Odeh (2006) V-criterion with visibility classes A/B/C/D (M3).
 */
final class OdehEvaluator implements CrescentEvaluator
{
    /**
     * Compute V and map to Odeh classes (M3).
     *
     * @param CrescentGeometry $geometry Sun/moon geometry at local sunset.
     * @return CrescentVerdict Odeh verdict, V score, class zone.
     */
    public function evaluate(CrescentGeometry $geometry): CrescentVerdict
    {
        throw new \LogicException('not implemented: M3 Odeh pending');
    }
}
