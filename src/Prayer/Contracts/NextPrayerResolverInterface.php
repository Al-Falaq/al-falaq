<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Prayer\Contracts;

use AlFalaq\AlFalaq\Prayer\ValueObjects\NextPrayer;
use AlFalaq\AlFalaq\Prayer\ValueObjects\PrayerSchedule;

/**
 * "What is next prayer?" badge logic over two adjacent schedules.
 *
 * Pure function of schedules + explicit now; reads no clock (M1).
 */
interface NextPrayerResolverInterface
{
    /**
     * Resolve the upcoming prayer across the today/tomorrow boundary.
     *
     * @param PrayerSchedule $today Today's schedule (UTC).
     * @param PrayerSchedule $tomorrow Tomorrow's schedule (UTC).
     * @param \DateTimeImmutable $nowUtc Explicit now in UTC (validated).
     * @return NextPrayer Prayer, its instant, and seconds until it.
     */
    public function resolve(PrayerSchedule $today, PrayerSchedule $tomorrow, \DateTimeImmutable $nowUtc): NextPrayer;
}
