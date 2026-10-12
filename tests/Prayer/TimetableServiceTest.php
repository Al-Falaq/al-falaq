<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Prayer;

use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Services\PrayerTimesCalculator;
use AlFalaq\AlFalaq\Prayer\Services\TimetableService;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;
use PHPUnit\Framework\TestCase;

/**
 * Monthly timetable over the single-day calculator.
 */
final class TimetableServiceTest extends TestCase
{
    public function testJulyHas31OrderedUtcSchedules(): void
    {
        $coords = Coordinates::fromDegrees(3.1390, 101.6869);
        $params = CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters();

        $month = (new TimetableService())->monthly($coords, 2015, 7, $params);

        self::assertCount(31, $month);

        $prev = null;

        foreach ($month as $schedule) {
            self::assertSame('UTC', $schedule->fajr->getTimezone()->getName());

            if ($prev !== null) {
                self::assertGreaterThan($prev, $schedule->fajr->getTimestamp());
            }

            $prev = $schedule->fajr->getTimestamp();
        }

        $single = PrayerTimesCalculator::default()->calculate(
            $coords,
            CivilDate::fromYmd(2015, 7, 1),
            $params,
        );
        self::assertSame(
            $single->fajr->format('Y-m-d H:i'),
            $month[0]->fajr->format('Y-m-d H:i'),
        );
    }

    public function testFebruaryLeapYearHas29(): void
    {
        $month = (new TimetableService())->monthly(
            Coordinates::fromDegrees(3.1390, 101.6869),
            2024,
            2,
            CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters(),
        );

        self::assertCount(29, $month);
    }

    public function testInvalidMonthThrows(): void
    {
        try {
            (new TimetableService())->monthly(
                Coordinates::fromDegrees(3.1390, 101.6869),
                2025,
                13,
                CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters(),
            );

            self::fail('expected ValidationException for month 13.');
        } catch (ValidationException $e) {
            // The guard itself must throw (not a downstream TypeError).
            self::assertSame('Invalid month 13: expected 1–12.', $e->getMessage());
        }
    }

    public function testJanuaryAndDecemberSucceed(): void
    {
        // The 1–12 guard is inclusive on both ends (mutation gate).
        $service = new TimetableService();
        $coords = Coordinates::fromDegrees(3.1390, 101.6869);
        $params = CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters();

        self::assertCount(31, $service->monthly($coords, 2025, 1, $params));
        self::assertCount(31, $service->monthly($coords, 2025, 12, $params));
    }
}
