<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Prayer\ValueObjects;

use AlFalaq\AlFalaq\Prayer\Enums\Prayer;

/**
 * Display-zone view over a UTC schedule (leaf conversion, math never rerun).
 */
final readonly class DisplaySchedule
{
    /**
     * Wrap a UTC schedule with its display zone.
     *
     * @param PrayerSchedule $utc Source schedule (UTC).
     * @param \DateTimeZone $zone Zone for display output.
     */
    public function __construct(
        public PrayerSchedule $utc,
        public \DateTimeZone $zone,
    ) {}

    /**
     * One prayer instant converted to the display zone.
     *
     * @param Prayer $prayer Which marker.
     * @return \DateTimeImmutable Instant in the display zone.
     */
    public function timeFor(Prayer $prayer): \DateTimeImmutable
    {
        return $this->utc->timeFor($prayer)->setTimezone($this->zone);
    }

    /**
     * All ten instants converted to the display zone, in schedule order.
     *
     * @return array<string, \DateTimeImmutable> Keyed by Prayer value.
     */
    public function all(): array
    {
        $out = [];

        foreach ($this->utc->all() as $key => $dt) {
            $out[$key] = $dt->setTimezone($this->zone);
        }

        return $out;
    }

    /**
     * ISO8601 strings in the display zone (JSON-ready timetable row).
     *
     * @return array<string, string> Keyed by Prayer value.
     */
    public function toArray(): array
    {
        $out = [];

        foreach ($this->all() as $key => $dt) {
            $out[$key] = $dt->format('Y-m-d\TH:i:sP');
        }

        return $out;
    }
}
