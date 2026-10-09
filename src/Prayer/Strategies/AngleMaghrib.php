<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Prayer\Strategies;

use AlFalaq\AlFalak\Prayer\Contracts\MaghribRule;

final class AngleMaghrib implements MaghribRule
{
    public function __construct(public readonly float $angle) {}

    public function minutesAfterSunset(): ?int
    {
        return null;
    }

    public function angle(): float
    {
        return $this->angle;
    }
}
