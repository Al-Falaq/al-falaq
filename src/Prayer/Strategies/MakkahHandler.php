<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Prayer\Strategies;

use AlFalaq\AlFalaq\Prayer\Contracts\PolarCircleHandler;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

/**
 * Polar fallback substituting Makkah coordinates (21.4225, 39.8262).
 *
 * Keeps the input date; only the location is remapped.
 */
final class MakkahHandler implements PolarCircleHandler
{
    /**
     * Replace any location with Makkah.
     *
     * @param Coordinates $coordinates Ignored (kept for interface symmetry).
     * @return Coordinates Makkah coordinates.
     */
    public function resolve(Coordinates $coordinates): Coordinates
    {
        return Coordinates::fromDegrees(21.4225, 39.8262);
    }
}
