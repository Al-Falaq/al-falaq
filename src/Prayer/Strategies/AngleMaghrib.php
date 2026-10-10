<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Prayer\Strategies;

use AlFalaq\AlFalaq\Prayer\Contracts\MaghribRule;

/**
 * Maghrib as a solar-depression angle (Tehran 4.5°).
 */
final class AngleMaghrib implements MaghribRule
{
    /**
     * Angle rule (Tehran-style maghrib below sunset).
     *
     * @param float $angle Depression angle in degrees.
     */
    public function __construct(public readonly float $angle) {}

    /**
     * Angle-based: no interval.
     *
     * @return int|null Always null.
     */
    public function minutesAfterSunset(): ?int
    {
        return null;
    }

    /**
     * The configured depression angle.
     *
     * @return float Angle in degrees.
     */
    public function angle(): float
    {
        return $this->angle;
    }
}
