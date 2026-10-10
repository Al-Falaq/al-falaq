<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\SharedKernel\Exceptions;

/**
 * Coordinates failed validation; the message states the valid range, the
 * received value, and a doc link.
 */
final class InvalidCoordinatesException extends ValidationException
{
    /**
     * Build an out-of-range error naming the axis and what was received.
     *
     * @param string $which Either 'lat' or 'lon'.
     * @param mixed $received The offending value, echoed back for debugging.
     * @return self Ready to throw.
     */
    public static function outOfRange(string $which, mixed $received): self
    {
        $range = $which === 'lat' ? '[-90, 90]' : '[-180, 180]';
        $seen = is_scalar($received) ? var_export($received, true) : gettype($received);

        return new self(
            sprintf(
                'Invalid %s %s: expected %s, see docs/prayer.md. Received: %s.',
                $which === 'lat' ? 'latitude' : 'longitude',
                $which,
                $range,
                $seen,
            ),
        );
    }

    /**
     * Build a misshapen-array error (assoc arrays are rejected to avoid swaps).
     *
     * @return self Ready to throw.
     */
    public static function malformedArray(): self
    {
        return new self(
            'Invalid coordinates array: expected [lat, lon] with integer keys 0 and 1. Assoc ["lat"=>..,"lon"=>..] is rejected to avoid lat/lon swaps. See docs/prayer.md.',
        );
    }
}
