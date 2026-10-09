<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Hijri\Exceptions;

use AlFalaq\AlFalak\SharedKernel\Exceptions\ValidationException;

final class HijriOutOfRangeException extends ValidationException
{
    public static function forDate(string $calendar, string $iso): self
    {
        return new self(sprintf('Date %s out of range for %s calendar (1356–1500 AH).', $iso, $calendar));
    }
}
