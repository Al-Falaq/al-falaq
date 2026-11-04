<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Hijri\Exceptions;

use AlFalaq\AlFalaq\SharedKernel\Exceptions\AlFalaqException;

/**
 * Shipped Hijri month-start data is malformed (for example a normalized
 * impossible day such as `2023-02-30`).
 *
 * This is a library-data defect, not caller input: regeneration via
 * `tools/gen_hijri.py` must fix it. The guard exists so a corrupt table fails
 * loudly instead of silently shifting every later month start.
 */
final class HijriDataException extends AlFalaqException
{
    /**
     * Build a malformed month-start error.
     *
     * @param string $iso Rejected stored day.
     * @return self Ready to throw.
     */
    public static function forMonthStart(string $iso): self
    {
        return new self(sprintf(
            'Malformed generated month start %s: expected a real YYYY-MM-DD day.',
            $iso,
        ));
    }
}
