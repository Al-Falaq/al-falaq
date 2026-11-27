<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Hijri\Services;

use AlFalaq\AlFalaq\Hijri\Contracts\DescribesCalendar;
use AlFalaq\AlFalaq\Hijri\Contracts\HijriCalendar;
use AlFalaq\AlFalaq\Hijri\Contracts\OffsetStore;
use AlFalaq\AlFalaq\Hijri\Enums\CalendarCoverage;
use AlFalaq\AlFalaq\Hijri\Enums\HijriDateFrame;
use AlFalaq\AlFalaq\Hijri\ValueObjects\CalendarProvenance;
use AlFalaq\AlFalaq\Hijri\ValueObjects\HijriCalculationMetadata;
use AlFalaq\AlFalaq\Hijri\ValueObjects\HijriCalculationOutcome;
use AlFalaq\AlFalaq\Hijri\ValueObjects\HijriDate;
use AlFalaq\AlFalaq\Prayer\Contracts\PrayerTimesCalculatorInterface;
use AlFalaq\AlFalaq\Prayer\Enums\Prayer;
use AlFalaq\AlFalaq\Prayer\Services\PrayerTimesCalculator;
use AlFalaq\AlFalaq\Prayer\ValueObjects\CalculationParameters;
use AlFalaq\AlFalaq\SharedKernel\Enums\CalculationContext;
use AlFalaq\AlFalaq\SharedKernel\Enums\WarningCode;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\AstronomicalException;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ConfigurationException;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CalculationMetadata;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CalculationSource;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CalculationWarning;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

/**
 * Gregorian→Hijri orchestration: civil-date frame, conversion, corrections (M4).
 *
 * Two frames, mirroring the pin's bridge: without `changeAtSunset` the caller's
 * own zone decides the civil date (a local wall-clock date); with it, the UTC
 * date is used and rolled at the observer's maghrib. The calendar itself is
 * date-only and never sees a zone. Astronomy is composed via
 * `PrayerTimesCalculatorInterface` and never reimplemented here.
 */
final class GregorianToHijriConverter
{
    /**
     * Stable source id for the sunset frame's maghrib probe.
     */
    public const MAGHRIB_PROBE_SOURCE_ID = 'hijri.maghrib-probe';

    /**
     * Stable source id for a caller-supplied offset store.
     */
    public const OFFSET_SOURCE_ID = 'hijri.offset.caller';

