<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Hijri\Services;

use AlFalaq\AlFalaq\Hijri\Contracts\HijriCalendar;
use AlFalaq\AlFalaq\Hijri\Contracts\OffsetStore;
use AlFalaq\AlFalaq\Hijri\ValueObjects\HijriDate;
use AlFalaq\AlFalaq\Prayer\Enums\Prayer;
use AlFalaq\AlFalaq\Prayer\Exceptions\UndefinedScheduleException;
use AlFalaq\AlFalaq\Prayer\Services\PrayerTimesCalculator;
use AlFalaq\AlFalaq\Prayer\ValueObjects\CalculationParameters;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ConfigurationException;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

/**
 * Gregorian→Hijri orchestration: civil-date frame, conversion, corrections (M4).
 *
 * Two frames, mirroring the pin's bridge: without `changeAtSunset` the caller's
 * own zone decides the civil date (a local wall-clock date); with it, the UTC
 * date is used and rolled at the observer's maghrib. The calendar itself is
 * date-only and never sees a zone. Astronomy is composed via
 * `PrayerTimesCalculator` and never reimplemented here.
 */
final class GregorianToHijriConverter
{
    /**
     * Bind the prayer calculator used for the maghrib probe.
     *
     * @param PrayerTimesCalculator $calculator Calculator supplying maghrib.
     */
    public function __construct(private readonly PrayerTimesCalculator $calculator) {}

    /**
     * Build a converter on the default prayer pipeline.
     *
     * @return self Ready to convert.
     */
    public static function default(): self
    {
        return new self(PrayerTimesCalculator::default());
    }

    /**
     * Convert one instant, with optional sunset rollover and day corrections.
     *
     * @param \DateTimeImmutable $dateTime Source instant (zone-aware).
     * @param HijriCalendar $calendar Target calendar system.
     * @param Coordinates|null $coordinates Required with changeAtSunset (maghrib probe).
     * @param CalculationParameters|null $parameters Required with changeAtSunset (maghrib).
     * @param bool $changeAtSunset Roll the civil date at the observer's maghrib.
     * @param OffsetStore|null $offsets Official day corrections, if any.
     * @return HijriDate Converted (and possibly rolled/corrected) date.
     */
    public function convert(
        \DateTimeImmutable $dateTime,
        HijriCalendar $calendar,
        ?Coordinates $coordinates = null,
        ?CalculationParameters $parameters = null,
        bool $changeAtSunset = false,
        ?OffsetStore $offsets = null,
    ): HijriDate {
        $hijri = $calendar->fromGregorian($this->resolveCivilDate($dateTime, $coordinates, $parameters, $changeAtSunset));

        if ($offsets === null) {
            return $hijri;
        }

        return $calendar->shift($hijri, $offsets->offsetDaysFor($hijri, $calendar));
    }

    /**
     * Pick the civil date the calendar will be asked about.
     *
     * @param \DateTimeImmutable $dateTime Source instant (zone-aware).
     * @param Coordinates|null $coordinates Observer, required for the sunset path.
     * @param CalculationParameters|null $parameters Method, required for the sunset path.
     * @param bool $changeAtSunset Use the UTC frame and roll at maghrib when true.
     * @return CivilDate Civil date to convert.
     */
    private function resolveCivilDate(
        \DateTimeImmutable $dateTime,
        ?Coordinates $coordinates,
        ?CalculationParameters $parameters,
        bool $changeAtSunset,
    ): CivilDate {
        if (! $changeAtSunset) {
            return CivilDate::fromDateTime($dateTime);
        }

        if ($coordinates === null || $parameters === null) {
            throw new ConfigurationException('changeAtSunset requires both $coordinates and $parameters.');
        }

        $utc = CivilDate::fromDateTime($dateTime->setTimezone(new \DateTimeZone('UTC')));

        if ($dateTime >= $this->maghrib($coordinates, $parameters, $utc)) {
            return $utc->plusDays(1);
        }

        $previous = $utc->plusDays(-1);

        if ($dateTime < $this->maghrib($coordinates, $parameters, $previous)) {
            return $previous;
        }

        return $utc;
    }

    /**
     * Predicted maghrib instant for one observer and UTC civil day.
     *
     * Falls back to the pipeline's maghrib-only computation when the full
     * schedule is undefined but maghrib itself is defined (#15).
     *
     * @param Coordinates $coordinates Observer.
     * @param CalculationParameters $parameters Calculation method.
     * @param CivilDate $date UTC civil day to evaluate.
     * @return \DateTimeImmutable Maghrib instant (UTC).
     */
    private function maghrib(
        Coordinates $coordinates,
        CalculationParameters $parameters,
        CivilDate $date,
    ): \DateTimeImmutable {
        try {
            return $this->calculator->calculate($coordinates, $date, $parameters)->timeFor(Prayer::MAGHRIB);
        } catch (UndefinedScheduleException) {
            // Strict high-latitude configs may leave the full schedule
            // undefined while maghrib itself is defined; resolve maghrib
            // alone rather than failing the conversion. Throws the same
            // exception when maghrib itself is undefined (e.g. polar night).
            return $this->calculator->maghribAt($coordinates, $date, $parameters);
        }
    }
}
