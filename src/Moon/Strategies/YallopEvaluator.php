<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Moon\Strategies;

use AlFalaq\AlFalak\Moon\Contracts\CrescentEvaluator;
use AlFalaq\AlFalak\Moon\ValueObjects\CrescentGeometry;
use AlFalaq\AlFalak\Moon\ValueObjects\CrescentVerdict;

/**
 * Yallop (1997) q-criterion with zones A–E (M3).
 */
final class YallopEvaluator implements CrescentEvaluator
{
    /**
     * Compute q = f(ARCV, DAZ, W) and map to Yallop zones (M3).
     *
     * @param CrescentGeometry $geometry Sun/moon geometry at local sunset.
     * @return CrescentVerdict Yallop verdict, q score, zone.
     */
    public function evaluate(CrescentGeometry $geometry): CrescentVerdict
    {
        throw new \LogicException('not implemented: M3 Yallop pending');
    }
}
