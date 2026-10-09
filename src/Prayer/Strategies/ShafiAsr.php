<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Prayer\Strategies;

use AlFalaq\AlFalak\Prayer\Contracts\AsrRule;

/**
 * Shafi'i (majority) Asr: shadow reaches one object height (+ noon shadow).
 */
final class ShafiAsr implements AsrRule
{
    /**
     * Shafi'i shadow factor.
     *
     * @return float Always 1.0.
     */
    public function shadowLength(): float
    {
        return 1.0;
    }
}
