<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Prayer\Strategies;

use AlFalaq\AlFalaq\Prayer\Contracts\PolarCircleHandler;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

/**
 * Polar fallback bisecting toward the equator for solvable sunrise (pin _nearest_latitude verbatim).
 *
 * Rise/set existence is monotonic in |latitude| for a fixed date, so bisect
 * between the (invalid) requested latitude and the equator (always defined),
 * then back off 0.5° toward the equator for a usable day/night split.
 */
final class NearestLatitudeHandler implements PolarCircleHandler
{
    /**
     * Resolve to the nearest solvable latitude, keeping the date.
     *
     * @param Coordinates $coordinates Original observer location.
     * @param CivilDate $date Original civil date.
     * @param callable(Coordinates, CivilDate): bool $isSolvable Astro-backed probe (calculator-supplied).
     * @return array{coordinates: Coordinates, date: CivilDate} Effective inputs to compute with.
     */
    public function resolve(Coordinates $coordinates, CivilDate $date, callable $isSolvable): array
    {
        $lat = $coordinates->latitude;
        $lon = $coordinates->longitude;
        $sign = $lat >= 0 ? 1.0 : -1.0;
        $invalid = abs($lat);
        $valid = 0.0;

        for ($i = 0; $i < 60; $i++) {
            $mid = ($invalid + $valid) / 2;
            $candidate = Coordinates::fromDegrees($sign * $mid, $lon);

            if ($isSolvable($candidate, $date)) {
                $valid = $mid;
            } else {
                $invalid = $mid;
            }
        }

        return [
            'coordinates' => Coordinates::fromDegrees($sign * max($valid - 0.5, 0.0), $lon),
            'date' => $date,
        ];
    }
}
