<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Prayer\Strategies;

use AlFalaq\AlFalak\Prayer\Contracts\AsrRule;

/**
 * Hanafi Asr: shadow reaches twice the object height (+ noon shadow).
 */
final class HanafiAsr implements AsrRule
{
    /**
     * Hanafi shadow factor.
     *
     * @return float Always 2.0.
     */
    public function shadowLength(): float
    {
        return 2.0;
    }
}
