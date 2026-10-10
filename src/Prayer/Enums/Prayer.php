<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Prayer\Enums;

/**
 * The ten prayer/day markers in schedule order (lowercase values are keys).
 *
 * SYURUK is the MY/SG alias of SUNRISE (same instant, documented naming).
 */
enum Prayer: string
{
    case IMSAK = 'imsak';
    case FAJR = 'fajr';
    case SUNRISE = 'sunrise';
    case SYURUK = 'syuruk';
    case ISHRAQ = 'ishraq';
    case DHUHA = 'dhuha';
    case DHUHR = 'dhuhr';
    case ASR = 'asr';
    case MAGHRIB = 'maghrib';
    case ISHA = 'isha';
}
