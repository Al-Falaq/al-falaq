<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Prayer\Services;

use AlFalaq\AlFalaq\Prayer\Contracts\PrayerMetadataCalculatorInterface;
use AlFalaq\AlFalaq\Prayer\Contracts\PrayerTimesCalculatorInterface;
use AlFalaq\AlFalaq\Prayer\Contracts\TimetableServiceInterface;
use AlFalaq\AlFalaq\Prayer\ValueObjects\CalculationParameters;
use AlFalaq\AlFalaq\Prayer\ValueObjects\PrayerCalculationOutcome;
use AlFalaq\AlFalaq\Prayer\ValueObjects\PrayerSchedule;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ConfigurationException;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

/**
 * Monthly timetable orchestration over the single-day calculator (M1).
 */
final class TimetableService implements TimetableServiceInterface
{
    /**
     * Bind an optional pre-wired single-day calculator (test doubles cross the same seam callers use).
     *
     * @param PrayerTimesCalculatorInterface|null $calculator Pre-wired pipeline; null defers to PrayerTimesCalculator::defaultFor($parameters).
     */
    public function __construct(private readonly ?PrayerTimesCalculatorInterface $calculator = null) {}

    /**
     * Build on the default prayer pipeline.
     *
     * @return self Ready to build monthly timetables.
     */
    public static function default(): self
    {
        return new self();
    }

    /**
     * Compute one schedule per day of the month (UTC, constant memory).
     *
     * No yearly helper by design (no contract method) — callers loop monthly().
     *
     * @param Coordinates $coordinates Observer location.
     * @param int $year Gregorian year.
     * @param int $month 1–12.
     * @param CalculationParameters $parameters Method defaults + overrides.
     * @return list<PrayerSchedule> One entry per calendar day, in order.
     */
    public function monthly(Coordinates $coordinates, int $year, int $month, CalculationParameters $parameters): array
    {
        $days = self::daysInMonth($year, $month);

        $calc = $this->calculator ?? PrayerTimesCalculator::defaultFor($parameters);

        $out = [];

        for ($d = 1; $d <= $days; ++$d) {
            $out[] = $calc->calculate($coordinates, CivilDate::fromYmd($year, $month, $d), $parameters);
        }

        return $out;
    }

    /**
     * Compute one outcome per day of the month, preserving day-specific provenance.
     *
     * Each entry carries the metadata of its own day, so a polar fallback that
     * applied on only some days stays visible. The legacy monthly() path is
     * unchanged. A null calculator uses PrayerTimesCalculator::defaultFor();
     * an injected calculator must implement PrayerMetadataCalculatorInterface.
     *
     * @param Coordinates $coordinates Observer location.
     * @param int $year Gregorian year.
     * @param int $month 1–12.
     * @param CalculationParameters $parameters Method defaults + overrides.
     * @return list<PrayerCalculationOutcome> One outcome per calendar day, in order.
     */
    public function monthlyWithMetadata(
        Coordinates $coordinates,
        int $year,
        int $month,
        CalculationParameters $parameters,
    ): array {
        $days = self::daysInMonth($year, $month);

        $calc = $this->calculator ?? PrayerTimesCalculator::defaultFor($parameters);

        if (! $calc instanceof PrayerMetadataCalculatorInterface) {
            throw new ConfigurationException(
                'The injected calculator does not implement PrayerMetadataCalculatorInterface; monthlyWithMetadata() cannot record per-day provenance.',
            );
        }

        $out = [];

        for ($d = 1; $d <= $days; ++$d) {
            $out[] = $calc->calculateWithMetadata($coordinates, CivilDate::fromYmd($year, $month, $d), $parameters);
        }

        return $out;
    }

    /**
     * Validate the month and return its day count.
     *
     * @param int $year Gregorian year.
     * @param int $month 1–12.
     * @return int Number of calendar days in the month.
     */
    private static function daysInMonth(int $year, int $month): int
    {
        if ($month < 1 || $month > 12) {
            throw new ValidationException(sprintf('Invalid month %d: expected 1–12.', $month));
        }

        return (int) CivilDate::fromYmd($year, $month, 1)->toUtcMidnight()->format('t');
    }
}
