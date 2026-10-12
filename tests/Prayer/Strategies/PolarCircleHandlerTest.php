<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Prayer\Strategies;

use AlFalaq\AlFalaq\Astro\Services\MeanSolarCalculator;
use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Enums\PolarCircleRule;
use AlFalaq\AlFalaq\Prayer\Enums\Prayer;
use AlFalaq\AlFalaq\Prayer\Services\PrayerTimesCalculator;
use AlFalaq\AlFalaq\Prayer\Strategies\MakkahHandler;
use AlFalaq\AlFalaq\Prayer\Strategies\NearestDayHandler;
use AlFalaq\AlFalaq\Prayer\Strategies\NearestLatitudeHandler;
use AlFalaq\AlFalaq\Prayer\Strategies\ThrowHandler;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\AstronomicalException;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;
use PHPUnit\Framework\TestCase;

/**
 * Polar-circle fallback strategies (pin _nearest_* verbatim).
 */
final class PolarCircleHandlerTest extends TestCase
{
    public function testNearestLatitudeMatchesPin(): void
    {
        $probe = fn(Coordinates $c, CivilDate $d): bool => (new MeanSolarCalculator())->isSolarDefined($c, $d, 0.0);

        $resolved = (new NearestLatitudeHandler())->resolve(
            Coordinates::fromDegrees(69.6496, 18.9553),
            CivilDate::fromYmd(2025, 6, 21),
            $probe,
        );

        self::assertEqualsWithDelta(65.22818986592176, $resolved['coordinates']->latitude, 1e-9);
        self::assertSame(18.9553, $resolved['coordinates']->longitude);
        self::assertTrue($resolved['date']->equals(CivilDate::fromYmd(2025, 6, 21)));
    }

    public function testNearestDayMatchesPin(): void
    {
        $probe = fn(Coordinates $c, CivilDate $d): bool => (new MeanSolarCalculator())->isSolarDefined($c, $d, 0.0);

        $resolved = (new NearestDayHandler())->resolve(
            Coordinates::fromDegrees(69.6496, 18.9553),
            CivilDate::fromYmd(2025, 12, 15),
            $probe,
        );

        self::assertTrue($resolved['date']->equals(CivilDate::fromYmd(2025, 11, 26)));
    }

    public function testMakkahSubstitutesPinConstant(): void
    {
        $probe = fn(Coordinates $c, CivilDate $d): bool => true;

        $resolved = (new MakkahHandler())->resolve(
            Coordinates::fromDegrees(69.6496, 18.9553),
            CivilDate::fromYmd(2025, 12, 15),
            $probe,
        );

        self::assertSame(21.4225241, $resolved['coordinates']->latitude);
        self::assertSame(39.8261818, $resolved['coordinates']->longitude);
    }

    public function testThrowEchoesInput(): void
    {
        $probe = fn(Coordinates $c, CivilDate $d): bool => false;
        $coords = Coordinates::fromDegrees(69.6496, 18.9553);
        $date = CivilDate::fromYmd(2025, 12, 15);

        $resolved = (new ThrowHandler())->resolve($coords, $date, $probe);

        self::assertTrue($resolved['coordinates']->equals($coords));
        self::assertTrue($resolved['date']->equals($date));
    }

    public function testPoleExhaustionThrows(): void
    {
        $probe = fn(Coordinates $c, CivilDate $d): bool => (new MeanSolarCalculator())->isSolarDefined($c, $d, 0.0);

        $this->expectException(AstronomicalException::class);
        (new NearestDayHandler())->resolve(
            Coordinates::fromDegrees(90.0, 0.0),
            CivilDate::fromYmd(2025, 6, 21),
            $probe,
        );
    }

    public function testBoundaryValidationFailureSkipsCandidate(): void
    {
        // At the 1900 floor, the -1 probes fall outside the civil span and
        // are skipped via the ValidationException continue; an always-false
        // probe then exhausts the year.
        $probe = fn(Coordinates $c, CivilDate $d): bool => false;

        $this->expectException(AstronomicalException::class);
        (new NearestDayHandler())->resolve(
            Coordinates::fromDegrees(3.1390, 101.6869),
            CivilDate::fromYmd(1900, 1, 1),
            $probe,
        );
    }

