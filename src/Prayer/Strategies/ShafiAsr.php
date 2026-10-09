<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Prayer\Strategies;

use AlFalaq\AlFalak\Prayer\Contracts\AsrRule;

final class ShafiAsr implements AsrRule
{
    public function shadowLength(): float
    {
        return 1.0;
    }
}
