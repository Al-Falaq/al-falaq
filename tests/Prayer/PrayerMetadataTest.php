<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Prayer;

use AlFalaq\AlFalaq\Astro\Contracts\SolarDayEngine;
use AlFalaq\AlFalaq\Astro\ValueObjects\SolarTime;
use AlFalaq\AlFalaq\Prayer\Contracts\IshaRule;
use AlFalaq\AlFalaq\Prayer\Contracts\PrayerMetadataCalculatorInterface;
use AlFalaq\AlFalaq\Prayer\Contracts\PrayerTimesCalculatorInterface;
use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Enums\HighLatitudeRule;
use AlFalaq\AlFalaq\Prayer\Enums\Madhab;
use AlFalaq\AlFalaq\Prayer\Enums\PolarCircleRule;
use AlFalaq\AlFalaq\Prayer\Enums\Prayer;
use AlFalaq\AlFalaq\Prayer\Exceptions\UndefinedScheduleException;
use AlFalaq\AlFalaq\Prayer\Services\PrayerTimesCalculator;
use AlFalaq\AlFalaq\Prayer\Strategies\MiddleOfNightCorrection;
use AlFalaq\AlFalaq\Prayer\Strategies\MinutesAfterSunsetMaghrib;
use AlFalaq\AlFalaq\Prayer\Strategies\NearestLatitudeHandler;
use AlFalaq\AlFalaq\Prayer\Strategies\SeasonAdjustedTwilight;
use AlFalaq\AlFalaq\Prayer\ValueObjects\CalculationParameters;
use AlFalaq\AlFalaq\Prayer\ValueObjects\PrayerAdjustments;
use AlFalaq\AlFalaq\Prayer\ValueObjects\PrayerCalculationMetadata;
use AlFalaq\AlFalaq\Prayer\ValueObjects\PrayerCalculationOutcome;
use AlFalaq\AlFalaq\Prayer\ValueObjects\PrayerSchedule;
use AlFalaq\AlFalaq\SharedKernel\Contracts\IdentifiesCalculationSource;
use AlFalaq\AlFalaq\SharedKernel\Enums\CalculationContext;
use AlFalaq\AlFalaq\SharedKernel\Enums\WarningCode;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\InvalidCoordinatesException;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CalculationMetadata;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CalculationSource;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\LibraryVersion;
use PHPUnit\Framework\TestCase;

/**
 * Prayer metadata and outcome envelopes: mixed-in common block, inputs, policy.
 */
final class PrayerMetadataTest extends TestCase
{
    private static function coordinates(): Coordinates
    {
        return Coordinates::fromDegrees(3.1390, 101.6869);
    }

    private static function date(): CivilDate
    {
        return CivilDate::fromYmd(2025, 3, 1);
    }

    private static function parameters(): CalculationParameters
    {
        return CalculationMethod::SINGAPORE->defaultParameters();
    }

    private static function metadata(?PolarCircleRule $fallbackRule = null): PrayerCalculationMetadata
    {
        return new PrayerCalculationMetadata(
            CalculationMetadata::create(CalculationContext::PRAYER),
            self::coordinates(),
            self::date(),
            self::coordinates(),
            self::date(),
            self::parameters(),
            $fallbackRule,
        );
    }

    public function testMetadataToArrayWrapsCommonAndInputs(): void
    {
        $array = self::metadata()->toArray();

        self::assertSame([
            'schema_version',
            'context',
            'status',
            'library_version',
            'warnings',
            'sources',
            'input',
            'effective_input',
            'policy',
            'fallback',
            'rounding',
        ], array_keys($array));

        self::assertSame([
            'coordinates' => ['latitude' => 3.1390, 'longitude' => 101.6869],
            'date' => '2025-03-01',
        ], $array['input']);
        self::assertSame($array['input'], $array['effective_input']);
        self::assertSame(self::parameters()->toArray(), $array['policy']);
        self::assertSame(['applied' => false, 'kind' => null, 'rule' => null], $array['fallback']);
        self::assertSame('half_up_to_minute_utc', $array['rounding']);
    }

