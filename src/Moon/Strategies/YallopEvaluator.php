<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Moon\Strategies;

use AlFalaq\AlFalaq\Moon\Contracts\CrescentEvaluator;
use AlFalaq\AlFalaq\Moon\ValueObjects\CrescentGeometry;
use AlFalaq\AlFalaq\Moon\ValueObjects\CrescentVerdict;

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
