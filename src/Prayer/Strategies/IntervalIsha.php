<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Prayer\Strategies;

use AlFalaq\AlFalak\Prayer\Contracts\IshaRule;

final class IntervalIsha implements IshaRule
{
    public function __construct(public readonly int $minutes) {}

    public function minutesAfterMaghrib(): int
    {
        return $this->minutes;
    }

    public function angle(): ?float
    {
        return null;
    }
}
