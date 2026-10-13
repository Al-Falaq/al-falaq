<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Prayer;

use AlFalaq\AlFalaq\Prayer\Contracts\PrayerTimesCalculatorInterface;
use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Services\PrayerTimesCalculator;
use AlFalaq\AlFalaq\Prayer\Services\TimetableService;
use AlFalaq\AlFalaq\Prayer\ValueObjects\CalculationParameters;
use AlFalaq\AlFalaq\Prayer\ValueObjects\PrayerSchedule;
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

    public function testInjectedCalculatorDoubleServesMonthly(): void
    {
        // The monthly loop crosses the same interface seam callers use: a
        // pre-wired double replaces 28 full-pipeline calls.
        $utc = new \DateTimeZone('UTC');
        $at = static fn(string $time): \DateTimeImmutable => new \DateTimeImmutable($time, $utc);
        $canned = new PrayerSchedule(
            $at('2025-02-01 22:00'),
            $at('2025-02-01 22:10'),
            $at('2025-02-01 23:00'),
            $at('2025-02-01 23:00'),
            $at('2025-02-01 23:15'),
            $at('2025-02-01 23:30'),
            $at('2025-02-02 05:00'),
            $at('2025-02-02 08:00'),
            $at('2025-02-02 10:00'),
            $at('2025-02-02 11:00'),
        );
        $double = new class ($canned) implements PrayerTimesCalculatorInterface {
            public function __construct(private readonly PrayerSchedule $canned) {}

            public function calculate(Coordinates $coordinates, CivilDate $date, CalculationParameters $parameters): PrayerSchedule
            {
                return $this->canned;
            }

            public function maghribAt(Coordinates $coordinates, CivilDate $date, CalculationParameters $parameters): \DateTimeImmutable
            {
                return $this->canned->maghrib;
            }
        };

        $month = (new TimetableService($double))->monthly(
            Coordinates::fromDegrees(3.1390, 101.6869),
            2025,
            2,
            CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters(),
        );

        self::assertCount(28, $month);

        foreach ($month as $schedule) {
            self::assertSame($canned, $schedule);
        }
    }
}