    public function testMetadataToArrayReportsAPolarFallback(): void
    {
        $fallback = self::metadata(PolarCircleRule::NEAREST_LATITUDE)->toArray()['fallback'];

        self::assertSame([
            'applied' => true,
            'kind' => 'polar',
            'rule' => 'nearest_latitude',
        ], $fallback);
    }

    public function testOutcomeWrapsResultAndMetadata(): void
    {
        $at = static fn(string $time): \DateTimeImmutable => new \DateTimeImmutable($time, new \DateTimeZone('UTC'));
        $schedule = new PrayerSchedule(
            $at('2025-03-01 05:00'),
            $at('2025-03-01 05:10'),
            $at('2025-03-01 06:20'),
            $at('2025-03-01 06:20'),
            $at('2025-03-01 06:35'),
            $at('2025-03-01 06:48'),
            $at('2025-03-01 12:26'),
            $at('2025-03-01 15:39'),
            $at('2025-03-01 18:28'),
            $at('2025-03-01 19:38'),
        );
        $outcome = new PrayerCalculationOutcome($schedule, self::metadata());

        $array = $outcome->toArray();

        self::assertSame(['result', 'metadata'], array_keys($array));
        self::assertSame($schedule->toArray(), $array['result']);
        self::assertSame(self::metadata()->toArray(), $array['metadata']);
    }

    public function testMetadataInterfaceExtendsTheCalculatorContract(): void
    {
        $reflection = new \ReflectionClass(PrayerMetadataCalculatorInterface::class);

        self::assertContains(
            PrayerTimesCalculatorInterface::class,
            $reflection->getInterfaceNames(),
        );

        $method = $reflection->getMethod('calculateWithMetadata');

        self::assertSame(PrayerCalculationOutcome::class, (string) $method->getReturnType());
    }

