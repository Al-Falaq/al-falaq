<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Prayer\Contracts;

use AlFalaq\AlFalaq\Prayer\Enums\PolarCircleRule;
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
     * The optional `rule` key names the fallback that actually ran. Built-in
     * handlers return it; custom handlers should too so metadata does not have
     * to assume the caller's configured `PolarCircleRule`. When omitted, the
     * calculator records the caller's configured rule.
     *
     * @param Coordinates $coordinates Original observer location.
     * @param CivilDate $date Original civil date.
     * @param callable(Coordinates, CivilDate): bool $isSolvable Astro-backed probe (calculator-supplied).
     * @return array{coordinates: Coordinates, date: CivilDate, rule?: PolarCircleRule} Effective inputs plus the applied rule when known.
     */
    public function resolve(Coordinates $coordinates, CivilDate $date, callable $isSolvable): array;
}
