<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Prayer\Services;

use AlFalaq\AlFalaq\Prayer\Contracts\TimetableServiceInterface;
use AlFalaq\AlFalaq\Prayer\ValueObjects\CalculationParameters;
use AlFalaq\AlFalaq\Prayer\ValueObjects\PrayerSchedule;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

/**
 * Monthly timetable orchestration over the single-day calculator (M1).
 */
final class TimetableService implements TimetableServiceInterface
{
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
        if ($month < 1 || $month > 12) {
            throw new ValidationException(sprintf('Invalid month %d: expected 1–12.', $month));
        }

        $calc = PrayerTimesCalculator::defaultFor($parameters);

        $days = (int) CivilDate::fromYmd($year, $month, 1)->toUtcMidnight()->format('t');

        $out = [];

        for ($d = 1; $d <= $days; ++$d) {
            $out[] = $calc->calculate($coordinates, CivilDate::fromYmd($year, $month, $d), $parameters);
        }

        return $out;
    }
}
