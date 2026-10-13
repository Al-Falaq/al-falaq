<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Prayer\Strategies;

use AlFalaq\AlFalaq\Prayer\Contracts\MaghribRule;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;

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

    /**
     * Validate the interval variant (minutes after sunset >= 0).
     *
     * @return void Throws on invalid configuration.
     */
    public function validate(): void
    {
        if ($this->minutes < 0) {
            throw new ValidationException('Invalid maghribRule: minutes after sunset must be >= 0.');
        }
    }
}
