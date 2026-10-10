<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Prayer\Strategies;

use AlFalaq\AlFalaq\Prayer\Contracts\PolarCircleHandler;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

/**
 * Polar NONE rule: echo inputs so the calculator re-probe throws.
 */
final class ThrowHandler implements PolarCircleHandler
{
    /**
     * Return the inputs unchanged (unsolvable dates throw downstream).
     *
     * @param Coordinates $coordinates Original observer location.
     * @param CivilDate $date Original civil date.
     * @param callable(Coordinates, CivilDate): bool $isSolvable Astro-backed probe (unused).
     * @return array{coordinates: Coordinates, date: CivilDate} Effective inputs to compute with.
     */
    public function resolve(Coordinates $coordinates, CivilDate $date, callable $isSolvable): array
    {
        return ['coordinates' => $coordinates, 'date' => $date];
    }
}
