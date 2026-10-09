<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Prayer\Strategies;

use AlFalaq\AlFalak\Prayer\Contracts\IshaRule;

/**
 * Isha as a solar-depression angle below the horizon (most methods).
 */
final class AngleIsha implements IshaRule
{
    /**
     * Angle rule (angle lives on the instance, interval stays null).
     *
     * @param float $angle Depression angle in degrees (e.g. 18.0).
     */
    public function __construct(public readonly float $angle) {}

    /**
     * Angle-based: no interval.
     *
     * @return int|null Always null.
     */
    public function minutesAfterMaghrib(): ?int
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
