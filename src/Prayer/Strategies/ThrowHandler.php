<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Prayer\Strategies;

use AlFalaq\AlFalak\Prayer\Contracts\PolarCircleHandler;
use AlFalaq\AlFalak\SharedKernel\ValueObjects\Coordinates;

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
