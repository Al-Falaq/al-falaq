<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Qibla\Contracts;

use AlFalaq\AlFalak\SharedKernel\ValueObjects\Coordinates;

interface GeodesyCalculator
{
    /** @return array{direction: float, distanceKm: float} */
    public function inverse(Coordinates $from): array;
}
