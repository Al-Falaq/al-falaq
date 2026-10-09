<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\SharedKernel\Exceptions;

final class InvalidCoordinatesException extends ValidationException
{
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

    public static function malformedArray(): self
    {
        return new self(
            'Invalid coordinates array: expected [lat, lon] with integer keys 0 and 1. Assoc ["lat"=>..,"lon"=>..] is rejected to avoid lat/lon swaps. See docs/prayer.md.',
        );
    }
}
