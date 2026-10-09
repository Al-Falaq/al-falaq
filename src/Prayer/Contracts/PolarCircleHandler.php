<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Prayer\Contracts;

use AlFalaq\AlFalak\SharedKernel\ValueObjects\Coordinates;

/**
 * Polar-circle fallback: remap unsolvable coordinates to solvable ones.
 *
 * NearestLatitude bisects toward the equator, NearestDay scans ±366 days,
 * Makkah substitutes fixed coords, Throw lets the exception propagate.
 */
interface PolarCircleHandler
{
    /**
     * Resolve effective coordinates for the solar computation.
     *
     * @param Coordinates $coordinates Original observer location.
     * @return Coordinates Coordinates to compute with (maybe unchanged).
     */
    public function resolve(Coordinates $coordinates): Coordinates;
}
