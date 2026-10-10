<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Prayer\Services;

use AlFalaq\AlFalaq\Prayer\Contracts\NextPrayerResolverInterface;
use AlFalaq\AlFalaq\Prayer\ValueObjects\NextPrayer;
use AlFalaq\AlFalaq\Prayer\ValueObjects\PrayerSchedule;

/**
 * Next-prayer badge logic; pure function of two schedules + explicit now (M1).
 */
final class NextPrayerResolver implements NextPrayerResolverInterface
{
    /**
     * Resolve the upcoming prayer across the today/tomorrow boundary.
     *
     * @param PrayerSchedule $today Today's schedule (UTC).
     * @param PrayerSchedule $tomorrow Tomorrow's schedule (UTC).
     * @param \DateTimeImmutable $nowUtc Explicit now in UTC (validated).
     * @return NextPrayer Prayer, its instant, and seconds until it.
     */
    public function resolve(PrayerSchedule $today, PrayerSchedule $tomorrow, \DateTimeImmutable $nowUtc): NextPrayer
    {
        throw new \LogicException('not implemented: M1 next-prayer pending');
    }
}
