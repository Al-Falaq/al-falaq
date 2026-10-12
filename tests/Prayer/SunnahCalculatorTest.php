<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Prayer;

use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Services\PrayerTimesCalculator;
use AlFalaq\AlFalaq\Prayer\Services\SunnahCalculator;
use AlFalaq\AlFalaq\Prayer\ValueObjects\PrayerSchedule;
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

    /**
     * Pinned half-up rounding (KL 2025-01-15 MWL: night 39000 s from
     * maghrib 11:21 to fajr 22:11 UTC). firstThird raw offset is 13000 s
     * (+3h36m40s → 14:57:40, seconds ≥ 30 so half-up gives 14:58, while
     * truncation would give 14:57); lastThird raw offset is 26000 s
     * (+7h13m20s → 18:34:20, seconds < 30 so it stays 18:34, while
     * always-round-up would give 18:35).
     */
    public function testMarkersApplyHalfUpRoundingBothSidesOfBoundary(): void
    {
        $night = $this->nightFor(3.1390, 101.6869, 2025, 1, 15);

        self::assertSame('2025-01-15T16:46:00+00:00', $night->middleOfNight->format('Y-m-d\TH:i:sP'));
        self::assertSame('2025-01-15T14:58:00+00:00', $night->firstThird->format('Y-m-d\TH:i:sP'));
        self::assertSame('2025-01-15T18:34:00+00:00', $night->lastThird->format('Y-m-d\TH:i:sP'));
    }

    public function testForZonePreservesInstantsAcrossDstSpringForward(): void
    {
        $night = $this->nightFor(40.7128, -74.0060, 2015, 3, 8);
        $display = $night->forZone(new \DateTimeZone('America/New_York'));

        self::assertSame($night->middleOfNight->getTimestamp(), $display->middleOfNight->getTimestamp());
        self::assertSame($night->firstThird->getTimestamp(), $display->firstThird->getTimestamp());
        self::assertSame($night->lastThird->getTimestamp(), $display->lastThird->getTimestamp());
        self::assertSame($night->fajr->getTimestamp(), $display->fajr->getTimestamp());
        self::assertSame($night->maghrib->getTimestamp(), $display->maghrib->getTimestamp());

        foreach ([$display->middleOfNight, $display->firstThird, $display->lastThird, $display->fajr, $display->maghrib] as $marker) {
            self::assertSame('America/New_York', $marker->getTimezone()->getName());
        }
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

    public function testDirectDegenerateValueConstructionIsRejected(): void
    {
        $anchor = new \DateTimeImmutable('2025-01-15 12:00:00', new \DateTimeZone('UTC'));

        $this->expectException(ValidationException::class);

        new SunnahTimes($anchor, $anchor, $anchor, $anchor, $anchor);
    }

    public function testDirectDegenerateMaghribAnchorIsRejected(): void
    {
        $utc = new \DateTimeZone('UTC');
        $middle = new \DateTimeImmutable('2025-01-15 12:00:00', $utc);
        $first = new \DateTimeImmutable('2025-01-15 10:00:00', $utc);
        $last = new \DateTimeImmutable('2025-01-15 14:00:00', $utc);
        $fajr = new \DateTimeImmutable('2025-01-15 15:00:00', $utc);
        $maghrib = new \DateTimeImmutable('2025-01-15 15:00:00', $utc);

        $this->expectException(ValidationException::class);

        new SunnahTimes($middle, $first, $last, $fajr, $maghrib);
    }

    public function testDirectLastThirdAfterFajrIsRejected(): void
    {
        $utc = new \DateTimeZone('UTC');
        $maghrib = new \DateTimeImmutable('2025-01-15 22:00:00', $utc);
        $middle = new \DateTimeImmutable('2025-01-16 00:00:00', $utc);
        $first = new \DateTimeImmutable('2025-01-15 23:30:00', $utc);
        $last = new \DateTimeImmutable('2025-01-16 03:00:00', $utc);
        $fajr = new \DateTimeImmutable('2025-01-16 02:00:00', $utc);

        $this->expectException(ValidationException::class);

        new SunnahTimes($middle, $first, $last, $fajr, $maghrib);
    }

    public function testNightFractionThirdsEquality(): void
    {
        $night = $this->nightFor(3.1390, 101.6869, 2025, 1, 15);

        self::assertSame(
            '2025-01-15T16:46:00+00:00',
            $night->nightFraction(0.5)->format('Y-m-d\TH:i:sP'),
        );
        self::assertSame(
            '2025-01-15T14:58:00+00:00',
            $night->nightFraction(1.0 / 3.0)->format('Y-m-d\TH:i:sP'),
        );
        self::assertSame(
            '2025-01-15T18:34:00+00:00',
            $night->nightFraction(2.0 / 3.0)->format('Y-m-d\TH:i:sP'),
        );
        self::assertSame($night->middleOfNight->getTimestamp(), $night->nightFraction(0.5)->getTimestamp());
        self::assertSame($night->firstThird->getTimestamp(), $night->nightFraction(1.0 / 3.0)->getTimestamp());
        self::assertSame($night->lastThird->getTimestamp(), $night->nightFraction(2.0 / 3.0)->getTimestamp());
    }

    public function testNightFractionRejectsInvalid(): void
    {
        $night = $this->nightFor(3.1390, 101.6869, 2025, 1, 15);

        foreach ([0.0, 1.0, -0.5, 1.5, NAN, INF, -INF] as $fraction) {
            try {
                $night->nightFraction($fraction);

                self::fail(sprintf('nightFraction(%s) must throw ValidationException.', var_export($fraction, true)));
            } catch (ValidationException $e) {
                self::assertInstanceOf(ValidationException::class, $e);
            }
        }
    }

    public function testNightFractionStaysStrictlyInteriorAfterRounding(): void
    {
        // Short summer night (67N 2024-06-21 MWL: 2940 s): raw offsets of a
        // few seconds used to minute-round exactly onto an anchor.
        $night = $this->nightFor(67.0, 10.0, 2024, 6, 21);

        foreach ([0.001, 0.01, 0.5, 0.99, 0.999] as $fraction) {
            $out = $night->nightFraction($fraction);

            self::assertGreaterThan($night->maghrib, $out);
            self::assertLessThan($night->fajr, $out);
            self::assertSame(0, (int) $out->format('s'));
        }

        self::assertSame('2024-06-21T22:34:00+00:00', $night->nightFraction(0.001)->format('Y-m-d\TH:i:sP'));
        self::assertSame('2024-06-21T23:21:00+00:00', $night->nightFraction(0.999)->format('Y-m-d\TH:i:sP'));
    }

    public function testForZonePreservesAllFiveInstants(): void
    {
        $night = $this->nightFor(3.1390, 101.6869, 2025, 1, 15);
        $display = $night->forZone(new \DateTimeZone('Asia/Kuala_Lumpur'));

        foreach (
            [
                [$night->middleOfNight, $display->middleOfNight],
                [$night->firstThird, $display->firstThird],
                [$night->lastThird, $display->lastThird],
                [$night->fajr, $display->fajr],
                [$night->maghrib, $display->maghrib],
            ] as [$utc, $zoned]
        ) {
            self::assertSame($utc->getTimestamp(), $zoned->getTimestamp());
        }

        foreach ([$display->middleOfNight, $display->firstThird, $display->lastThird, $display->fajr, $display->maghrib] as $marker) {
            self::assertSame('Asia/Kuala_Lumpur', $marker->getTimezone()->getName());
        }
    }

    public function testForZoneThenNightFractionCommutates(): void
    {
        $night = $this->nightFor(3.1390, 101.6869, 2025, 1, 15);
        $zone = new \DateTimeZone('America/New_York');
        $display = $night->forZone($zone);

        foreach ([0.5, 1.0 / 3.0, 2.0 / 3.0, 0.1, 0.9] as $fraction) {
            $viaDisplay = $display->nightFraction($fraction);
            $viaUtc = $night->nightFraction($fraction)->setTimezone($zone);

            self::assertSame(
                $viaUtc->getTimestamp(),
                $viaDisplay->getTimestamp(),
                sprintf('Commutativity failed for fraction %s.', (string) $fraction),
            );
            self::assertSame($zone->getName(), $viaDisplay->getTimezone()->getName());
        }
    }

    /**
     * Schedules bracketing a fixed-length night (maghrib M, fajr M + N s).
     *
     * @param int $nightSeconds Length of the synthetic night.
     * @return SunnahTimes Markers for the synthetic night.
     */
    private function straddleNight(int $nightSeconds): SunnahTimes
    {
        $utc = new \DateTimeZone('UTC');
        $maghrib = new \DateTimeImmutable('2025-01-15 10:00:00', $utc);
        $fajr = $maghrib->modify(sprintf('+%d seconds', $nightSeconds));
        $at = static fn(string $time): \DateTimeImmutable => new \DateTimeImmutable($time, $utc);

        $today = new PrayerSchedule(
            $at('2025-01-15 00:01:00'),
            $maghrib->modify('+3600 seconds'),
            $at('2025-01-15 06:00:00'),
            $at('2025-01-15 06:00:00'),
            $at('2025-01-15 06:15:00'),
            $at('2025-01-15 06:28:00'),
            $at('2025-01-15 12:00:00'),
            $at('2025-01-15 15:00:00'),
            $maghrib,
            $at('2025-01-15 20:00:00'),
        );
        $tomorrow = new PrayerSchedule(
            $at('2025-01-15 00:01:00'),
            $fajr,
            $at('2025-01-15 06:00:00'),
            $at('2025-01-15 06:00:00'),
            $at('2025-01-15 06:15:00'),
            $at('2025-01-15 06:28:00'),
            $at('2025-01-15 12:00:00'),
            $at('2025-01-15 15:00:00'),
            $maghrib->modify('+7200 seconds'),
            $at('2025-01-15 20:00:00'),
        );

        return (new SunnahCalculator($today, $tomorrow))->calculate();
    }

    public function testThirdsUseHalfUpRoundingAtTheMinuteBoundary(): void
    {
        // 36088 s nights: firstThird raw 12029.33 s rounds to :29 (stays);
        // ceil would give :30 (rounds up). 36089 s: raw 12029.67 s rounds to
        // :30 (rounds up); floor would give :29 (stays). Each pin kills one
        // RoundingFamily mutant.
        self::assertSame(
            '13:20:00',
            $this->straddleNight(36088)->firstThird->format('H:i:s'),
        );
        self::assertSame(
            '13:21:00',
            $this->straddleNight(36089)->firstThird->format('H:i:s'),
        );
    }

    public function testEqualInstantNightIsRejected(): void
    {
        // fajr == maghrib (not just before) still throws. Note: the guard
        // here is defense-in-depth with SunnahTimes itself, which re-checks
        // the same condition with the same exception — so this pins the
        // boundary behavior rather than a single line.
        $utc = new \DateTimeZone('UTC');
        $anchor = new \DateTimeImmutable('2025-01-15 10:00:00', $utc);
        $at = static fn(string $time): \DateTimeImmutable => new \DateTimeImmutable($time, $utc);
        $mk = static function (\DateTimeImmutable $maghrib, \DateTimeImmutable $fajr) use ($at): PrayerSchedule {
            return new PrayerSchedule(
                $at('2025-01-15 00:01:00'),
                $fajr,
                $at('2025-01-15 06:00:00'),
                $at('2025-01-15 06:00:00'),
                $at('2025-01-15 06:15:00'),
                $at('2025-01-15 06:28:00'),
                $at('2025-01-15 12:00:00'),
                $at('2025-01-15 15:00:00'),
                $maghrib,
                $at('2025-01-15 20:00:00'),
            );
        };

        $this->expectException(ValidationException::class);
        (new SunnahCalculator($mk($anchor, $at('2025-01-15 06:00:00')), $mk($at('2025-01-15 06:00:00'), $anchor)))->calculate();
    }

    public function testNightFractionHalfSecondBoundaries(): void
    {
        // A 10-hour night: raw 18029.5 s rounds half-up to :30 (up to 05:01)
        // while floor stays at :29 (05:00); raw 18029.3 s rounds to :29
        // (05:00) while ceil reaches :30 (05:01). Each pin kills one
        // RoundingFamily mutant.
        $utc = new \DateTimeZone('UTC');
        $night = new SunnahTimes(
            new \DateTimeImmutable('2025-01-15 05:00:00', $utc),
            new \DateTimeImmutable('2025-01-15 03:00:00', $utc),
            new \DateTimeImmutable('2025-01-15 07:00:00', $utc),
            new \DateTimeImmutable('2025-01-15 10:00:00', $utc),
            new \DateTimeImmutable('2025-01-15 00:00:00', $utc),
        );

        self::assertSame(
            '2025-01-15 05:01:00',
            $night->nightFraction(18029.5 / 36000)->format('Y-m-d H:i:s'),
        );
        self::assertSame(
            '2025-01-15 05:00:00',
            $night->nightFraction(18029.3 / 36000)->format('Y-m-d H:i:s'),
        );
    }

    public function testLastThirdEqualToFajrIsRejected(): void
    {
        // A one-second night puts lastThird exactly on fajr (mutation gate).
        $utc = new \DateTimeZone('UTC');
        $maghrib = new \DateTimeImmutable('2025-01-15 10:00:00', $utc);
        $fajr = new \DateTimeImmutable('2025-01-15 10:00:01', $utc);

        $this->expectException(ValidationException::class);
        new SunnahTimes($maghrib, $maghrib, $fajr, $fajr, $maghrib);
    }
}
