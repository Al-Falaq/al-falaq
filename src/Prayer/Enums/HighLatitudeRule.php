<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Prayer\Enums;

/**
 * High-latitude night-splitting rule (picked per method, Appendix A).
 */
enum HighLatitudeRule: string
{
    case NONE = 'none';
    case MIDDLE_OF_NIGHT = 'middle_of_night';
    case SEVENTH_OF_NIGHT = 'seventh_of_night';
    case TWILIGHT_ANGLE = 'twilight_angle';
    case SEASONAL_TWILIGHT = 'seasonal_twilight';
}
