<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\SharedKernel\ValueObjects;

use AlFalaq\AlFalak\SharedKernel\Exceptions\ValidationException;

final class Minutes
{
    private function __construct() {}

    public static function roundedHalfUp(\DateTimeImmutable $utc): \DateTimeImmutable
    {
        if ($utc->getTimezone()->getName() !== 'UTC') {
            throw new ValidationException('Minutes::roundedHalfUp expects a UTC DateTimeImmutable. Convert first.');
        }

        $seconds = (int) $utc->format('s');
        $truncated = $utc->setTime((int) $utc->format('G'), (int) $utc->format('i'), 0, 0);

        if ($seconds >= 30) {
            $moved = $truncated->modify('+1 minute');

            if ($moved === false) {
                throw new ValidationException('Unable to round minutes.');
            }

            return $moved;
        }

        return $truncated;
    }

    public static function add(\DateTimeImmutable $utc, int $minutes): \DateTimeImmutable
    {
        $moved = $utc->modify(sprintf('%+d minutes', $minutes));

        if ($moved === false) {
            throw new ValidationException('Unable to add minutes.');
        }

        return $moved;
    }
}
