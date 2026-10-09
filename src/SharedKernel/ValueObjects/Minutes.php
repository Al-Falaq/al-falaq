<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\SharedKernel\ValueObjects;

use AlFalaq\AlFalak\SharedKernel\Exceptions\ValidationException;

/**
 * UTC-only minute math (rounding and shifting prayer instants).
 */
final class Minutes
{
    private function __construct() {}

    /**
     * Round to the minute, half-up, with hour/day rollover (UTC only).
     *
     * @param \DateTimeImmutable $utc Instant in UTC; other zones throw.
     * @return \DateTimeImmutable Truncated/rounded copy, seconds zeroed.
     */
    public static function roundedHalfUp(\DateTimeImmutable $utc): \DateTimeImmutable
    {
        if ($utc->getTimezone()->getName() !== 'UTC') {
            throw new ValidationException('Minutes::roundedHalfUp expects a UTC DateTimeImmutable. Convert first.');
        }

        $seconds = (int) $utc->format('s');
        $truncated = $utc->setTime((int) $utc->format('G'), (int) $utc->format('i'), 0, 0);

        if ($seconds >= 30) {
            $moved = $truncated->modify('+1 minute');

            if ($moved === false) {
                throw new ValidationException('Unable to round minutes.');
            }

            return $moved;
        }

        return $truncated;
    }

    /**
     * Shift an instant by whole minutes (caller owns the zone).
     *
     * @param \DateTimeImmutable $utc Base instant.
     * @param int $minutes Signed shift.
     * @return \DateTimeImmutable Shifted copy.
     */
    public static function add(\DateTimeImmutable $utc, int $minutes): \DateTimeImmutable
    {
        $moved = $utc->modify(sprintf('%+d minutes', $minutes));

        if ($moved === false) {
            throw new ValidationException('Unable to add minutes.');
        }

        return $moved;
    }
}
