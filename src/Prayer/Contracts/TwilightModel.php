<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Prayer\Contracts;

/**
 * Seasonal twilight angles for the Moon-Sighting-Committee method (M1).
 *
 * Polynomial from Technical-PRD.md Appendix B; active only at abs(lat) ≥ 55.
 */
interface TwilightModel
{
    /**
     * Seasonal Fajr/Isha angles for a day-of-year and latitude.
     *
     * @param int $dayOfYear 1–366.
     * @param float $latitude Observer latitude in degrees.
     * @return array{morning: float, evening: float} Angles in degrees.
     */
    public function adjustedAngles(int $dayOfYear, float $latitude): array;
}
