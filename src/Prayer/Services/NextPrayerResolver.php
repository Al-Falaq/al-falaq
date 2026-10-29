<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Prayer\Services;

use AlFalaq\AlFalaq\Prayer\Contracts\NextPrayerResolverInterface;
use AlFalaq\AlFalaq\Prayer\Enums\Prayer;
use AlFalaq\AlFalaq\Prayer\ValueObjects\NextPrayer;
use AlFalaq\AlFalaq\Prayer\ValueObjects\PrayerSchedule;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;

/**
 * Next-prayer badge logic; pure function of two schedules + explicit now (M1).
 */
final class NextPrayerResolver implements NextPrayerResolverInterface
{
    /**
     * Build the stateless resolver.
     *
     * @return self Ready to resolve the next prayer.
     */
    public static function default(): self
    {
        return new self();
    }

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
        if ($nowUtc->getTimezone()->getName() !== 'UTC') {
            throw new ValidationException('NextPrayerResolver::nowUtc must be UTC.');
        }

        $now = $nowUtc->getTimestamp();

        foreach ([$today, $tomorrow] as $schedule) {
            foreach ($schedule->all() as $key => $instant) {
                if ($instant->getTimestamp() > $now) {
                    return new NextPrayer(Prayer::from($key), $instant, $instant->getTimestamp() - $now);
                }
            }
        }

        throw new ValidationException('No upcoming prayer in the today/tomorrow schedules.');
    }
}
