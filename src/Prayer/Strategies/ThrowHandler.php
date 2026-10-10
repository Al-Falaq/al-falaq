<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Prayer\Strategies;

use AlFalaq\AlFalaq\Prayer\Contracts\PolarCircleHandler;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

/**
 * Polar NONE rule: pass coordinates through and let the exception propagate.
 */
final class ThrowHandler implements PolarCircleHandler
{
    /**
     * Return the input unchanged (unsolvable dates throw downstream).
     *
     * @param Coordinates $coordinates Original observer location.
     * @return Coordinates The same coordinates.
     */
    public function resolve(Coordinates $coordinates): Coordinates
    {
        return $coordinates;
    }
}
