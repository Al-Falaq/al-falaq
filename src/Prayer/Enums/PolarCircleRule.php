<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Prayer\Enums;

/**
 * Polar-circle fallback strategy selector (NONE lets it throw).
 */
enum PolarCircleRule: string
{
    case NONE = 'none';
    case NEAREST_LATITUDE = 'nearest_latitude';
    case NEAREST_DAY = 'nearest_day';
    case MAKKAH = 'makkah';
}
