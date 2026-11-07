<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\SharedKernel\ValueObjects;

use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;

/**
 * UTC-only minute math (rounding and shifting prayer instants).
 */
final class Minutes
{
    private function __construct() {}

    /**
     * Round to the minute, half-up, with hour/day rollover (zero-offset UTC only).
     *
     * @param \DateTimeImmutable $utc Instant in zero-offset UTC; other offsets throw.
     * @return \DateTimeImmutable Truncated/rounded copy in named UTC, seconds zeroed.
     */
    public static function roundedHalfUp(\DateTimeImmutable $utc): \DateTimeImmutable
    {
        if ($utc->getOffset() !== 0) {
            throw new ValidationException('Minutes::roundedHalfUp expects a UTC DateTimeImmutable. Convert first.');
        }

        $utc = $utc->setTimezone(new \DateTimeZone('UTC'));

        $seconds = (int) $utc->format('s');
        $truncated = $utc->setTime((int) $utc->format('G'), (int) $utc->format('i'), 0, 0);

        if ($seconds >= 30) {
            return $truncated->modify('+1 minute');
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
        return $utc->modify(sprintf('%+d minutes', $minutes));
    }
}
