<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Hijri\Services;

use AlFalaq\AlFalak\Hijri\Contracts\HijriCalendar;
use AlFalaq\AlFalak\Hijri\Contracts\OffsetStore;
use AlFalaq\AlFalak\Hijri\ValueObjects\HijriDate;
use AlFalaq\AlFalak\Prayer\ValueObjects\CalculationParameters;
use AlFalaq\AlFalak\SharedKernel\ValueObjects\Coordinates;

final class GregorianToHijriConverter
{
    public function convert(
        \DateTimeImmutable $dateTime,
        HijriCalendar $calendar,
        ?Coordinates $coordinates = null,
        ?CalculationParameters $parameters = null,
        bool $changeAtSunset = false,
        ?OffsetStore $offsets = null,
    ): HijriDate {
        throw new \LogicException('not implemented: M4 converter pending');
    }
}
