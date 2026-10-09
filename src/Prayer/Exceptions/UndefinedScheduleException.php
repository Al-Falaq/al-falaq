<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Prayer\Exceptions;

use AlFalaq\AlFalak\SharedKernel\Exceptions\AstronomicalException;

/**
 * A polar date has no valid schedule; carries context for the fix message.
 */
final class UndefinedScheduleException extends AstronomicalException
{
    /**
     * Build a polar-undefined error pointing at the resolving rule.
     *
     * @param \AlFalaq\AlFalak\SharedKernel\ValueObjects\Coordinates $coordinates Observer location.
     * @param \AlFalaq\AlFalak\SharedKernel\ValueObjects\CivilDate $date Civil date with no sunrise/sunset.
     * @param string $rule PolarCircleRule case that was active.
     * @return self Ready to throw.
     */
    public static function polar(
        \AlFalaq\AlFalak\SharedKernel\ValueObjects\Coordinates $coordinates,
        \AlFalaq\AlFalak\SharedKernel\ValueObjects\CivilDate $date,
        string $rule,
    ): self {
        return new self(sprintf(
            'Undefined schedule at %s on %s (rule %s). Try PolarCircleRule::NEAREST_LATITUDE. See docs/polar.md.',
            (string) $coordinates,
            $date->isoformat(),
            $rule,
        ));
    }
}
