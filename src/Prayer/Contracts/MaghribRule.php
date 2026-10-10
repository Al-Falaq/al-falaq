<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Prayer\Contracts;

/**
 * Maghrib definition: sunset itself, an angle below it, or minutes after it.
 *
 * Exactly one accessor returns non-null for angle/interval variants.
 */
interface MaghribRule
{
    /**
     * Fixed delay after sunset, or null when angle-based (0 = at sunset).
     *
     * @return int|null Minutes after sunset; null for AngleMaghrib.
     */
    public function minutesAfterSunset(): ?int;

    /**
     * Solar depression angle in degrees, or null when interval-based.
     *
     * @return float|null Angle; null for MinutesAfterSunsetMaghrib.
     */
    public function angle(): ?float;
}