    /**
     * Bind the prayer calculator used for the maghrib probe.
     *
     * @param PrayerTimesCalculatorInterface $calculator Calculator supplying maghrib.
     */
    public function __construct(private readonly PrayerTimesCalculatorInterface $calculator) {}

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
        return $this->trace($dateTime, $calendar, $coordinates, $parameters, $changeAtSunset, $offsets)['date'];
    }

    /**
     * Convert one instant and report the calendar, frame, offsets, and fallback.
     *
     * Shares trace() with convert(); provenance is read only after the numeric
     * path succeeds. The compact path never calls DescribesCalendar.
     *
     * @param \DateTimeImmutable $dateTime Source instant (zone-aware).
     * @param HijriCalendar $calendar Target calendar system.
     * @param Coordinates|null $coordinates Required with changeAtSunset (maghrib probe).
     * @param CalculationParameters|null $parameters Required with changeAtSunset (maghrib).
     * @param bool $changeAtSunset Roll the civil date at the observer's maghrib.
     * @param OffsetStore|null $offsets Official day corrections, if any.
     * @return HijriCalculationOutcome Converted date plus provenance.
     */
    public function convertWithMetadata(
        \DateTimeImmutable $dateTime,
        HijriCalendar $calendar,
        ?Coordinates $coordinates = null,
        ?CalculationParameters $parameters = null,
        bool $changeAtSunset = false,
        ?OffsetStore $offsets = null,
    ): HijriCalculationOutcome {
        $trace = $this->trace($dateTime, $calendar, $coordinates, $parameters, $changeAtSunset, $offsets);
        $warnings = [];
        $sources = [];

        if ($changeAtSunset) {
            $sources[] = CalculationSource::algorithm(self::MAGHRIB_PROBE_SOURCE_ID);
        }

        if ($offsets !== null) {
            $sources[] = CalculationSource::callerInput(self::OFFSET_SOURCE_ID);
        }

        $provenance = self::calendarProvenance($calendar, $warnings);

        if ($provenance->coverage === CalendarCoverage::GENERATED_TABLE) {
            $warnings[] = new CalculationWarning(
                WarningCode::GENERATED_CALENDAR_TABLE,
                'This calendar is a generated table, not an official authority dataset.',
                [
                    'calendar' => $provenance->key,
                    'range' => $provenance->range,
                ],
            );
        } elseif ($provenance->coverage === CalendarCoverage::ARITHMETIC_RULE) {
            $warnings[] = new CalculationWarning(
                WarningCode::TABULAR_CALENDAR_ESTIMATE,
                'This calendar uses an arithmetic month rule, not an observed month length.',
                ['offset_days' => $trace['offsetDays'] ?? 0],
            );
        }

        if ($trace['maghribFallbackApplied']) {
            $warnings[] = new CalculationWarning(
                WarningCode::SUNSET_FRAME_MAGHRIB_FALLBACK,
                'The full prayer schedule was undefined while resolving the sunset roll; maghrib alone was used. The date is the effective Gregorian date.',
                [
                    'date' => $trace['resolvedCivilDate']->isoformat(),
                    'reason' => 'full_schedule_undefined',
                ],
            );
        }

        return new HijriCalculationOutcome(
            $trace['date'],
            new HijriCalculationMetadata(
                CalculationMetadata::create(CalculationContext::HIJRI, $warnings, $sources),
                $dateTime,
                $changeAtSunset ? HijriDateFrame::SUNSET_ROLLOVER : HijriDateFrame::CIVIL_DATE,
                $trace['resolvedCivilDate'],
                $provenance,
                $trace['offsetDays'],
                $trace['maghribFallbackApplied'],
                $changeAtSunset ? $coordinates : null,
                $changeAtSunset ? $parameters : null,
            ),
        );
    }

    /**
     * Shared numeric path: conversion, rollover, and offset application.
     *
     * The compact path returns `date` and never reads calendar provenance.
     *
     * @param \DateTimeImmutable $dateTime Source instant (zone-aware).
     * @param HijriCalendar $calendar Target calendar system.
     * @param Coordinates|null $coordinates Required with changeAtSunset (maghrib probe).
     * @param CalculationParameters|null $parameters Required with changeAtSunset (maghrib).
     * @param bool $changeAtSunset Roll the civil date at the observer's maghrib.
     * @param OffsetStore|null $offsets Official day corrections, if any.
     * @return array{
     *     date: HijriDate,
     *     resolvedCivilDate: CivilDate,
     *     offsetDays: int|null,
     *     maghribFallbackApplied: bool,
     * }
     */
    private function trace(
        \DateTimeImmutable $dateTime,
        HijriCalendar $calendar,
        ?Coordinates $coordinates,
        ?CalculationParameters $parameters,
        bool $changeAtSunset,
        ?OffsetStore $offsets,
    ): array {
        $resolved = $this->resolveCivilDate($dateTime, $coordinates, $parameters, $changeAtSunset);
        $hijri = $calendar->fromGregorian($resolved['civilDate']);
        $offsetDays = $offsets?->offsetDaysFor($hijri, $calendar);

        return [
            'date' => $offsetDays === null ? $hijri : $calendar->shift($hijri, $offsetDays),
            'resolvedCivilDate' => $resolved['civilDate'],
            'offsetDays' => $offsetDays,
            'maghribFallbackApplied' => $resolved['maghribFallbackApplied'],
        ];
    }

    /**
     * Pick the civil date the calendar will be asked about.
     *
     * The backward probe is best-effort: at the lower `CivilDate` edge, or
     * when the previous UTC day's schedule is undefined, the current UTC day
     * is used instead of failing a conversion whose own maghrib is defined.
     *
     * @param \DateTimeImmutable $dateTime Source instant (zone-aware).
     * @param Coordinates|null $coordinates Observer, required for the sunset path.
     * @param CalculationParameters|null $parameters Method, required for the sunset path.
     * @param bool $changeAtSunset Use the UTC frame and roll at maghrib when true.
     * @return array{civilDate: CivilDate, maghribFallbackApplied: bool}
     */
    private function resolveCivilDate(
        \DateTimeImmutable $dateTime,
        ?Coordinates $coordinates,
        ?CalculationParameters $parameters,
        bool $changeAtSunset,
    ): array {
        if (! $changeAtSunset) {
            return [
                'civilDate' => CivilDate::fromDateTime($dateTime),
                'maghribFallbackApplied' => false,
            ];
        }

        if ($coordinates === null || $parameters === null) {
            throw new ConfigurationException('changeAtSunset requires both $coordinates and $parameters.');
        }

        $utc = CivilDate::fromDateTime($dateTime->setTimezone(new \DateTimeZone('UTC')));
        $forward = $this->maghribProbe($coordinates, $parameters, $utc);

        if ($dateTime >= $forward['instant']) {
            return [
                'civilDate' => $utc->plusDays(1),
                'maghribFallbackApplied' => $forward['fallbackApplied'],
            ];
        }

        $fallback = $forward['fallbackApplied'];

        try {
            $previous = $utc->plusDays(-1);
        } catch (ValidationException) {
            // 1900-01-01 is the first CivilDate; there is no previous day to
            // probe, so the instant belongs to the current UTC day.
            return ['civilDate' => $utc, 'maghribFallbackApplied' => $fallback];
        }

        try {
            $backward = $this->maghribProbe($coordinates, $parameters, $previous);
        } catch (AstronomicalException) {
            // The previous day's schedule can be undefined (for example polar
            // night) while the current day's maghrib is defined. Without a
            // previous maghrib there is no evidence of a backward roll.
            return ['civilDate' => $utc, 'maghribFallbackApplied' => $fallback];
        }

        $fallback = $fallback || $backward['fallbackApplied'];

        return [
            'civilDate' => $dateTime < $backward['instant'] ? $previous : $utc,
            'maghribFallbackApplied' => $fallback,
        ];
    }

    /**
     * Predicted maghrib instant plus whether the maghrib-only fallback ran.
     *
     * @param Coordinates $coordinates Observer.
     * @param CalculationParameters $parameters Calculation method.
     * @param CivilDate $date UTC civil day to evaluate.
     * @return array{instant: \DateTimeImmutable, fallbackApplied: bool}
     */
    private function maghribProbe(
        Coordinates $coordinates,
        CalculationParameters $parameters,
        CivilDate $date,
    ): array {
        try {
            return [
                'instant' => $this->calculator->calculate($coordinates, $date, $parameters)->timeFor(Prayer::MAGHRIB),
                'fallbackApplied' => false,
            ];
        } catch (AstronomicalException) {
            // Any astronomical "schedule undefined" — polar day/night or an
            // undefined Fajr/Asr/Isha component — falls back to maghrib alone.
            // A genuinely undefined maghrib still propagates; validation and
            // configuration errors are not AstronomicalException and surface.
            return [
                'instant' => $this->calculator->maghribAt($coordinates, $date, $parameters),
                'fallbackApplied' => true,
            ];
        }
    }

    /**
     * Read the calendar's own identity, or report an undescribed custom calendar.
     *
     * The calendar is reported in the metadata's `calendar` block, not as a
     * CalculationSource. A custom calendar adds only the warning.
     *
     * @param HijriCalendar $calendar Wired calendar.
     * @param list<CalculationWarning> $warnings Warning sink, appended in place.
     * @return CalendarProvenance Described identity or a key-only fallback.
     */
    private static function calendarProvenance(HijriCalendar $calendar, array &$warnings): CalendarProvenance
    {
        if ($calendar instanceof DescribesCalendar) {
            return $calendar->calendarProvenance();
        }

        $warnings[] = new CalculationWarning(
            WarningCode::UNIDENTIFIED_CALCULATION_SOURCE,
            'The calendar component does not implement DescribesCalendar.',
            ['component' => 'calendar', 'source_id' => 'custom.calendar'],
        );

        return new CalendarProvenance($calendar->name(), null, null);
    }
}
