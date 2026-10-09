<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Prayer\Contracts;

use AlFalaq\AlFalak\Prayer\ValueObjects\NextPrayer;
use AlFalaq\AlFalak\Prayer\ValueObjects\PrayerSchedule;

interface NextPrayerResolverInterface
{
    public function resolve(PrayerSchedule $today, PrayerSchedule $tomorrow, \DateTimeImmutable $nowUtc): NextPrayer;
}
