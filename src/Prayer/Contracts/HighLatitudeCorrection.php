<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Prayer\Contracts;

use AlFalaq\AlFalak\Prayer\ValueObjects\NightPortions;
use AlFalaq\AlFalak\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalak\SharedKernel\ValueObjects\Coordinates;

interface HighLatitudeCorrection
{
    public function portions(Coordinates $coordinates, CivilDate $date): NightPortions;
}
