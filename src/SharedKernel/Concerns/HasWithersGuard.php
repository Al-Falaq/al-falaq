<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\SharedKernel\Concerns;

use AlFalaq\AlFalak\SharedKernel\Exceptions\ValidationException;

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
