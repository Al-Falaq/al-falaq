<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Hijri\Exceptions;

use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;

/**
 * Date outside a calendar's supported range (e.g. past an observational table edge).
 *
 * Boundary violations throw; they never saturate and never wrap (spec §3.2).
 */
final class HijriOutOfRangeException extends ValidationException
{
    /**
     * Build a range error naming the calendar, the rejected date and the supported span.
     *
     * @param string $calendar Calendar key (e.g. 'uqu').
     * @param string $iso ISO date or year-month that fell outside.
     * @param string $range Human-readable supported span (e.g. '1423–1500 AH').
     * @return self Ready to throw.
     */
    public static function forDate(string $calendar, string $iso, string $range): self
    {
        return new self(sprintf('Date %s out of range for %s calendar (%s).', $iso, $calendar, $range));
    }
}