    public function testLegacyResultEqualsMetadataResult(): void
    {
        $places = [
            'Kuala Lumpur' => Coordinates::fromDegrees(3.1390, 101.6869),
            'Tromsø' => Coordinates::fromDegrees(69.6496, 18.9553),
        ];
        $dates = [CivilDate::fromYmd(2025, 3, 1), CivilDate::fromYmd(2025, 11, 28)];

        foreach ([
            CalculationMethod::SINGAPORE,
            CalculationMethod::UMM_AL_QURA,
            CalculationMethod::MOON_SIGHTING_COMMITTEE,
        ] as $method) {
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
    }

    public function testCalculateWithMetadataAcceptsACoordinatePair(): void
    {
        $calculator = PrayerTimesCalculator::default();
        $parameters = CalculationMethod::SINGAPORE->defaultParameters();

        $pair = $calculator->calculateWithMetadata(
            [3.1390, 101.6869],
            self::date(),
            $parameters,
        );
        $valueObject = $calculator->calculateWithMetadata(self::coordinates(), self::date(), $parameters);

        self::assertSame($valueObject->result->toArray(), $pair->result->toArray());
        self::assertSame($valueObject->metadata->toArray(), $pair->metadata->toArray());
    }

    public function testLegacyNoneRuleExceptionIsUnchanged(): void
    {
        $calculator = PrayerTimesCalculator::default();
        $parameters = CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters()
            ->withPolarCircleRule(PolarCircleRule::NONE);
        $tromso = Coordinates::fromDegrees(69.6496, 18.9553);
        $polarDay = CivilDate::fromYmd(2025, 6, 21);

        self::assertThrows(
            UndefinedScheduleException::class,
            static fn() => $calculator->calculate($tromso, $polarDay, $parameters),
        );
        self::assertThrows(
            UndefinedScheduleException::class,
            static fn() => $calculator->calculateWithMetadata($tromso, $polarDay, $parameters),
        );
    }

    public function testMalformedCoordinateArrayExceptionIsUnchanged(): void
    {
        $calculator = PrayerTimesCalculator::default();
        $parameters = CalculationMethod::SINGAPORE->defaultParameters();

        self::assertThrows(
            InvalidCoordinatesException::class,
            static fn() => $calculator->calculate([999.0, 999.0], self::date(), $parameters),
        );
        self::assertThrows(
            InvalidCoordinatesException::class,
            static fn() => $calculator->calculateWithMetadata([999.0, 999.0], self::date(), $parameters),
        );
    }

    public function testNormalOutcomeIsCompleteWithTheRegistrySourceSet(): void
    {
        $outcome = PrayerTimesCalculator::default()->calculateWithMetadata(
            self::coordinates(),
            self::date(),
            self::parameters(),
        );
        $metadata = $outcome->metadata;
        $array = $metadata->toArray();

        self::assertSame('1.0', $array['schema_version']);
        self::assertSame('prayer', $array['context']);
        self::assertSame('complete', $array['status']);
        self::assertSame(LibraryVersion::CURRENT, $array['library_version']);
        self::assertSame([], $array['warnings']);
        self::assertSame([
            ['id' => 'prayer.high-latitude.middle_of_night', 'kind' => 'algorithm', 'version' => null, 'digest' => null, 'range' => null],
            ['id' => 'prayer.isha.angle', 'kind' => 'algorithm', 'version' => null, 'digest' => null, 'range' => null],
            ['id' => 'prayer.maghrib.interval', 'kind' => 'algorithm', 'version' => null, 'digest' => null, 'range' => null],
            ['id' => 'prayer.method.singapore', 'kind' => 'algorithm', 'version' => 'method-defaults-v1', 'digest' => null, 'range' => null],
            ['id' => 'solar.mean-meeus', 'kind' => 'algorithm', 'version' => 'meeus-2ed', 'digest' => null, 'range' => null],
        ], $array['sources']);
        self::assertSame(self::parameters()->toArray(), $array['policy']);
        self::assertSame(['applied' => false, 'kind' => null, 'rule' => null], $array['fallback']);
        self::assertSame('half_up_to_minute_utc', $array['rounding']);

        $json = json_encode($outcome->toArray(), JSON_THROW_ON_ERROR);

        self::assertStringNotContainsString('Astro\\', $json);
        self::assertStringNotContainsString('AlFalaq\\AlFalaq\\Astro', $json);
    }

    public function testSeasonalModeListsTheTwilightModelOnly(): void
    {
        $parameters = CalculationMethod::MOON_SIGHTING_COMMITTEE->defaultParameters();
        $outcome = PrayerTimesCalculator::defaultFor($parameters)->calculateWithMetadata(
            self::coordinates(),
            self::date(),
            $parameters,
        );
        $ids = self::sourceIds($outcome);

        self::assertContains('prayer.high-latitude.seasonal_twilight', $ids);
        self::assertNotContains('prayer.high-latitude.middle_of_night', $ids);
    }

    public function testNoneModeListsNeitherNightSource(): void
    {
        $parameters = self::noneParameters();
        $outcome = PrayerTimesCalculator::default()->calculateWithMetadata(
            self::coordinates(),
            self::date(),
            $parameters,
        );

        self::assertSame([
            'prayer.isha.angle',
            'prayer.maghrib.interval',
            'prayer.method.none',
            'solar.mean-meeus',
        ], self::sourceIds($outcome));
        self::assertNotContains('prayer.high-latitude.middle_of_night', self::sourceIds($outcome));
        self::assertNotContains('prayer.high-latitude.seasonal_twilight', self::sourceIds($outcome));
    }

    public function testNonZeroCallerAdjustmentsAddTheCallerSource(): void
    {
        $parameters = self::parameters()
            ->withAdjustments(PrayerAdjustments::empty()->with(Prayer::FAJR, 3));

        $outcome = PrayerTimesCalculator::default()->calculateWithMetadata(
            self::coordinates(),
            self::date(),
            $parameters,
        );

        self::assertContains('caller.adjustments', self::sourceIds($outcome));
        self::assertSame('caller_input', self::sourceById($outcome, 'caller.adjustments')['kind']);
    }

    public function testEmptyCallerAdjustmentsOmitTheCallerSource(): void
    {
        $outcome = PrayerTimesCalculator::default()->calculateWithMetadata(
            self::coordinates(),
            self::date(),
            self::parameters(),
        );

        self::assertNotContains('caller.adjustments', self::sourceIds($outcome));
    }

    public function testMethodAdjustmentsDoNotAddACallerSource(): void
    {
        $outcome = PrayerTimesCalculator::default()->calculateWithMetadata(
            self::coordinates(),
            self::date(),
            self::parameters(),
        );

        self::assertSame(1, self::parameters()->methodAdjustments->for(Prayer::DHUHR));
        self::assertSame(0, self::parameters()->adjustments->for(Prayer::DHUHR));
        self::assertNotContains('caller.adjustments', self::sourceIds($outcome));
    }

    public function testPolarFallbackIsVisibleInStatusWarningAndInputs(): void
    {
        $parameters = CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters()
            ->withPolarCircleRule(PolarCircleRule::NEAREST_LATITUDE);
        $requested = Coordinates::fromDegrees(69.6496, 18.9553);
        $date = CivilDate::fromYmd(2025, 12, 21);
        $outcome = PrayerTimesCalculator::defaultFor($parameters)->calculateWithMetadata(
            $requested,
            $date,
            $parameters,
        );
        $array = $outcome->metadata->toArray();

        self::assertSame('fallback_applied', $array['status']);
        self::assertCount(1, $array['warnings']);

        $warning = $array['warnings'][0];

        self::assertSame('polar_fallback_applied', $warning['code']);
        self::assertSame('nearest_latitude', $warning['details']['rule']);
        self::assertSame(69.6496, $warning['details']['requested_latitude']);
        self::assertSame(18.9553, $warning['details']['requested_longitude']);
        self::assertSame('2025-12-21', $warning['details']['requested_date']);
        self::assertSame('2025-12-21', $warning['details']['effective_date']);
        self::assertLessThan(69.6496, $warning['details']['effective_latitude']);
        self::assertNotSame('', $warning['message']);
        self::assertSame($requested->toArray(), $array['input']['coordinates']);
        self::assertNotSame($requested->toArray(), $array['effective_input']['coordinates']);
        self::assertSame([
            'applied' => true,
            'kind' => 'polar',
            'rule' => 'nearest_latitude',
        ], $array['fallback']);
        self::assertContains('prayer.polar.nearest_latitude', self::sourceIds($outcome));
    }

    public function testPolarFallbackKeepsLegacyEquality(): void
    {
        $parameters = CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters()
            ->withPolarCircleRule(PolarCircleRule::NEAREST_LATITUDE);
        $calculator = PrayerTimesCalculator::defaultFor($parameters);
        $tromso = Coordinates::fromDegrees(69.6496, 18.9553);
        $date = CivilDate::fromYmd(2025, 12, 21);

        self::assertSame(
            $calculator->calculate($tromso, $date, $parameters)->toArray(),
            $calculator->calculateWithMetadata($tromso, $date, $parameters)->result->toArray(),
        );
    }

    public function testUnidentifiedSolarEngineIsReportedAsCustomWithAWarning(): void
    {
        $calculator = new PrayerTimesCalculator(
            self::zeroOffsetEngine(),
            new MiddleOfNightCorrection(),
            new NearestLatitudeHandler(),
            new SeasonAdjustedTwilight(),
        );
        $parameters = CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters();
        $outcome = $calculator->calculateWithMetadata(self::coordinates(), self::date(), $parameters);
        $array = $outcome->metadata->toArray();

        self::assertContains('custom.solar', self::sourceIds($outcome));
        self::assertNotContains('solar.mean-meeus', self::sourceIds($outcome));
        self::assertCount(1, $array['warnings']);
        self::assertSame([
            'code' => 'unidentified_calculation_source',
            'message' => $array['warnings'][0]['message'],
            'details' => ['component' => 'solar', 'source_id' => 'custom.solar'],
        ], $array['warnings'][0]);
        self::assertSame('complete', $array['status']);
        self::assertInstanceOf(PrayerSchedule::class, $calculator->calculate(self::coordinates(), self::date(), $parameters));
    }

    public function testIdentifiedCustomSolarEngineIsReportedWithoutAWarning(): void
    {
        $calculator = new PrayerTimesCalculator(
            self::zeroOffsetIdentifiedEngine(),
            new MiddleOfNightCorrection(),
            new NearestLatitudeHandler(),
            new SeasonAdjustedTwilight(),
        );
        $parameters = CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters();
        $outcome = $calculator->calculateWithMetadata(self::coordinates(), self::date(), $parameters);

        self::assertContains('custom.test-engine', self::sourceIds($outcome));
        self::assertSame([], $outcome->metadata->common->warnings);
    }

    public function testUnidentifiedCustomIshaRuleIsReported(): void
    {
        $parameters = self::parameters()->withIshaRule(self::customIshaRule());
        $outcome = PrayerTimesCalculator::default()->calculateWithMetadata(
            self::coordinates(),
            self::date(),
            $parameters,
        );
        $array = $outcome->metadata->toArray();

        self::assertContains('custom.isha', self::sourceIds($outcome));
        self::assertNotContains('prayer.isha.angle', self::sourceIds($outcome));
        self::assertCount(1, $array['warnings']);
        self::assertSame('unidentified_calculation_source', $array['warnings'][0]['code']);
        self::assertSame(['component' => 'isha', 'source_id' => 'custom.isha'], $array['warnings'][0]['details']);
        self::assertInstanceOf(PrayerSchedule::class, $outcome->result);
    }

    /**
     * Ids of the sorted sources in one outcome.
     *
     * @param PrayerCalculationOutcome $outcome Outcome to inspect.
     * @return list<string>
     */
    private static function sourceIds(PrayerCalculationOutcome $outcome): array
    {
        return array_map(
            static fn(CalculationSource $source): string => $source->id,
            $outcome->metadata->common->sources,
        );
    }

    /**
     * One source's wire shape by id (test helper).
     *
     * @param PrayerCalculationOutcome $outcome Outcome to inspect.
     * @param string $id Registry id.
     * @return array{id: string, kind: string, version: string|null, digest: string|null, range: string|null}
     */
    private static function sourceById(PrayerCalculationOutcome $outcome, string $id): array
    {
        foreach ($outcome->metadata->common->sources as $source) {
            if ($source->id === $id) {
                return $source->toArray();
            }
        }

        self::fail(sprintf('Source %s not found.', $id));
    }

    /**
     * Assert a callable throws exactly the expected exception class.
     *
     * @param class-string<\Throwable> $expected Expected exception class.
     * @param callable(): mixed $call Code under test.
     * @return void
     */
    private static function assertThrows(string $expected, callable $call): void
    {
        try {
            $call();
        } catch (\Throwable $throwable) {
            self::assertInstanceOf($expected, $throwable);

            return;
        }

        self::fail(sprintf('Expected %s, got no exception.', $expected));
    }

    private static function noneParameters(): CalculationParameters
    {
        return CalculationParameters::create(
            CalculationMethod::NONE,
            Madhab::SHAFI,
            HighLatitudeRule::NONE,
            PolarCircleRule::NEAREST_LATITUDE,
            18.0,
            new \AlFalaq\AlFalaq\Prayer\Strategies\AngleIsha(17.0),
            new MinutesAfterSunsetMaghrib(0),
            0,
            10,
            15,
            28,
            0.0,
            false,
            PrayerAdjustments::empty(),
            PrayerAdjustments::empty(),
        );
    }

    /**
     * Day engine double with no source identity and no astronomy.
     *
     * @return SolarDayEngine Engine double.
     */
    private static function zeroOffsetEngine(): SolarDayEngine
    {
        return new class implements SolarDayEngine {
            public function solarTime(Coordinates $coordinates, CivilDate $date, mixed $elevationM): SolarTime
            {
                $midnight = $date->toUtcMidnight()->getTimestamp();

                return new SolarTime(
                    new \DateTimeImmutable('@' . ($midnight + 43200)),
                    new \DateTimeImmutable('@' . ($midnight + 21600)),
                    new \DateTimeImmutable('@' . ($midnight + 64800)),
                );
            }

            public function hourAngleFloat(Coordinates $coordinates, CivilDate $date, mixed $elevationM, float $angleDeg, bool $afterTransit): float
            {
                return 3.0;
            }

            public function hourAngle(Coordinates $coordinates, CivilDate $date, mixed $elevationM, float $angleDeg, bool $afterTransit): \DateTimeImmutable
            {
                return new \DateTimeImmutable('@' . ($date->toUtcMidnight()->getTimestamp() + 10800));
            }

            public function afternoon(Coordinates $coordinates, CivilDate $date, mixed $elevationM, mixed $shadowLength): \DateTimeImmutable
            {
                return new \DateTimeImmutable('@' . ($date->toUtcMidnight()->getTimestamp() + 54000));
            }

            public function isSolarDefined(Coordinates $coordinates, CivilDate $date, mixed $elevationM): bool
            {
                return true;
            }
        };
    }

    /**
     * Same double, but with an explicit custom source identity.
     *
     * @return SolarDayEngine Engine double implementing IdentifiesCalculationSource.
     */
    private static function zeroOffsetIdentifiedEngine(): SolarDayEngine
    {
        return new class implements SolarDayEngine, IdentifiesCalculationSource {
            public function solarTime(Coordinates $coordinates, CivilDate $date, mixed $elevationM): SolarTime
            {
                $midnight = $date->toUtcMidnight()->getTimestamp();

                return new SolarTime(
                    new \DateTimeImmutable('@' . ($midnight + 43200)),
                    new \DateTimeImmutable('@' . ($midnight + 21600)),
                    new \DateTimeImmutable('@' . ($midnight + 64800)),
                );
            }

            public function hourAngleFloat(Coordinates $coordinates, CivilDate $date, mixed $elevationM, float $angleDeg, bool $afterTransit): float
            {
                return 3.0;
            }

            public function hourAngle(Coordinates $coordinates, CivilDate $date, mixed $elevationM, float $angleDeg, bool $afterTransit): \DateTimeImmutable
            {
                return new \DateTimeImmutable('@' . ($date->toUtcMidnight()->getTimestamp() + 10800));
            }

            public function afternoon(Coordinates $coordinates, CivilDate $date, mixed $elevationM, mixed $shadowLength): \DateTimeImmutable
            {
                return new \DateTimeImmutable('@' . ($date->toUtcMidnight()->getTimestamp() + 54000));
            }

            public function isSolarDefined(Coordinates $coordinates, CivilDate $date, mixed $elevationM): bool
            {
                return true;
            }

            public function calculationSource(): CalculationSource
            {
                return CalculationSource::algorithm('custom.test-engine', 'v1');
            }
        };
    }

    /**
     * Interval Isha rule without a source identity.
     *
     * @return IshaRule Custom rule.
     */
    private static function customIshaRule(): IshaRule
    {
        return new class implements IshaRule {
            public function minutesAfterMaghrib(): int
            {
                return 90;
            }

            public function angle(): null
            {
                return null;
            }

            public function validate(): void {}
        };
    }
}
