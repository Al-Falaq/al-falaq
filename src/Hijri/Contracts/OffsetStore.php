<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Hijri\Contracts;

use AlFalaq\AlFalak\Hijri\ValueObjects\HijriDate;

/**
 * Official day corrections injected by callers (e.g. -1…+2 moon-sighting offsets).
 *
 * Caller-supplied driven port; no shipped implementation at M0.
 */
interface OffsetStore
{
    /**
     * Correction days for one converted date under one calendar.
     *
     * @param HijriDate $date Converted date to correct.
     * @param HijriCalendar $calendar Calendar it came from.
     * @return int Signed day correction (usually -1…+2).
     */
    public function offsetDaysFor(HijriDate $date, HijriCalendar $calendar): int;
}
