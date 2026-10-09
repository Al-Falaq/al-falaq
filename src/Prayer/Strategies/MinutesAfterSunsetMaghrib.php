<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Prayer\Strategies;

use AlFalaq\AlFalak\Prayer\Contracts\MaghribRule;

final class MinutesAfterSunsetMaghrib implements MaghribRule
{
    public function __construct(public readonly int $minutes = 0) {}

    public function minutesAfterSunset(): int
    {
        return $this->minutes;
    }

    public function angle(): ?float
    {
        return null;
    }
}
