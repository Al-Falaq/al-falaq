<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Moon\Contracts;

use AlFalaq\AlFalaq\Moon\ValueObjects\CrescentGeometry;
use AlFalaq\AlFalaq\Moon\ValueObjects\CrescentVerdict;

/**
 * One visibility criterion over sunset crescent geometry (pure function).
 *
 * Yallop/Odeh/MABIMS evaluators share this shape; all live in Strategies/.
 */
interface CrescentEvaluator
{
    /**
     * Judge visibility from computed geometry.
     *
     * @param CrescentGeometry $geometry Sun/moon geometry at local sunset.
     * @return CrescentVerdict Criterion name, verdict, score, zone/class.
     */
    public function evaluate(CrescentGeometry $geometry): CrescentVerdict;
}
