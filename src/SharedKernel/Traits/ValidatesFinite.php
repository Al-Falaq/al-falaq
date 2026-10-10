<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\SharedKernel\Traits;

use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;

/**
 * Shared finite-float guard: bools and NaN/INF never reach the trig.
 */
trait ValidatesFinite
{
    /** @phpstan-assert float $value */
    private static function assertFinite(mixed $value, string $name): float
    {
        if (is_bool($value) || ! is_int($value) && ! is_float($value)) {
            throw new ValidationException(sprintf('Invalid %s: expected finite float, got %s.', $name, gettype($value)));
        }

        $float = (float) $value;

        if (! is_finite($float)) {
            throw new ValidationException(sprintf('Invalid %s: non-finite value rejected.', $name));
        }

        return $float;
    }
}
