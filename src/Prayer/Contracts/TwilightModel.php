<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Prayer\Contracts;

/**
 * Seasonal twilight minutes for the Moon-Sighting-Committee method.
 *
 * Minute offsets from sunrise/sunset (pin Twilight.py verbatim);
 * the calculator applies them only for MSC at abs(lat) >= 55.
 */
interface TwilightModel
{
    /**
     * Seasonal minute offsets for a day-of-year, year and latitude.
     *
     * @param int $dayOfYear 1–366.
     * @param int $year Gregorian year (leap-year solstice shift).
     * @param float $latitude Observer latitude in degrees.
     * @return array{morning: int, evening: int} Whole minutes (rounded like the pin).
     */
    public function seasonalMinutes(int $dayOfYear, int $year, float $latitude): array;
}
