<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Hijri;

use AlFalaq\AlFalaq\Hijri\Enums\HijriMonth;
use PHPUnit\Framework\TestCase;

/**
 * Hijri month display labels (English transliteration + Arabic, no intl).
 */
final class HijriMonthTest extends TestCase
{
    public function testEnglishLabels(): void
    {
        $expected = [
            1 => 'Muharram', 2 => 'Safar', 3 => "Rabiʿ al-awwal", 4 => "Rabiʿ al-thani",
            5 => 'Jumada al-ula', 6 => 'Jumada al-akhirah', 7 => 'Rajab', 8 => "Shaʿban",
            9 => 'Ramadan', 10 => 'Shawwal', 11 => "Dhu al-Qaʿdah", 12 => 'Dhu al-Hijjah',
        ];

        foreach (HijriMonth::cases() as $month) {
            self::assertSame($expected[$month->value], $month->label('en'), $month->name);
        }
    }

    public function testArabicLabels(): void
    {
        $expected = [
            1 => 'محرم', 2 => 'صفر', 3 => 'ربيع الأول', 4 => 'ربيع الثاني',
            5 => 'جمادى الأولى', 6 => 'جمادى الآخرة', 7 => 'رجب', 8 => 'شعبان',
            9 => 'رمضان', 10 => 'شوال', 11 => 'ذو القعدة', 12 => 'ذو الحجة',
        ];

        foreach (HijriMonth::cases() as $month) {
            self::assertSame($expected[$month->value], $month->label('ar'), $month->name);
        }
    }

    public function testUnknownLocaleFallsBackToEnglish(): void
    {
        self::assertSame('Ramadan', HijriMonth::RAMADAN->label('fr'));
        self::assertSame('Ramadan', HijriMonth::RAMADAN->label(''));
    }
}
