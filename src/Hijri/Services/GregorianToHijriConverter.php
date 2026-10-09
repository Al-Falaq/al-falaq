<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Hijri\Services;

use AlFalaq\AlFalak\Hijri\Contracts\HijriCalendar;
use AlFalaq\AlFalak\Hijri\Contracts\OffsetStore;
use AlFalaq\AlFalak\Hijri\ValueObjects\HijriDate;
use AlFalaq\AlFalak\Prayer\ValueObjects\CalculationParameters;
use AlFalaq\AlFalak\SharedKernel\ValueObjects\Coordinates;

/**
 * Gregorian→Hijri orchestration: convert, sunset-rollover, offsets (M4).
 *
 * Pure orchestration over an injected HijriCalendar; astronomy (maghrib
 * probe) composed via PrayerTimesCalculator, never reimplemented here.
 */
final class GregorianToHijriConverter
{
    /**
     * Convert one instant, with optional sunset rollover + corrections (M4).
     *
     * @param \DateTimeImmutable $dateTime Source instant (zone-aware).
     * @param HijriCalendar $calendar Target calendar system.
     * @param Coordinates|null $coordinates Required with changeAtSunset (maghrib probe).
     * @param CalculationParameters|null $parameters Required with changeAtSunset (method for maghrib).
     * @param bool $changeAtSunset Roll the date at local maghrib when true.
     * @param OffsetStore|null $offsets Official day corrections, if any.
     * @return HijriDate Converted (and possibly rolled/corrected) date.
     */
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
