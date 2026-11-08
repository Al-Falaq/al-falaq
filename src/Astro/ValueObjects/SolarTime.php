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
    public \DateTimeImmutable $transit;

    public \DateTimeImmutable $sunrise;

    public \DateTimeImmutable $sunset;

    /**
     * Internal solar-day triple, UTC-anchored.
     *
     * Accepts any zero-offset zone — named `UTC`, a `+00:00` offset zone, or
     * an `@`-epoch construction (whose zone reports `+00:00`, not `UTC`) —
     * and rejects every other offset. Accepted instants are normalized to
     * named UTC: a zone such as `Europe/London` can be at offset 0 in winter,
     * but interval math must not cross its DST transition.
     *
     * @param \DateTimeImmutable $transit Solar noon (zero-offset UTC).
     * @param \DateTimeImmutable $sunrise Sunrise (zero-offset UTC).
     * @param \DateTimeImmutable $sunset Sunset (zero-offset UTC).
     */
    public function __construct(
        \DateTimeImmutable $transit,
        \DateTimeImmutable $sunrise,
        \DateTimeImmutable $sunset,
    ) {
        foreach (['transit' => $transit, 'sunrise' => $sunrise, 'sunset' => $sunset] as $name => $instant) {
            if ($instant->getOffset() !== 0) {
                throw new ValidationException(sprintf('SolarTime::%s must be UTC.', $name));
            }
        }

        $utc = new \DateTimeZone('UTC');
        $this->transit = $transit->setTimezone($utc);
        $this->sunrise = $sunrise->setTimezone($utc);
        $this->sunset = $sunset->setTimezone($utc);
    }
}