    public function testCalculatorTromsoeSummerMatchesGoldenRow(): void
    {
        // Oracle row: pin MWL, Tromsø 2025-06-21 (also in prayer_muslim_world_league.csv).
        $schedule = PrayerTimesCalculator::default()->calculate(
            Coordinates::fromDegrees(69.6496, 18.9553),
            CivilDate::fromYmd(2025, 6, 21),
            CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters(),
        );

        $expected = [
            'imsak' => '2025-06-20 22:36', 'fajr' => '2025-06-20 22:46',
            'sunrise' => '2025-06-20 23:35', 'syuruk' => '2025-06-20 23:35',
            'ishraq' => '2025-06-20 23:50', 'dhuha' => '2025-06-21 00:03',
            'dhuhr' => '2025-06-21 10:47', 'asr' => '2025-06-21 15:42',
            'maghrib' => '2025-06-21 21:57', 'isha' => '2025-06-21 22:46',
        ];

        foreach ($expected as $marker => $minute) {
            self::assertSame(
                $minute,
                $schedule->timeFor(Prayer::from($marker))->format('Y-m-d H:i'),
                "marker {$marker}",
            );
        }
    }

    public function testDefaultForCoversAllPolarHandlers(): void
    {
        $base = CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters();

        $day = PrayerTimesCalculator::defaultFor($base->withPolarCircleRule(PolarCircleRule::NEAREST_DAY));
        self::assertInstanceOf(NearestDayHandler::class, $day->polarCircleHandler());

        $makkah = PrayerTimesCalculator::defaultFor($base->withPolarCircleRule(PolarCircleRule::MAKKAH));
        self::assertInstanceOf(MakkahHandler::class, $makkah->polarCircleHandler());

        $none = PrayerTimesCalculator::defaultFor($base->withPolarCircleRule(PolarCircleRule::NONE));
        self::assertInstanceOf(ThrowHandler::class, $none->polarCircleHandler());
    }

    public function testNearestDayFindsOffsetOne(): void
    {
        // The scan starts at offset 1, not 2 (mutation gate).
        $probe = static fn(Coordinates $c, CivilDate $d): bool => $d->isoformat() === '2025-05-31';

        $resolved = (new NearestDayHandler())->resolve(
            Coordinates::fromDegrees(3.1390, 101.6869),
            CivilDate::fromYmd(2025, 6, 1),
            $probe,
        );

        self::assertSame(['coordinates', 'date'], array_keys($resolved));
        self::assertTrue($resolved['date']->equals(CivilDate::fromYmd(2025, 5, 31)));
        self::assertTrue($resolved['coordinates']->equals(Coordinates::fromDegrees(3.1390, 101.6869)));
    }

    public function testNearestDayReachesOffset366(): void
    {
        // The scan is inclusive at 366: 2025-06-01 minus 366 days is
        // 2024-05-31 (mutation gate).
        $probe = static fn(Coordinates $c, CivilDate $d): bool => $d->isoformat() === '2024-05-31';

        $resolved = (new NearestDayHandler())->resolve(
            Coordinates::fromDegrees(3.1390, 101.6869),
            CivilDate::fromYmd(2025, 6, 1),
            $probe,
        );

        self::assertTrue($resolved['date']->equals(CivilDate::fromYmd(2024, 5, 31)));
    }

    public function testNearestDaySkipsOnlyTheFailedCandidate(): void
    {
        // A validation failure skips that candidate (continue), it does not
        // abort the whole offset (break): with only 1900-01-02 solvable, the
        // -1 probe fails but the +1 probe must still be tried.
        $probe = static fn(Coordinates $c, CivilDate $d): bool => $d->isoformat() === '1900-01-02';

        $resolved = (new NearestDayHandler())->resolve(
            Coordinates::fromDegrees(3.1390, 101.6869),
            CivilDate::fromYmd(1900, 1, 1),
            $probe,
        );

        self::assertTrue($resolved['date']->equals(CivilDate::fromYmd(1900, 1, 2)));
    }

