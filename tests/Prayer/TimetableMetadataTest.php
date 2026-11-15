<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Prayer;

use AlFalaq\AlFalaq\Prayer\Contracts\PrayerMetadataCalculatorInterface;
use AlFalaq\AlFalaq\Prayer\Contracts\PrayerTimesCalculatorInterface;
use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Enums\PolarCircleRule;
use AlFalaq\AlFalaq\Prayer\Services\TimetableService;
use AlFalaq\AlFalaq\Prayer\ValueObjects\CalculationParameters;
use AlFalaq\AlFalaq\Prayer\ValueObjects\PrayerCalculationMetadata;
use AlFalaq\AlFalaq\Prayer\ValueObjects\PrayerCalculationOutcome;
use AlFalaq\AlFalaq\Prayer\ValueObjects\PrayerSchedule;
use AlFalaq\AlFalaq\SharedKernel\Enums\CalculationContext;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ConfigurationException;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CalculationMetadata;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;
use PHPUnit\Framework\TestCase;

/**
 * Metadata-aware monthly timetable: per-day provenance and injected-calculator policy.
 */
final class TimetableMetadataTest extends TestCase
{
    private static function kualaLumpur(): Coordinates
    {
        return Coordinates::fromDegrees(3.1390, 101.6869);
    }

    private static function cannedSchedule(CivilDate $date): PrayerSchedule
    {
        $at = static fn(string $time): \DateTimeImmutable => new \DateTimeImmutable(
            $date->isoformat() . ' ' . $time,
            new \DateTimeZone('UTC'),
        );

        return new PrayerSchedule(
            $at('05:00'),
            $at('05:10'),
            $at('06:20'),
            $at('06:20'),
            $at('06:35'),
            $at('06:48'),
            $at('12:26'),
            $at('15:39'),
            $at('18:28'),
            $at('19:38'),
        );
    }

    private static function outcome(
        Coordinates $coordinates,
        CivilDate $date,
        CalculationParameters $parameters,
        ?PrayerSchedule $schedule = null,
    ): PrayerCalculationOutcome {
        return new PrayerCalculationOutcome(
            $schedule ?? self::cannedSchedule($date),
            new PrayerCalculationMetadata(
                CalculationMetadata::create(CalculationContext::PRAYER),
                $coordinates,
                $date,
                $coordinates,
                $date,
                $parameters,
                null,
            ),
        );
    }

    public function testFebruaryLeapYearReturnsOneOutcomePerDayInOrder(): void
    {
        $service = TimetableService::default();
        $parameters = CalculationMethod::SINGAPORE->defaultParameters();
        $outcomes = $service->monthlyWithMetadata(self::kualaLumpur(), 2024, 2, $parameters);

        self::assertCount(29, $outcomes);
        self::assertContainsOnlyInstancesOf(PrayerCalculationOutcome::class, $outcomes);

        foreach ($outcomes as $index => $outcome) {
            self::assertSame(CalculationContext::PRAYER, $outcome->metadata->common->context);
            self::assertSame(
                sprintf('2024-02-%02d', $index + 1),
                $outcome->metadata->requestedDate->isoformat(),
            );
        }

        $legacy = $service->monthly(self::kualaLumpur(), 2024, 2, $parameters);

        self::assertCount(29, $legacy);

        foreach ($legacy as $index => $schedule) {
            self::assertSame($schedule->toArray(), $outcomes[$index]->result->toArray());
        }
    }

    public function testDaySpecificPolarFallbackIsVisible(): void
    {
        $service = TimetableService::default();
        $parameters = CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters()
            ->withPolarCircleRule(PolarCircleRule::NEAREST_LATITUDE);
        $tromso = Coordinates::fromDegrees(69.6496, 18.9553);
        $outcomes = $service->monthlyWithMetadata($tromso, 2025, 11, $parameters);

        self::assertCount(30, $outcomes);

        $fallbacks = 0;

        foreach ($outcomes as $outcome) {
            $fallback = $outcome->metadata->toArray()['fallback'];

            if (! $fallback['applied']) {
                self::assertSame('complete', $outcome->metadata->common->status->value);

                continue;
            }

            ++$fallbacks;
            self::assertSame('polar', $fallback['kind']);
            self::assertSame('nearest_latitude', $fallback['rule']);
            self::assertSame('fallback_applied', $outcome->metadata->common->status->value);
            self::assertCount(1, $outcome->metadata->common->warnings);
            self::assertSame('polar_fallback_applied', $outcome->metadata->common->warnings[0]->code->value);
        }

        self::assertGreaterThanOrEqual(1, $fallbacks, 'November at Tromsø must contain a polar-night day.');

        $legacy = $service->monthly($tromso, 2025, 11, $parameters);

        self::assertCount(30, $legacy);

        foreach ($legacy as $index => $schedule) {
            self::assertSame($schedule->toArray(), $outcomes[$index]->result->toArray());
        }
    }

