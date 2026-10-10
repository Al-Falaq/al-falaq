<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Prayer;

use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Enums\Prayer;
use AlFalaq\AlFalaq\Prayer\Services\PrayerTimesCalculator;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;
use AlFalaq\AlFalaq\Tests\Goldens\Comparator;
use PHPUnit\Framework\TestCase;

/**
 * Core pipeline pinned to two golden rows (full sweep lives in the harness).
 */
final class PrayerTimesCalculatorTest extends TestCase
{
    public function testKualaLumpurMwlMatchesGoldenRow(): void
    {
        $schedule = PrayerTimesCalculator::default()->calculate(
            Coordinates::fromDegrees(3.1390, 101.6869),
            CivilDate::fromYmd(2025, 1, 15),
            CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters(),
        );

        $expected = [
            'imsak' => '2025-01-14T22:00:00+00:00',
            'fajr' => '2025-01-14T22:10:00+00:00',
            'sunrise' => '2025-01-14T23:24:00+00:00',
            'syuruk' => '2025-01-14T23:24:00+00:00',
            'ishraq' => '2025-01-14T23:39:00+00:00',
            'dhuha' => '2025-01-14T23:52:00+00:00',
            'dhuhr' => '2025-01-15T05:24:00+00:00',
            'asr' => '2025-01-15T08:46:00+00:00',
            'maghrib' => '2025-01-15T11:21:00+00:00',
            'isha' => '2025-01-15T12:31:00+00:00',
        ];

        foreach ($expected as $marker => $minute) {
            self::assertTrue(
                Comparator::minutesEqual(
                    $schedule->timeFor(Prayer::from($marker)),
                    new \DateTimeImmutable($minute, new \DateTimeZone('UTC')),
                ),
                "marker {$marker}",
            );
        }
    }

    public function testNewYorkMwlJuneMatchesGoldenRow(): void
    {
        $schedule = PrayerTimesCalculator::default()->calculate(
            Coordinates::fromDegrees(40.7128, -74.0060),
            CivilDate::fromYmd(2025, 6, 21),
            CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters(),
        );

        $expected = [
            'imsak' => '2025-06-21T07:09:00+00:00',
            'fajr' => '2025-06-21T07:19:00+00:00',
            'sunrise' => '2025-06-21T09:25:00+00:00',
            'syuruk' => '2025-06-21T09:25:00+00:00',
            'ishraq' => '2025-06-21T09:40:00+00:00',
            'dhuha' => '2025-06-21T09:53:00+00:00',
            'dhuhr' => '2025-06-21T16:59:00+00:00',
            'asr' => '2025-06-21T20:58:00+00:00',
            'maghrib' => '2025-06-22T00:31:00+00:00',
            'isha' => '2025-06-22T02:28:00+00:00',
        ];

        foreach ($expected as $marker => $minute) {
            self::assertTrue(
                Comparator::minutesEqual(
                    $schedule->timeFor(Prayer::from($marker)),
                    new \DateTimeImmutable($minute, new \DateTimeZone('UTC')),
                ),
                "marker {$marker}",
            );
        }
    }
}
