<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Hijri\Enums;

/**
 * Which civil day a Hijri conversion was resolved against.
 *
 * `CIVIL_DATE` converts the Gregorian day named by the caller's instant and
 * consults no astronomy. `SUNSET_ROLLOVER` converts the day selected by the
 * observer's maghrib, which can move the effective Gregorian date.
 */
enum HijriDateFrame: string
{
    case CIVIL_DATE = 'civil_date';
    case SUNSET_ROLLOVER = 'sunset_rollover';
}
