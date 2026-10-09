<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Prayer\Strategies;

use AlFalaq\AlFalak\Prayer\Contracts\HighLatitudeCorrection;
use AlFalaq\AlFalak\Prayer\ValueObjects\NightPortions;
use AlFalaq\AlFalak\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalak\SharedKernel\ValueObjects\Coordinates;

final class SeventhOfNightCorrection implements HighLatitudeCorrection
{
    public function portions(Coordinates $coordinates, CivilDate $date): NightPortions
    {
        throw new \LogicException('not implemented: M1 high-latitude pending');
    }
}
