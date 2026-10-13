<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Astro\Contracts;

use AlFalaq\AlFalaq\Astro\ValueObjects\SolarTime;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

/**
 * Day-level solar engine port: transit/rise/set plus angle events.
 *
 * Two precision paths over one geometry, kept deliberately: `hourAngle()`
 * returns pin-floored UTC instants for Prayer minute parity, while
 * `hourAngleFloat()` returns raw fractional hours for sub-second Julian-day
 * math (the crescent deep pins gate at 1e-6 deg, where the sun's 15 deg/hour
 * motion makes flooring cost ~0.004 deg). Unifying behind either precision
 * breaks the other side's gate — verified against the KL deep pin.
 * Services hint this port, never the engine class, so tests cross the same
 * seam callers use.
 *
 * Stable wiring surface: implement it for test doubles; construct
 * MeanSolarCalculator (the default adapter) for explicit service wiring
 * per docs/integrations.md.
 */
interface SolarDayEngine
{
    /**
     * Core solar day: transit, sunrise, sunset in UTC (Meeus Ch.15).
     *
     * @param Coordinates $coordinates Observer location.
     * @param CivilDate $date Civil date whose solar day is computed.
     * @param mixed $elevationM Observer height in metres; finite and >= 0.
     * @return SolarTime Transit, sunrise, sunset in UTC.
     */
    public function solarTime(Coordinates $coordinates, CivilDate $date, mixed $elevationM): SolarTime;

    /**
     * Fractional hours since midnight UTC for a solar depression angle, or null when unreachable.
     *
     * Raw twin of hourAngle() without the whole-second floor: the crescent
     * pipeline anchors its Julian-day math on this value (1e-6 deg deep
     * pins); prayer keeps the DateTime path untouched.
     *
     * @param Coordinates $coordinates Observer location.
     * @param CivilDate $date Civil date owning the event.
     * @param mixed $elevationM Observer height in metres; finite and >= 0.
     * @param float $angleDeg Solar altitude in degrees (e.g. -18.0 for fajr).
     * @param bool $afterTransit True for evening, false for morning.
     * @return float|null Fractional hours since midnight UTC, or null when the sun never reaches the angle.
     */
    public function hourAngleFloat(
        Coordinates $coordinates,
        CivilDate $date,
        mixed $elevationM,
        float $angleDeg,
        bool $afterTransit,
    ): ?float;

    /**
     * Instant the sun reaches a depression angle, or null when unreachable.
     *
     * @param Coordinates $coordinates Observer location.
     * @param CivilDate $date Civil date owning the event.
     * @param mixed $elevationM Observer height in metres; finite and >= 0.
     * @param float $angleDeg Solar altitude in degrees (e.g. -18.0 for fajr).
     * @param bool $afterTransit True for evening, false for morning.
     * @return \DateTimeImmutable|null Event instant in UTC, or null when the sun never reaches the angle.
     */
    public function hourAngle(
        Coordinates $coordinates,
        CivilDate $date,
        mixed $elevationM,
        float $angleDeg,
        bool $afterTransit,
    ): ?\DateTimeImmutable;

    /**
     * Asr instant for a shadow-length factor, or null when unreachable.
     *
     * @param Coordinates $coordinates Observer location.
     * @param CivilDate $date Civil date owning the event.
     * @param mixed $elevationM Observer height in metres; finite and >= 0.
     * @param mixed $shadowLength Shadow factor (1.0 Shafi, 2.0 Hanafi); finite and > 0.
     * @return \DateTimeImmutable|null Asr instant in UTC, or null when unreachable.
     */
    public function afternoon(
        Coordinates $coordinates,
        CivilDate $date,
        mixed $elevationM,
        mixed $shadowLength,
    ): ?\DateTimeImmutable;

    /**
     * Whether a full solar schedule exists for this date.
     *
     * @param Coordinates $coordinates Observer location.
     * @param CivilDate $date Civil date to probe.
     * @param mixed $elevationM Observer height in metres; finite and >= 0.
     * @return bool True when today rise+set and tomorrow rise are defined.
     */
    public function isSolarDefined(Coordinates $coordinates, CivilDate $date, mixed $elevationM): bool;
}
