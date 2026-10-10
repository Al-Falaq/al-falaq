<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\SharedKernel\Traits;

use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;

/**
 * Shared guard for `with*()` minute offsets (rejects bools, clamps range).
 */
trait HasWithersGuard
{
    private static function guardOffsetMinutes(mixed $value, string $name, int $min = 0, int $max = 60): int
    {
        if (is_bool($value) || ! is_int($value)) {
            throw new ValidationException(sprintf('Invalid %s: expected int in [%d, %d], got %s.', $name, $min, $max, gettype($value)));
        }

        if ($value < $min || $value > $max) {
            throw new ValidationException(sprintf('Invalid %s: expected int in [%d, %d], got %d.', $name, $min, $max, $value));
        }

        return $value;
    }
}
