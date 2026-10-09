<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Prayer\Services;

use AlFalaq\AlFalak\Prayer\Contracts\NextPrayerResolverInterface;
use AlFalaq\AlFalak\Prayer\ValueObjects\NextPrayer;
use AlFalaq\AlFalak\Prayer\ValueObjects\PrayerSchedule;

final class NextPrayerResolver implements NextPrayerResolverInterface
{
    public function resolve(PrayerSchedule $today, PrayerSchedule $tomorrow, \DateTimeImmutable $nowUtc): NextPrayer
    {
        throw new \LogicException('not implemented: M1 next-prayer pending');
    }
}
