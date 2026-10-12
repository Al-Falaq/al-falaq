<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Prayer;

use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Enums\Prayer;
use AlFalaq\AlFalaq\Prayer\Services\PrayerTimesCalculator;
use AlFalaq\AlFalaq\Prayer\ValueObjects\DisplaySchedule;
use AlFalaq\AlFalaq\Prayer\ValueObjects\PrayerSchedule;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;
use PHPUnit\Framework\TestCase;

/**
 * UTC schedule value object plus its display-zone view.
 */
final class PrayerScheduleTest extends TestCase
{
    /**
     * One real KL/MWL schedule (UTC).
     *
     * @return PrayerSchedule Schedule for 2025-01-15.
     */
    private static function klSchedule(): PrayerSchedule
    {
        return PrayerTimesCalculator::default()->calculate(
            Coordinates::fromDegrees(3.1390, 101.6869),
            CivilDate::fromYmd(2025, 1, 15),
            CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters(),
        );
    }

    public function testNonUtcInputIsRejected(): void
    {
        $utc = self::klSchedule();
        $ny = new \DateTimeZone('America/New_York');
        $shifted = $utc->fajr->setTimezone($ny);

        $this->expectException(ValidationException::class);

        new PrayerSchedule(
            $utc->imsak,
            $shifted,
            $utc->sunrise,
            $utc->syuruk,
            $utc->ishraq,
            $utc->dhuha,
            $utc->dhuhr,
            $utc->asr,
            $utc->maghrib,
            $utc->isha,
        );
    }

    public function testToArrayReturnsIsoStringsInScheduleOrder(): void
    {
        $row = self::klSchedule()->toArray();

        self::assertSame(
            ['imsak', 'fajr', 'sunrise', 'syuruk', 'ishraq', 'dhuha', 'dhuhr', 'asr', 'maghrib', 'isha'],
            array_keys($row),
        );
        self::assertSame('2025-01-14T22:10:00+00:00', $row['fajr']);
        self::assertSame('2025-01-15T11:21:00+00:00', $row['maghrib']);
    }

    public function testForZoneReturnsDisplayView(): void
    {
        $view = self::klSchedule()->forZone(new \DateTimeZone('Asia/Kuala_Lumpur'));

        self::assertInstanceOf(DisplaySchedule::class, $view);
        self::assertSame('Asia/Kuala_Lumpur', $view->zone->getName());
    }

    public function testDisplayTimeForConvertsOneInstant(): void
    {
        $schedule = self::klSchedule();
        $view = $schedule->forZone(new \DateTimeZone('Asia/Kuala_Lumpur'));

        $expected = $schedule->timeFor(Prayer::DHUHR)->setTimezone(new \DateTimeZone('Asia/Kuala_Lumpur'));

        self::assertSame(
            $expected->format('Y-m-d\TH:i:sP'),
            $view->timeFor(Prayer::DHUHR)->format('Y-m-d\TH:i:sP'),
        );
        self::assertSame('2025-01-15T13:24:00+08:00', $view->timeFor(Prayer::DHUHR)->format('Y-m-d\TH:i:sP'));
    }

    public function testDisplayAllConvertsEveryInstant(): void
    {
        $schedule = self::klSchedule();
        $all = $schedule->forZone(new \DateTimeZone('Asia/Kuala_Lumpur'))->all();

        self::assertCount(10, $all);

        foreach ($schedule->all() as $key => $instant) {
            self::assertSame(
                $instant->setTimezone(new \DateTimeZone('Asia/Kuala_Lumpur'))->getTimestamp(),
                $all[$key]->getTimestamp(),
                $key,
            );
            self::assertSame('Asia/Kuala_Lumpur', $all[$key]->getTimezone()->getName(), $key);
        }
    }

    public function testDisplayToArrayFormatsInZone(): void
    {
        $row = self::klSchedule()->forZone(new \DateTimeZone('Asia/Kuala_Lumpur'))->toArray();

        self::assertSame(
            ['imsak', 'fajr', 'sunrise', 'syuruk', 'ishraq', 'dhuha', 'dhuhr', 'asr', 'maghrib', 'isha'],
            array_keys($row),
        );
        self::assertSame('2025-01-15T06:10:00+08:00', $row['fajr']);
    }
}
