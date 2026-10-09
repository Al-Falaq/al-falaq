<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Prayer\Strategies;

use AlFalaq\AlFalak\Prayer\Contracts\TwilightModel;

/**
 * Moon-Sighting-Committee seasonal twilight (Appendix B polynomial, M1).
 *
 * Active only for MSC at abs(lat) ≥ 55; transcribe verbatim from the pin.
 */
final class SeasonAdjustedTwilight implements TwilightModel
{
    /**
     * Seasonal Fajr/Isha angles for a day-of-year and latitude (M1).
     *
     * @param int $dayOfYear 1–366.
     * @param float $latitude Observer latitude in degrees.
     * @return array{morning: float, evening: float} Angles in degrees.
     */
    public function adjustedAngles(int $dayOfYear, float $latitude): array
    {
        throw new \LogicException('not implemented: M1 MSC twilight pending Appendix B transcription');
    }

    /**
     * Morning (Fajr-side) seasonal angle leg (M1).
     *
     * @param int $dayOfYear 1–366.
     * @param float $latitude Observer latitude in degrees.
     * @return float Angle in degrees.
     */
    public function seasonAdjustedMorning(int $dayOfYear, float $latitude): float
    {
        throw new \LogicException('not implemented: M1 MSC twilight pending');
    }

    /**
     * Evening (Isha-side) seasonal angle leg (M1).
     *
     * @param int $dayOfYear 1–366.
     * @param float $latitude Observer latitude in degrees.
     * @return float Angle in degrees.
     */
    public function seasonAdjustedEvening(int $dayOfYear, float $latitude): float
    {
        throw new \LogicException('not implemented: M1 MSC twilight pending');
    }
}
