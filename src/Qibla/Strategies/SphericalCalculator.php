<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Qibla\Strategies;

use AlFalaq\AlFalak\Qibla\Contracts\GeodesyCalculator;
use AlFalaq\AlFalak\SharedKernel\ValueObjects\Coordinates;

final class SphericalCalculator implements GeodesyCalculator
{
    public const MEAN_RADIUS_KM = 6371.0088;

    public function inverse(Coordinates $from): array
    {
        throw new \LogicException('not implemented: M2 qibla pending');
    }
}
