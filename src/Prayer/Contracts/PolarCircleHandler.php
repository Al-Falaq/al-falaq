<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Prayer\Contracts;

use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

/**
 * Polar-circle fallback: remap unsolvable day to solvable inputs.
 *
 * NearestLatitude bisects toward the equator, NearestDay scans ±366 days,
 * Makkah substitutes fixed coords, Throw echoes inputs for the re-probe throw.
 */
interface PolarCircleHandler
{
    /**
     * Resolve effective location+date for an unsolvable polar day.
     *
     * @param Coordinates $coordinates Original observer location.
     * @param CivilDate $date Original civil date.
     * @param callable(Coordinates, CivilDate): bool $isSolvable Astro-backed probe (calculator-supplied).
     * @return array{coordinates: Coordinates, date: CivilDate} Effective inputs to compute with.
     */
    public function resolve(Coordinates $coordinates, CivilDate $date, callable $isSolvable): array;
}
