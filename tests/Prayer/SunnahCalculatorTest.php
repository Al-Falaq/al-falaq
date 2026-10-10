<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Prayer;

use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Services\PrayerTimesCalculator;
use AlFalaq\AlFalaq\Prayer\Services\SunnahCalculator;
use AlFalaq\AlFalaq\Prayer\ValueObjects\SunnahTimes;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;
use PHPUnit\Framework\TestCase;

/**
 * Sunnah night-marker contract (implementation shipped in M1; M2 locks it).
 */
final class SunnahCalculatorTest extends TestCase
{
    /**
     * Compute one night via the shipped pipeline.
     *
     * @param float $lat Observer latitude.
     * @param float $lon Observer longitude.
     * @param int $y Civil year of tonight's maghrib.
     * @param int $m Civil month of tonight's maghrib.
     * @param int $d Civil day of tonight's maghrib.
     * @return SunnahTimes Markers in UTC.
     */
    private function nightFor(float $lat, float $lon, int $y, int $m, int $d): SunnahTimes
    {
        $calculator = PrayerTimesCalculator::default();
        $coords = Coordinates::fromDegrees($lat, $lon);
        $params = CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters();
        $date = CivilDate::fromYmd($y, $m, $d);

        return SunnahCalculator::fromCalculator($calculator, $coords, $date, $params)->calculate();
    }

    public function testMarkersAreOrderedAndMinuteAligned(): void
    {
        $night = $this->nightFor(3.1390, 101.6869, 2025, 1, 15);

        self::assertTrue($night->firstThird < $night->middleOfNight);
        self::assertTrue($night->middleOfNight < $night->lastThird);
        self::assertTrue($night->lastThird < $night->fajr);

        foreach ([$night->middleOfNight, $night->firstThird, $night->lastThird] as $marker) {
            self::assertSame('UTC', $marker->getTimezone()->getName());
            self::assertSame(0, (int) $marker->format('s'));
        }
    }

    public function testTahajjudWindowSpansLastThirdToFajr(): void
    {
        $night = $this->nightFor(35.7750, -78.6336, 2025, 1, 15);
        $window = $night->tahajjudWindow();

        self::assertSame($night->lastThird, $window['from']);
        self::assertSame($night->fajr, $window['to']);
    }

    public function testForZonePreservesInstantsAcrossDstSpringForward(): void
    {
        $night = $this->nightFor(40.7128, -74.0060, 2015, 3, 8);
        $display = $night->forZone(new \DateTimeZone('America/New_York'));

        self::assertSame($night->middleOfNight->getTimestamp(), $display->middleOfNight->getTimestamp());
        self::assertSame($night->firstThird->getTimestamp(), $display->firstThird->getTimestamp());
        self::assertSame($night->lastThird->getTimestamp(), $display->lastThird->getTimestamp());
        self::assertSame('America/New_York', $display->middleOfNight->getTimezone()->getName());
    }

    public function testDegenerateNightIsRejected(): void
    {
        $calculator = PrayerTimesCalculator::default();
        $schedule = $calculator->calculate(
            Coordinates::fromDegrees(3.1390, 101.6869),
            CivilDate::fromYmd(2025, 1, 15),
            CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters(),
        );

        $this->expectException(ValidationException::class);

        (new SunnahCalculator($schedule, $schedule))->calculate();
    }
}
