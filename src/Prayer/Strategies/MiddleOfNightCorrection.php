<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Prayer\Strategies;

use AlFalaq\AlFalak\Prayer\Contracts\HighLatitudeCorrection;
use AlFalaq\AlFalak\Prayer\ValueObjects\NightPortions;
use AlFalaq\AlFalak\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalak\SharedKernel\ValueObjects\Coordinates;

/**
 * Middle-of-night high-latitude correction (default rule, M1).
 */
final class MiddleOfNightCorrection implements HighLatitudeCorrection
{
    /**
     * Split the night at its midpoint (M1).
     *
     * @param Coordinates $coordinates Observer location.
     * @param CivilDate $date Civil date to correct.
     * @return NightPortions Fajr and Isha instants (UTC).
     */
    public function portions(Coordinates $coordinates, CivilDate $date): NightPortions
    {
        throw new \LogicException('not implemented: M1 high-latitude pending');
    }
}
