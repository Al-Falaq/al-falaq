<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Prayer\Strategies;

use AlFalaq\AlFalak\Prayer\Contracts\MaghribRule;

/**
 * Maghrib as fixed minutes after sunset (0 = at sunset, most methods).
 */
final class MinutesAfterSunsetMaghrib implements MaghribRule
{
    /**
     * Interval rule with optional default (0 = at sunset).
     *
     * @param int $minutes Delay after sunset.
     */
    public function __construct(public readonly int $minutes = 0) {}

    /**
     * The configured delay.
     *
     * @return int Minutes after sunset.
     */
    public function minutesAfterSunset(): int
    {
        return $this->minutes;
    }

    /**
     * Interval-based: no angle.
     *
     * @return float|null Always null.
     */
    public function angle(): ?float
    {
        return null;
    }
}
