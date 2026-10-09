<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Prayer\Enums;

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
