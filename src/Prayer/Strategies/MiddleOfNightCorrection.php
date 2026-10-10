<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Prayer\Strategies;

use AlFalaq\AlFalaq\Prayer\Contracts\HighLatitudeCorrection;
use AlFalaq\AlFalaq\Prayer\ValueObjects\NightPortions;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

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
