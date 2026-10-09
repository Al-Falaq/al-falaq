<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Hijri\Enums;

/**
 * The twelve Hijri months with en/ar display labels (no intl needed).
 */
enum HijriMonth: int
{
    case MUHARRAM = 1;
    case SAFAR = 2;
    case RABI_AL_AWWAL = 3;
    case RABI_AL_THANI = 4;
    case JUMADA_AL_ULA = 5;
    case JUMADA_AL_AKHIRAH = 6;
    case RAJAB = 7;
    case SHABAN = 8;
    case RAMADAN = 9;
    case SHAWWAL = 10;
    case DHU_AL_QADAH = 11;
    case DHU_AL_HIJJAH = 12;

    /**
     * Display name without ICU: English transliteration or Arabic.
     *
     * @param string $locale 'ar' for Arabic, anything else for English.
     * @return string Month label in the requested script.
     */
    public function label(string $locale): string
    {
        $en = [
            1 => 'Muharram', 2 => 'Safar', 3 => "Rabiʿ al-awwal", 4 => "Rabiʿ al-thani",
            5 => 'Jumada al-ula', 6 => 'Jumada al-akhirah', 7 => 'Rajab', 8 => "Shaʿban",
            9 => 'Ramadan', 10 => 'Shawwal', 11 => "Dhu al-Qaʿdah", 12 => 'Dhu al-Hijjah',
        ];
        $ar = [
            1 => 'محرم', 2 => 'صفر', 3 => 'ربيع الأول', 4 => 'ربيع الثاني',
            5 => 'جمادى الأولى', 6 => 'جمادى الآخرة', 7 => 'رجب', 8 => 'شعبان',
            9 => 'رمضان', 10 => 'شوال', 11 => 'ذو القعدة', 12 => 'ذو الحجة',
        ];

        return match ($locale) {
            'ar' => $ar[$this->value],
            default => $en[$this->value],
        };
    }
}