    public function testInjectedLegacyCalculatorThrowsConfigurationException(): void
    {
        $parameters = CalculationMethod::SINGAPORE->defaultParameters();
        $legacyDouble = new class (self::cannedSchedule(CivilDate::fromYmd(2025, 3, 1))) implements PrayerTimesCalculatorInterface {
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
        $service = new TimetableService($legacyDouble);

        self::assertCount(31, $service->monthly(self::kualaLumpur(), 2025, 3, $parameters));
        self::assertThrows(
            ConfigurationException::class,
            static fn() => $service->monthlyWithMetadata(self::kualaLumpur(), 2025, 3, $parameters),
        );
    }

    public function testInjectedMetadataCalculatorIsUsed(): void
    {
        $parameters = CalculationMethod::SINGAPORE->defaultParameters();
        $scheduleFor = static fn(CivilDate $date): PrayerSchedule => self::cannedSchedule($date);
        $outcomeFor = static fn(
            Coordinates $coordinates,
            CivilDate $date,
            CalculationParameters $parameters,
        ): PrayerCalculationOutcome => self::outcome($coordinates, $date, $parameters);
        $double = new class ($scheduleFor, $outcomeFor) implements PrayerMetadataCalculatorInterface {
            public int $calls = 0;

            /**
             * @var \Closure(CivilDate): PrayerSchedule
             */
            private readonly \Closure $scheduleFor;

            /**
             * @var \Closure(Coordinates, CivilDate, CalculationParameters): PrayerCalculationOutcome
             */
            private readonly \Closure $outcomeFor;

            /**
             * @param \Closure(CivilDate): PrayerSchedule $scheduleFor Canned schedule factory.
             * @param \Closure(Coordinates, CivilDate, CalculationParameters): PrayerCalculationOutcome $outcomeFor Canned outcome factory.
             */
            public function __construct(\Closure $scheduleFor, \Closure $outcomeFor)
            {
                $this->scheduleFor = $scheduleFor;
                $this->outcomeFor = $outcomeFor;
            }

            public function calculate(Coordinates $coordinates, CivilDate $date, CalculationParameters $parameters): PrayerSchedule
            {
                return ($this->scheduleFor)($date);
            }

            public function maghribAt(Coordinates $coordinates, CivilDate $date, CalculationParameters $parameters): \DateTimeImmutable
            {
                return ($this->scheduleFor)($date)->maghrib;
            }

            public function calculateWithMetadata(
                Coordinates $coordinates,
                CivilDate $date,
                CalculationParameters $parameters,
            ): PrayerCalculationOutcome {
                ++$this->calls;

                return ($this->outcomeFor)($coordinates, $date, $parameters);
            }
        };
        $service = new TimetableService($double);
        $outcomes = $service->monthlyWithMetadata(self::kualaLumpur(), 2024, 2, $parameters);

        self::assertCount(29, $outcomes);
        self::assertSame(29, $double->calls);
        self::assertSame('2024-02-01', $outcomes[0]->metadata->requestedDate->isoformat());
        self::assertSame('2024-02-29', $outcomes[28]->metadata->requestedDate->isoformat());
    }

    public function testInvalidMonthStillThrowsFromBothMethods(): void
    {
        $service = TimetableService::default();
        $parameters = CalculationMethod::SINGAPORE->defaultParameters();

        foreach ([0, 13] as $month) {
            self::assertThrows(
                ValidationException::class,
                static fn() => $service->monthly(self::kualaLumpur(), 2025, $month, $parameters),
            );
            self::assertThrows(
                ValidationException::class,
                static fn() => $service->monthlyWithMetadata(self::kualaLumpur(), 2025, $month, $parameters),
            );
        }
    }

    public function testMonthlyIsUnchanged(): void
    {
        $service = TimetableService::default();
        $parameters = CalculationMethod::SINGAPORE->defaultParameters();
        $legacy = $service->monthly(self::kualaLumpur(), 2025, 3, $parameters);
        $outcomes = $service->monthlyWithMetadata(self::kualaLumpur(), 2025, 3, $parameters);

        self::assertCount(31, $legacy);

        foreach ($legacy as $index => $schedule) {
            self::assertSame($schedule->toArray(), $outcomes[$index]->result->toArray());
        }
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
}
