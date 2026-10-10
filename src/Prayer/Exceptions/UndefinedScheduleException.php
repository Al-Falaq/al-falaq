<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Prayer\Exceptions;

use AlFalaq\AlFalaq\SharedKernel\Exceptions\AstronomicalException;

/**
 * A polar date has no valid schedule; carries context for the fix message.
 */
final class UndefinedScheduleException extends AstronomicalException
{
    /**
     * Build a polar-undefined error pointing at the resolving rule.
     *
     * @param \AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates $coordinates Observer location.
     * @param \AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate $date Civil date with no sunrise/sunset.
     * @param string $rule PolarCircleRule case that was active.
     * @return self Ready to throw.
     */
    public static function polar(
        \AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates $coordinates,
        \AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate $date,
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