    public function testNearestDayExhaustionMessageIsExact(): void
    {
        // Every message operand and its order are pinned (mutation gate).
        $probe = static fn(Coordinates $c, CivilDate $d): bool => false;

        try {
            (new NearestDayHandler())->resolve(
                Coordinates::fromDegrees(3.1390, 101.6869),
                CivilDate::fromYmd(2025, 1, 15),
                $probe,
            );

            self::fail('expected AstronomicalException for exhausted scan.');
        } catch (AstronomicalException $e) {
            self::assertSame(
                'No date with sunrise/sunset found within a year. '
                . "At the exact geographic poles (|latitude| == 90) the sun's "
                . 'hour-angle is singular and no such date exists numerically; '
                . 'use PolarCircleRule.NEAREST_LATITUDE or MAKKAH instead.',
                $e->getMessage(),
            );
        }
    }

    public function testNearestLatitudeEquatorKeepsPositiveZero(): void
    {
        // Latitude exactly 0.0 takes the northern sign (mutation gate).
        $probe = static fn(Coordinates $c, CivilDate $d): bool => true;

        $resolved = (new NearestLatitudeHandler())->resolve(
            Coordinates::fromDegrees(0.0, 18.9553),
            CivilDate::fromYmd(2025, 6, 21),
            $probe,
        );

        self::assertSame(0.0, $resolved['coordinates']->latitude);
        self::assertSame(INF, fdiv(1.0, $resolved['coordinates']->latitude));
    }

    public function testNearestLatitudeSharpBoundaryIsExact(): void
    {
        // Synthetic solvable boundary at |lat| <= 65: bisection from 0 (not
        // 1.0) lands bit-exactly on 64.5 after the 0.5 back-off, both poles.
        $probe = static fn(Coordinates $c, CivilDate $d): bool => abs($c->latitude) <= 65.0;

        $north = (new NearestLatitudeHandler())->resolve(
            Coordinates::fromDegrees(69.6496, 18.9553),
            CivilDate::fromYmd(2025, 6, 21),
            $probe,
        );

        self::assertSame(64.5, $north['coordinates']->latitude);
        self::assertSame(18.9553, $north['coordinates']->longitude);

        $south = (new NearestLatitudeHandler())->resolve(
            Coordinates::fromDegrees(-69.6496, 18.9553),
            CivilDate::fromYmd(2025, 6, 21),
            $probe,
        );

        self::assertSame(-64.5, $south['coordinates']->latitude);
    }

    public function testNearestLatitudeStartsFromEquator(): void
    {
        // The bisection seeds valid at 0.0 (the equator, always solvable),
        // never at an unchecked 1.0: with a probe solvable only exactly at
        // the equator, the original returns 0.0 while a 1.0 seed leaks
        // through the back-off as 0.5.
        $probe = static fn(Coordinates $c, CivilDate $d): bool => $c->latitude === 0.0;

        $resolved = (new NearestLatitudeHandler())->resolve(
            Coordinates::fromDegrees(69.6496, 18.9553),
            CivilDate::fromYmd(2025, 6, 21),
            $probe,
        );

        self::assertSame(0.0, $resolved['coordinates']->latitude);
    }

    public function testNearestLatitudeRunsSixtyIterations(): void
    {
        // Exactly 60 probes: one fewer or one more changes the answer.
        $calls = 0;
        $probe = static function (Coordinates $c, CivilDate $d) use (&$calls): bool {
            $calls++;

            return false;
        };

        (new NearestLatitudeHandler())->resolve(
            Coordinates::fromDegrees(69.6496, 18.9553),
            CivilDate::fromYmd(2025, 6, 21),
            $probe,
        );

        self::assertSame(60, $calls);
    }

    public function testNearestLatitudeBackoffFloorIsZero(): void
    {
        // max(valid - 0.5, 0.0): the floor is 0.0, not 1.0 (mutation gate).
        $probe = static fn(Coordinates $c, CivilDate $d): bool => abs($c->latitude) <= 1.0;

        $resolved = (new NearestLatitudeHandler())->resolve(
            Coordinates::fromDegrees(69.6496, 18.9553),
            CivilDate::fromYmd(2025, 6, 21),
            $probe,
        );

        self::assertSame(0.5, $resolved['coordinates']->latitude);
    }
}
