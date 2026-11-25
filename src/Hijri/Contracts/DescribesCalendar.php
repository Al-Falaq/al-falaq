<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Hijri\Contracts;

use AlFalaq\AlFalaq\Hijri\ValueObjects\CalendarProvenance;

/**
 * Opt-in driven capability: a calendar that can describe its own identity.
 *
 * Deliberately separate from `HijriCalendar` so existing custom calendars keep
 * compiling. Consumers read it only when metadata is requested.
 */
interface DescribesCalendar
{
    /**
     * Stable, auditable identity for this calendar.
     *
     * @return CalendarProvenance Calendar key, family, coverage, and data identity.
     */
    public function calendarProvenance(): CalendarProvenance;
}
