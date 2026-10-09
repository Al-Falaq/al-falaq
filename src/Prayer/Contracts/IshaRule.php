<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Prayer\Contracts;

/**
 * Isha definition: either a solar-depression angle or minutes after Maghrib.
 *
 * Exactly one accessor returns non-null (AngleIsha vs IntervalIsha).
 */
interface IshaRule
{
    /**
     * Fixed delay after Maghrib, or null when angle-based.
     *
     * @return int|null Minutes after Maghrib; null for AngleIsha.
     */
    public function minutesAfterMaghrib(): ?int;

    /**
     * Solar depression angle in degrees, or null when interval-based.
     *
     * @return float|null Angle; null for IntervalIsha.
     */
    public function angle(): ?float;
}
