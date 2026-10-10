<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Astro\ValueObjects;

use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;

/**
 * Core solar day: transit, sunrise, sunset (UTC) driving prayer math (M1).
 *
 * @internal Prayer pipeline input; userland reads PrayerSchedule.
 */
final readonly class SolarTime
{
    /**
     * Internal solar-day triple (all UTC).
     *
     * @param \DateTimeImmutable $transit Solar noon (UTC).
     * @param \DateTimeImmutable $sunrise Sunrise (UTC).
     * @param \DateTimeImmutable $sunset Sunset (UTC).
     */
    public function __construct(
        public \DateTimeImmutable $transit,
        public \DateTimeImmutable $sunrise,
        public \DateTimeImmutable $sunset,
    ) {
        foreach (['transit' => $transit, 'sunrise' => $sunrise, 'sunset' => $sunset] as $name => $instant) {
            if ($instant->getTimezone()->getName() !== 'UTC') {
                throw new ValidationException(sprintf('SolarTime::%s must be UTC.', $name));
            }
        }
    }
}
