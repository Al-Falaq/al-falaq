<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Prayer\ValueObjects;

use AlFalaq\AlFalak\Prayer\Enums\Prayer;

final readonly class DisplaySchedule
{
    public function __construct(
        public PrayerSchedule $utc,
        public \DateTimeZone $zone,
    ) {}

    public function timeFor(Prayer $prayer): \DateTimeImmutable
    {
        return $this->utc->timeFor($prayer)->setTimezone($this->zone);
    }

    /** @return array<string, \DateTimeImmutable> */
    public function all(): array
    {
        $out = [];

        foreach ($this->utc->all() as $key => $dt) {
            $out[$key] = $dt->setTimezone($this->zone);
        }

        return $out;
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        $out = [];

        foreach ($this->all() as $key => $dt) {
            $out[$key] = $dt->format('Y-m-d\TH:i:sP');
        }

        return $out;
    }
}
