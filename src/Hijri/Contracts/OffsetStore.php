<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Hijri\Contracts;

use AlFalaq\AlFalaq\Hijri\ValueObjects\HijriDate;

/**
 * Official day corrections injected by callers (e.g. a −1…+2 moon-sighting offset).
 *
 * No implementation ships: callers own the correction table. `offsetDaysFor()`
 * returns 0 for an unknown date, and the converter treats 0 as a no-op shift
 * (a stored explicit 0 is indistinguishable from a missing entry by design).
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
