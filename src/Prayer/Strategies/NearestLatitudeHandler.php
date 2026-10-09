<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Prayer\Strategies;

use AlFalaq\AlFalak\Prayer\Contracts\PolarCircleHandler;
use AlFalaq\AlFalak\SharedKernel\ValueObjects\Coordinates;

/**
 * Polar fallback bisecting toward the equator for solvable sunrise (M1).
 *
 * Default rule: max 60 iterations, then 0.5° backoff toward the equator.
 */
final class NearestLatitudeHandler implements PolarCircleHandler
{
    /**
     * Resolve to the nearest solvable latitude (M1).
     *
     * @param Coordinates $coordinates Original observer location.
     * @return Coordinates Effective coordinates for computation.
     */
    public function resolve(Coordinates $coordinates): Coordinates
    {
        throw new \LogicException('not implemented: M1 polar pending');
    }
}
