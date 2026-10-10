<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Prayer\Strategies;

use AlFalaq\AlFalaq\Prayer\Contracts\IshaRule;

/**
 * Isha as fixed minutes after Maghrib (Umm al-Qura 90/120, Qatar 90).
 */
final class IntervalIsha implements IshaRule
{
    /**
     * Interval rule (minutes live on the instance, angle stays null).
     *
     * @param int $minutes Delay after Maghrib.
     */
    public function __construct(public readonly int $minutes) {}

    /**
     * The configured delay.
     *
     * @return int Minutes after Maghrib.
     */
    public function minutesAfterMaghrib(): int
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
