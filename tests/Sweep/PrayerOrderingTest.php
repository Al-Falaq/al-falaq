<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Sweep;

use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Services\PrayerTimesCalculator;
use AlFalaq\AlFalaq\Prayer\ValueObjects\PrayerAdjustments;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Ordering invariant over real schedules: with EMPTY adjustments,
 * fajr ≤ sunrise ≤ dhuhr ≤ asr ≤ maghrib ≤ isha (non-polar latitudes).
 *
 * Seeded (mt_srand inline per repo convention). Latitudes capped to
 * ±65° to exclude polar-undefined days (polar behavior is T17's).
 */
#[Group('sweep')]
final class PrayerOrderingTest extends TestCase
{
    public function testOrderingHoldsWithEmptyAdjustments(): void
    {
        mt_srand(1234);

        $methods = [
            CalculationMethod::MUSLIM_WORLD_LEAGUE,
            CalculationMethod::EGYPTIAN,
            CalculationMethod::KARACHI,
            CalculationMethod::UMM_AL_QURA,
            CalculationMethod::DUBAI,
            CalculationMethod::MOON_SIGHTING_COMMITTEE,
            CalculationMethod::TURKEY,
            CalculationMethod::TEHRAN,
            CalculationMethod::KUWAIT,
            CalculationMethod::QATAR,
            CalculationMethod::SINGAPORE,
            CalculationMethod::NORTH_AMERICA,
            CalculationMethod::MOROCCO,
            CalculationMethod::JAKIM,
            CalculationMethod::KEMENAG,
            CalculationMethod::MUIS,
            CalculationMethod::BRUNEI,
            CalculationMethod::UOIF,
        ];

        for ($i = 0; $i < 200; $i++) {
            $method = $methods[mt_rand(0, count($methods) - 1)];
            $params = $method->defaultParameters();
            // Empty adjustments: replaces any caller adjustments (method layer stays).
            $params = $params->withAdjustments(PrayerAdjustments::empty());

            $lat = mt_rand(-6500, 6500) / 100.0;
            $lon = mt_rand(-18000, 18000) / 100.0;
            $date = CivilDate::fromYmd(mt_rand(2020, 2030), mt_rand(1, 12), mt_rand(1, 28))
                ->plusDays(mt_rand(0, 30));

            $schedule = PrayerTimesCalculator::default()->calculate(
                Coordinates::fromDegrees($lat, $lon),
                $date,
                $params,
            );

            $t = static fn(\DateTimeImmutable $dt): int => $dt->getTimestamp();
            $ctx = "{$method->value} {$date->isoformat()} {$lat},{$lon}";

            self::assertTrue($t($schedule->fajr) <= $t($schedule->sunrise), "fajr after sunrise: {$ctx}");
            self::assertTrue($t($schedule->sunrise) <= $t($schedule->dhuhr), "sunrise after dhuhr: {$ctx}");
            self::assertTrue($t($schedule->dhuhr) <= $t($schedule->asr), "dhuhr after asr: {$ctx}");
            self::assertTrue($t($schedule->asr) <= $t($schedule->maghrib), "asr after maghrib: {$ctx}");
            self::assertTrue($t($schedule->maghrib) <= $t($schedule->isha), "maghrib after isha: {$ctx}");
        }
    }
}
