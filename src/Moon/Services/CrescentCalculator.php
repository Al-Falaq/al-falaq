<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Moon\Services;

use AlFalaq\AlFalak\Moon\ValueObjects\CrescentGeometry;
use AlFalaq\AlFalak\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalak\SharedKernel\ValueObjects\Coordinates;

final class CrescentCalculator
{
    public function atSunset(Coordinates $coordinates, CivilDate $date): CrescentGeometry
    {
        throw new \LogicException('not implemented: M3 moon pending');
    }
}
