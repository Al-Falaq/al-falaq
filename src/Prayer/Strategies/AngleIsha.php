<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Prayer\Strategies;

use AlFalaq\AlFalak\Prayer\Contracts\IshaRule;

final class AngleIsha implements IshaRule
{
    public function __construct(public readonly float $angle) {}

    public function minutesAfterMaghrib(): ?int
    {
        return null;
    }

    public function angle(): float
    {
        return $this->angle;
    }
}
