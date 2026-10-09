<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Prayer\Exceptions;

use AlFalaq\AlFalak\SharedKernel\Exceptions\AstronomicalException;

final class UndefinedScheduleException extends AstronomicalException
{
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
