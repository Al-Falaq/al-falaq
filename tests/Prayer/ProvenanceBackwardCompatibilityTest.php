<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Prayer;

use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Enums\PolarCircleRule;
use AlFalaq\AlFalaq\Prayer\Services\PrayerTimesCalculator;
use AlFalaq\AlFalaq\Prayer\Services\TimetableService;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;
use PHPUnit\Framework\TestCase;

/**
 * Release-gate proof that opt-in metadata never changes a legacy schedule.
 */
final class ProvenanceBackwardCompatibilityTest extends TestCase
{
    /**
     * Compact and metadata paths agree across methods, places, and a polar fallback.
     *
     * @return void
     */
    public function testCompactAndMetadataPathsAgreeOnRepresentativeGrid(): void
    {
        $places = [
            'Kuala Lumpur' => Coordinates::fromDegrees(3.1390, 101.6869),
            'Tromso' => Coordinates::fromDegrees(69.6496, 18.9553),
        ];
        $dates = [CivilDate::fromYmd(2025, 3, 1), CivilDate::fromYmd(2025, 11, 28)];
        $methods = [
            CalculationMethod::SINGAPORE,
            CalculationMethod::UMM_AL_QURA,
            CalculationMethod::MOON_SIGHTING_COMMITTEE,
        ];

        foreach ($methods as $method) {
            $parameters = $method->defaultParameters();
            $calculator = PrayerTimesCalculator::defaultFor($parameters);

            foreach ($places as $place => $coordinates) {
                foreach ($dates as $date) {
                    self::assertSame(
                        $calculator->calculate($coordinates, $date, $parameters)->toArray(),
                        $calculator->calculateWithMetadata($coordinates, $date, $parameters)->result->toArray(),
                        sprintf('Legacy and metadata results diverged for %s on %s.', $place, $date->isoformat()),
                    );
                }
            }
        }

        $polar = CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters()
            ->withPolarCircleRule(PolarCircleRule::NEAREST_LATITUDE);
        $tromsoSummer = CivilDate::fromYmd(2026, 6, 21);
        $calculator = PrayerTimesCalculator::defaultFor($polar);

        self::assertSame(
            $calculator->calculate($places['Tromso'], $tromsoSummer, $polar)->toArray(),
            $calculator->calculateWithMetadata($places['Tromso'], $tromsoSummer, $polar)->result->toArray(),
        );
    }

    /**
     * Monthly legacy and metadata paths return the same schedules in order.
     *
     * @return void
     */
    public function testMonthlyAndMonthlyWithMetadataAgreeInOrder(): void
    {
        $service = TimetableService::default();
        $coordinates = Coordinates::fromDegrees(3.1390, 101.6869);
        $parameters = CalculationMethod::SINGAPORE->defaultParameters();

        $legacy = $service->monthly($coordinates, 2025, 3, $parameters);
        $outcomes = $service->monthlyWithMetadata($coordinates, 2025, 3, $parameters);

        self::assertCount(31, $legacy);
        self::assertCount(count($legacy), $outcomes);

        foreach ($legacy as $index => $schedule) {
            self::assertSame($schedule->toArray(), $outcomes[$index]->result->toArray());
        }
    }
}
