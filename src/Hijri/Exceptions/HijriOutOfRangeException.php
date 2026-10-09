<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Hijri\Exceptions;

use AlFalaq\AlFalak\SharedKernel\Exceptions\ValidationException;

/**
 * Date outside a calendar's supported range (e.g. past the UQ table edge).
 */
final class HijriOutOfRangeException extends ValidationException
{
    /**
     * Build a range error naming the calendar and the rejected date.
     *
     * @param string $calendar Calendar key (e.g. 'umm-al-qura').
     * @param string $iso ISO date string that fell outside.
     * @return self Ready to throw.
     */
    public static function forDate(string $calendar, string $iso): self
    {
        return new self(sprintf('Date %s out of range for %s calendar (1356–1500 AH).', $iso, $calendar));
    }
}
