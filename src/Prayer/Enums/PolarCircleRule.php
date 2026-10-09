<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Prayer\Enums;

enum PolarCircleRule: string
{
    case NONE = 'none';
    case NEAREST_LATITUDE = 'nearest_latitude';
    case NEAREST_DAY = 'nearest_day';
    case MAKKAH = 'makkah';
}
