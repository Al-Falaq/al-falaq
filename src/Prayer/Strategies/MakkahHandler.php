<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Prayer\Strategies;

use AlFalaq\AlFalaq\Prayer\Contracts\PolarCircleHandler;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

/**
 * Polar fallback substituting Makkah coordinates (pin Constants.MAKKAH verbatim).
 *
 * Pin pointer: data/Constants.py::MAKKAH.
 *
 * Keeps the input date; only the location is remapped.
 */
final class MakkahHandler implements PolarCircleHandler
{
    /**
     * Replace any location with Makkah.
     *
     * @param Coordinates $coordinates Original observer location (ignored).
     * @param CivilDate $date Original civil date (kept).
     * @param callable(Coordinates, CivilDate): bool $isSolvable Astro-backed probe (unused).
     * @return array{coordinates: Coordinates, date: CivilDate} Effective inputs to compute with.
     */
    public function resolve(Coordinates $coordinates, CivilDate $date, callable $isSolvable): array
    {
        // Pin Constants.MAKKAH full precision (21.4225241, 39.8261818); 4dp display would be ~11 m off — kept verbatim for cross-port parity.
        return [
            'coordinates' => Coordinates::fromDegrees(21.4225241, 39.8261818),
            'date' => $date,
        ];
    }
}
