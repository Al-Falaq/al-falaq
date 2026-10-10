<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Prayer\Contracts;

/**
 * Seasonal twilight offsets for the Moon-Sighting-Committee method.
 *
 * Second-precision offsets from sunrise/sunset (pin Twilight.py verbatim:
 * `timedelta(seconds=±int(round(adjustment * 60.0)))`); the calculator
 * applies them as safe caps on every MSC row and as seventh-of-night
 * temps at high latitude.
 */
interface TwilightModel
{
    /**
     * Seasonal second offsets for a day-of-year, year and latitude.
     *
     * @param int $dayOfYear 1–366.
     * @param int $year Gregorian year (leap-year solstice shift).
     * @param float $latitude Observer latitude in degrees.
     * @return array{morning: int, evening: int} Seconds before sunrise / after sunset (rounded like the pin).
     */
    public function seasonalSeconds(int $dayOfYear, int $year, float $latitude): array;
}
