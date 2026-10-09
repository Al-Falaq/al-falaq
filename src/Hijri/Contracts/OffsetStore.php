<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Hijri\Contracts;

use AlFalaq\AlFalak\Hijri\ValueObjects\HijriDate;

interface OffsetStore
{
    public function offsetDaysFor(HijriDate $date, HijriCalendar $calendar): int;
}
