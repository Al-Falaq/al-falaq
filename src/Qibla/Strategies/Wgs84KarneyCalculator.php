<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Qibla\Strategies;

use AlFalaq\AlFalak\Qibla\Contracts\GeodesyCalculator;
use AlFalaq\AlFalak\SharedKernel\ValueObjects\Coordinates;

final class Wgs84KarneyCalculator implements GeodesyCalculator
{
    public const SEMI_MAJOR_KM = 6378.137;

    public const FLATTENING = 1.0 / 298.257223563;

    public function inverse(Coordinates $from): array
    {
        throw new \LogicException('not implemented: M2 Karney spike pending (fallback Vincenty allowed)');
    }
}
