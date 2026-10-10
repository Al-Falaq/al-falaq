<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Prayer\Contracts;

use AlFalaq\AlFalaq\Prayer\ValueObjects\NightPortions;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

/**
 * High-latitude night splitting (Middle/Seventh of night, twilight angle).
 *
 * Used when twilight never reaches the method angles; returns Fajr/Isha
 * portions of the maghrib→sunrise night.
 */
interface HighLatitudeCorrection
{
    /**
     * Split the night into Fajr and Isha portions for this date/place.
     *
     * @param Coordinates $coordinates Observer location.
     * @param CivilDate $date Civil date to correct.
     * @return NightPortions Fajr and Isha instants (UTC).
     */
    public function portions(Coordinates $coordinates, CivilDate $date): NightPortions;
}
