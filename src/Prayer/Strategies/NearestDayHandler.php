<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Prayer\Strategies;

use AlFalaq\AlFalak\Prayer\Contracts\PolarCircleHandler;
use AlFalaq\AlFalak\SharedKernel\ValueObjects\Coordinates;

/**
 * Polar fallback scanning ±366 days for the nearest solvable date (M1).
 */
final class NearestDayHandler implements PolarCircleHandler
{
    /**
     * Resolve to the nearest date with sunrise/sunset (M1).
     *
     * @param Coordinates $coordinates Original observer location.
     * @return Coordinates Effective coordinates for the found date.
     */
    public function resolve(Coordinates $coordinates): Coordinates
    {
        throw new \LogicException('not implemented: M1 polar pending');
    }
}
