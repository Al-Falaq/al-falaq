<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Prayer\Strategies;

use AlFalaq\AlFalaq\Prayer\Contracts\PolarCircleHandler;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\AstronomicalException;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

/**
 * Polar fallback scanning ±366 days for the nearest solvable date (pin _nearest_date verbatim).
 *
 * Pin pointer: PrayerTimes.py::_nearest_date_with_sunrise_sunset.
 *
 * Tries offsets 1…366 with deltas (−o, +o); plusDays outside 1900–2100 throws
 * ValidationException and is treated as unsolvable. Exhaustion means the
 * exact geographic poles, where no rise/set date exists numerically.
 */
final class NearestDayHandler implements PolarCircleHandler
{
    /**
     * Resolve to the nearest date with sunrise/sunset, keeping the location.
     *
     * @param Coordinates $coordinates Original observer location.
     * @param CivilDate $date Original civil date.
     * @param callable(Coordinates, CivilDate): bool $isSolvable Astro-backed probe (calculator-supplied).
     * @return array{coordinates: Coordinates, date: CivilDate} Effective inputs to compute with.
     */
    public function resolve(Coordinates $coordinates, CivilDate $date, callable $isSolvable): array
    {
        for ($offset = 1; $offset <= 366; $offset++) {
            foreach ([-$offset, $offset] as $delta) {
                try {
                    $candidate = $date->plusDays($delta);
                } catch (ValidationException) {
                    continue;
                }

                if ($isSolvable($coordinates, $candidate)) {
                    return ['coordinates' => $coordinates, 'date' => $candidate];
                }
            }
        }

        throw new AstronomicalException(
            'No date with sunrise/sunset found within a year. '
            . "At the exact geographic poles (|latitude| == 90) the sun's "
            . 'hour-angle is singular and no such date exists numerically; '
            . 'use PolarCircleRule.NEAREST_LATITUDE or MAKKAH instead.',
        );
    }
}
