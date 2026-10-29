<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Hijri;

use AlFalaq\AlFalaq\Hijri\Contracts\HijriCalendar;
use AlFalaq\AlFalaq\Hijri\Contracts\OffsetStore;
use AlFalaq\AlFalaq\Hijri\Data\UmmAlQuraMonthStarts;
use AlFalaq\AlFalaq\Hijri\Services\GregorianToHijriConverter;
use AlFalaq\AlFalaq\Hijri\Strategies\TabularCalendar;
use AlFalaq\AlFalaq\Hijri\Strategies\UmmAlQuraCalendar;
use AlFalaq\AlFalaq\Hijri\ValueObjects\HijriDate;
use AlFalaq\AlFalaq\Prayer\Contracts\PrayerTimesCalculatorInterface;
use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Enums\PolarCircleRule;
use AlFalaq\AlFalaq\Prayer\Enums\Prayer;
use AlFalaq\AlFalaq\Prayer\Exceptions\UndefinedScheduleException;
use AlFalaq\AlFalaq\Prayer\Services\PrayerTimesCalculator;
use AlFalaq\AlFalaq\Prayer\ValueObjects\CalculationParameters;
use AlFalaq\AlFalaq\Prayer\ValueObjects\PrayerSchedule;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\AstronomicalException;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ConfigurationException;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

/**
 * Gregorian bridge: frame selection, both rollovers, offsets, error taxonomy.
 */
final class GregorianToHijriConverterTest extends TestCase
{
    /**
     * Kuala Lumpur observer, used for the sunset-path tests.
     *
     * @return Coordinates Observer coordinates.
     */
    private function kualaLumpur(): Coordinates
    {
        return Coordinates::fromDegrees(3.1390, 101.6869);
    }

    /**
     * Honolulu observer (far west of Greenwich) — the only place the backward
     * rollover is reachable, because maghrib for UTC day D−1 must fall after
     * 00:00 UTC on day D.
     *
     * @return Coordinates Observer coordinates.
     */
    private function honolulu(): Coordinates
    {
        return Coordinates::fromDegrees(21.3069, -157.8583);
    }

    /**
     * Default MWL parameters.
     *
     * @return CalculationParameters Method defaults.
     */
    private function parameters(): CalculationParameters
    {
        return CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters();
    }

    /**
     * Maghrib instant predicted for one UTC civil day at an observer.
     *
     * @param Coordinates $coordinates Observer.
     * @param CivilDate $date UTC civil day.
     * @return DateTimeImmutable Maghrib instant (UTC).
     */
    private function maghrib(Coordinates $coordinates, CivilDate $date): DateTimeImmutable
    {
        return PrayerTimesCalculator::default()
            ->calculate($coordinates, $date, $this->parameters())
            ->timeFor(Prayer::MAGHRIB);
    }

    /**
     * A fixed-correction store.
     *
     * @param int $days Correction to return for every lookup.
     * @return OffsetStore Test store.
     */
    private function offsets(int $days): OffsetStore
    {
        return new class ($days) implements OffsetStore {
            /**
             * Bind the fixed correction.
             *
             * @param int $days Correction returned for every date.
             */
            public function __construct(private readonly int $days) {}

            /**
             * Return the fixed correction.
             *
             * @param HijriDate $date Ignored.
             * @param HijriCalendar $calendar Ignored.
             * @return int Configured correction.
             */
            public function offsetDaysFor(HijriDate $date, HijriCalendar $calendar): int
            {
                return $this->days;
            }
        };
    }

    /**
     * Without changeAtSunset the caller's own zone picks the civil date.
     *
     * @return void
     */
    public function testOwnZoneFrameIsTheDefault(): void
    {
        $instant = new DateTimeImmutable('2024-03-01 20:00:00', new DateTimeZone('UTC'));
        $converter = GregorianToHijriConverter::default();
        $calendar = new TabularCalendar();

        $tokyo = $converter->convert($instant->setTimezone(new DateTimeZone('Asia/Tokyo')), $calendar);
        $honolulu = $converter->convert($instant->setTimezone(new DateTimeZone('Pacific/Honolulu')), $calendar);

        self::assertSame('2024-03-02', $instant->setTimezone(new DateTimeZone('Asia/Tokyo'))->format('Y-m-d'));
        self::assertSame('2024-03-01', $instant->setTimezone(new DateTimeZone('Pacific/Honolulu'))->format('Y-m-d'));
        self::assertSame($calendar->fromGregorian(CivilDate::fromYmd(2024, 3, 2))->isoformat(), $tokyo->isoformat());
        self::assertSame($calendar->fromGregorian(CivilDate::fromYmd(2024, 3, 1))->isoformat(), $honolulu->isoformat());
        self::assertNotSame($tokyo->isoformat(), $honolulu->isoformat());
    }

    /**
     * Under changeAtSunset the frame is UTC, so the caller's label is irrelevant.
     *
     * @return void
     */
    public function testChangeAtSunsetUsesTheUtcFrame(): void
    {
        $instant = new DateTimeImmutable('2024-03-01 20:00:00', new DateTimeZone('UTC'));
        $converter = GregorianToHijriConverter::default();
        $calendar = new TabularCalendar();
        $coordinates = $this->kualaLumpur();
        $parameters = $this->parameters();

        $tokyo = $converter->convert($instant->setTimezone(new DateTimeZone('Asia/Tokyo')), $calendar, $coordinates, $parameters, true);
        $honolulu = $converter->convert($instant->setTimezone(new DateTimeZone('Pacific/Honolulu')), $calendar, $coordinates, $parameters, true);

        self::assertSame($tokyo->isoformat(), $honolulu->isoformat());

        $utcDate = CivilDate::fromYmd(2024, 3, 1);
        $expected = $instant >= $this->maghrib($coordinates, $utcDate) ? $utcDate->plusDays(1) : $utcDate;

        self::assertSame($calendar->fromGregorian($expected)->isoformat(), $tokyo->isoformat());
    }

    /**
     * Exactly at maghrib the date rolls forward.
     *
     * @return void
     */
    public function testMaghribBoundaryRollsForward(): void
    {
        $coordinates = $this->kualaLumpur();
        $calendar = new TabularCalendar();
        $utcDate = CivilDate::fromYmd(2024, 1, 2);
        $maghrib = $this->maghrib($coordinates, $utcDate);

        self::assertSame(
            $calendar->fromGregorian($utcDate->plusDays(1))->isoformat(),
            GregorianToHijriConverter::default()
                ->convert($maghrib, $calendar, $coordinates, $this->parameters(), true)
                ->isoformat(),
        );
    }

    /**
     * Just before maghrib the date stays on the UTC civil date.
     *
     * @return void
     */
    public function testBeforeMaghribStaysOnTheUtcDate(): void
    {
        $coordinates = $this->kualaLumpur();
        $calendar = new TabularCalendar();
        $utcDate = CivilDate::fromYmd(2024, 1, 2);
        $instant = $this->maghrib($coordinates, $utcDate)->sub(new \DateInterval('PT1S'));

        self::assertSame(
            $calendar->fromGregorian($utcDate)->isoformat(),
            GregorianToHijriConverter::default()
                ->convert($instant, $calendar, $coordinates, $this->parameters(), true)
                ->isoformat(),
        );
    }

    /**
     * The sunset roll crosses the calculator interface, never the concrete class.
     *
     * A stub pipeline whose full schedule is undefined forces the maghribAt
     * fallback; the rollover still resolves from the stubbed maghrib with no
     * astronomy involved.
     *
     * @return void
     */
    public function testSunsetRollReachesCalculatorThroughInterface(): void
    {
        $utc = new DateTimeZone('UTC');
        $calculator = new class implements PrayerTimesCalculatorInterface {
            public function calculate(Coordinates $coordinates, CivilDate $date, CalculationParameters $parameters): PrayerSchedule
            {
                throw new UndefinedScheduleException('no schedule');
            }

            public function maghribAt(Coordinates $coordinates, CivilDate $date, CalculationParameters $parameters): DateTimeImmutable
            {
                return new DateTimeImmutable($date->isoformat() . ' 10:00:00', new DateTimeZone('UTC'));
            }
        };

        $converter = new GregorianToHijriConverter($calculator);
        $calendar = new TabularCalendar();
        $coordinates = Coordinates::fromDegrees(3.1390, 101.6869);

        $after = new DateTimeImmutable('2025-03-01 12:00:00', $utc);
        $before = new DateTimeImmutable('2025-03-01 09:00:00', $utc);

        self::assertSame(
            $calendar->fromGregorian(CivilDate::fromYmd(2025, 3, 2))->isoformat(),
            $converter->convert($after, $calendar, $coordinates, CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters(), true)->isoformat(),
        );
        self::assertSame(
            $calendar->fromGregorian(CivilDate::fromYmd(2025, 3, 1))->isoformat(),
            $converter->convert($before, $calendar, $coordinates, CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters(), true)->isoformat(),
        );
    }

    /**
     * A far-west observer can sit before the previous local maghrib, and the
     * date rolls backward instead of forward.
     *
     * @return void
     */
    public function testBackwardRolloverForFarWestObserver(): void
    {
        $coordinates = $this->honolulu();
        $calendar = new TabularCalendar();
        $utcDate = CivilDate::fromYmd(2024, 1, 2);
        $previousMaghrib = $this->maghrib($coordinates, $utcDate->plusDays(-1));

        // Precondition: the backward branch is only reachable when the previous
        // day's maghrib falls after 00:00 UTC on the current UTC day.
        self::assertGreaterThan($utcDate->toUtcMidnight(), $previousMaghrib);

        $instant = $previousMaghrib->sub(new \DateInterval('PT1S'));

        self::assertSame(
            $calendar->fromGregorian($utcDate->plusDays(-1))->isoformat(),
            GregorianToHijriConverter::default()
                ->convert($instant, $calendar, $coordinates, $this->parameters(), true)
                ->isoformat(),
        );
    }

    /**
     * Exactly at the previous day's maghrib the date stays on the UTC civil
     * date (the backward comparison is strict: <, not <=).
     *
     * @return void
     */
    public function testExactPreviousMaghribStaysOnTheUtcDate(): void
    {
        $coordinates = $this->honolulu();
        $calendar = new TabularCalendar();
        $utcDate = CivilDate::fromYmd(2024, 1, 2);
        $previousMaghrib = $this->maghrib($coordinates, $utcDate->plusDays(-1));

        // Precondition: same fixture as the backward rollover (previous
        // maghrib after 00:00 UTC), but exactly on the instant.
        self::assertGreaterThan($utcDate->toUtcMidnight(), $previousMaghrib);

        self::assertSame(
            $calendar->fromGregorian($utcDate)->isoformat(),
            GregorianToHijriConverter::default()
                ->convert($previousMaghrib, $calendar, $coordinates, $this->parameters(), true)
                ->isoformat(),
        );
    }

    /**
     * A DST spring-forward does not disturb the local civil date.
     *
     * @return void
     */
    public function testDstSpringForwardKeepsTheLocalCivilDate(): void
    {
        $converter = GregorianToHijriConverter::default();
        $calendar = new TabularCalendar();
        $zone = new DateTimeZone('America/New_York');

        foreach (['2024-03-10 01:59:00', '2024-03-10 03:00:00'] as $local) {
            $instant = new DateTimeImmutable($local, $zone);

            self::assertSame(
                $calendar->fromGregorian(CivilDate::fromYmd(2024, 3, 10))->isoformat(),
                $converter->convert($instant, $calendar)->isoformat(),
                $local,
            );
        }
    }

    /**
     * changeAtSunset without coordinates is a configuration error.
     *
     * @return void
     */
    public function testChangeAtSunsetWithoutCoordinatesIsAConfigurationError(): void
    {
        $this->expectException(ConfigurationException::class);
        GregorianToHijriConverter::default()->convert(
            new DateTimeImmutable('2024-01-02 12:00:00', new DateTimeZone('UTC')),
            new TabularCalendar(),
            null,
            $this->parameters(),
            true,
        );
    }

    /**
     * changeAtSunset without parameters is a configuration error.
     *
     * @return void
     */
    public function testChangeAtSunsetWithoutParametersIsAConfigurationError(): void
    {
        $this->expectException(ConfigurationException::class);
        GregorianToHijriConverter::default()->convert(
            new DateTimeImmutable('2024-01-02 12:00:00', new DateTimeZone('UTC')),
            new TabularCalendar(),
            $this->kualaLumpur(),
            null,
            true,
        );
    }

    /**
     * An undefined polar maghrib propagates the astronomy error untouched.
     *
     * @return void
     */
    public function testPolarMaghribFailurePropagates(): void
    {
        $parameters = $this->parameters()->withPolarCircleRule(PolarCircleRule::NONE);

        $this->expectException(AstronomicalException::class);
        GregorianToHijriConverter::default()->convert(
            new DateTimeImmutable('2025-06-21 12:00:00', new DateTimeZone('UTC')),
            new TabularCalendar(),
            Coordinates::fromDegrees(69.6496, 18.9553),
            $parameters,
            true,
        );
    }

    /**
     * The store sees the converted (pre-offset) date and the calendar.
     *
     * @return void
     */
    public function testOffsetStoreReceivesTheConvertedDate(): void
    {
        $store = new class implements OffsetStore {
            /** @var list<string> Recorded lookups. */
            public array $calls = [];

            /**
             * Record the lookup and return a no-op correction.
             *
             * @param HijriDate $date Converted date.
             * @param HijriCalendar $calendar Source calendar.
             * @return int Always 0.
             */
            public function offsetDaysFor(HijriDate $date, HijriCalendar $calendar): int
            {
                $this->calls[] = $date->isoformat() . '|' . $calendar->name();

                return 0;
            }
        };

        GregorianToHijriConverter::default()->convert(
            new DateTimeImmutable('2025-03-01 12:00:00', new DateTimeZone('UTC')),
            new TabularCalendar(),
            offsets: $store,
        );

        self::assertSame(['1446-09-01|tabular'], $store->calls);
    }

    /**
     * Offsets are applied once, as a Hijri shift after conversion.
     *
     * @return void
     */
    public function testOffsetIsAppliedOnceAfterConversion(): void
    {
        $instant = new DateTimeImmutable('2025-03-01 12:00:00', new DateTimeZone('UTC'));
        $converter = GregorianToHijriConverter::default();
        $calendar = new TabularCalendar();
        $plain = $converter->convert($instant, $calendar);

        self::assertSame(
            $calendar->shift($plain, 2)->isoformat(),
            $converter->convert($instant, $calendar, offsets: $this->offsets(2))->isoformat(),
        );
        self::assertSame($plain->isoformat(), $converter->convert($instant, $calendar, offsets: $this->offsets(0))->isoformat());
    }

    /**
     * Because the offset is a shift, it crosses month boundaries correctly.
     *
     * @return void
     */
    public function testOffsetCrossesAMonthBoundary(): void
    {
        $starts = UmmAlQuraMonthStarts::STARTS;
        $index = (1446 - UmmAlQuraMonthStarts::FIRST_YEAR) * 12 + (10 - UmmAlQuraMonthStarts::FIRST_MONTH);
        $firstOfNextMonth = CivilDate::fromDateTime(new DateTimeImmutable($starts[$index], new DateTimeZone('UTC')));
        $lastOfMonth = $firstOfNextMonth->plusDays(-1);

        $converter = GregorianToHijriConverter::default();
        $calendar = new UmmAlQuraCalendar();

        self::assertSame(
            sprintf('1446-09-%02d', $calendar->monthLength(1446, 9)),
            $converter->convert($lastOfMonth->toUtcMidnight(), $calendar)->isoformat(),
        );
        self::assertSame(
            '1446-10-01',
            $converter->convert($lastOfMonth->toUtcMidnight(), $calendar, offsets: $this->offsets(1))->isoformat(),
        );
    }

    public function testSunsetRolloverFallsBackToMaghribOnly(): void
    {
        // Tromsø 2025-11-27 (#15): today's sunset is defined but
        // tomorrow's sunrise is not, so the full schedule is undefined
        // under PolarCircleRule::NONE while maghrib itself exists. The
        // bridge must resolve maghrib alone instead of failing.
        // (A plain AstronomicalException from the full schedule takes the
        // same fallback; an undefined maghrib from maghribAt() still
        // propagates.)
        $params = CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters()
            ->withPolarCircleRule(PolarCircleRule::NONE);
        $coordinates = Coordinates::fromDegrees(69.6496, 18.9553);
        $date = CivilDate::fromYmd(2025, 11, 27);

        try {
            PrayerTimesCalculator::default()->calculate($coordinates, $date, $params);
            self::markTestSkipped('full schedule defined here; pick a stricter triple');
        } catch (UndefinedScheduleException) {
            // expected: the fallback path is under test
        }

        $converter = GregorianToHijriConverter::default();
        $hijri = $converter->convert(
            new DateTimeImmutable('2025-11-27T20:00:00+00:00'),
            new TabularCalendar(),
            $coordinates,
            $params,
            true,
        );

        // 20:00 UTC is past the ~10:36 UTC maghrib, so the UTC civil day
        // 2025-11-27 rolls to 2025-11-28 -> 1447-06-07 (tabular).
        self::assertSame('1447-06-07', $hijri->isoformat());
    }

    /**
     * At 2100-12-31 the full schedule is undefined at the range edge while
     * maghrib itself is defined, so the bridge resolves maghrib alone; an
     * instant at/after maghrib still fails on the plusDays(1) rollover.
     *
     * @return void
     */
    public function testUpperBoundaryFallsBackToMaghribOnly(): void
    {
        $coordinates = $this->kualaLumpur();
        $parameters = $this->parameters();
        $date = CivilDate::fromYmd(2100, 12, 31);
        $calendar = new TabularCalendar();

        try {
            PrayerTimesCalculator::default()->calculate($coordinates, $date, $parameters);
            self::fail('full schedule must be undefined at 2100-12-31.');
        } catch (UndefinedScheduleException) {
            // expected: the fallback path is under test
        }

        $maghrib = PrayerTimesCalculator::default()->maghribAt($coordinates, $date, $parameters);

        $converter = GregorianToHijriConverter::default();

        self::assertSame(
            $calendar->fromGregorian($date)->isoformat(),
            $converter->convert(
                $maghrib->sub(new \DateInterval('PT1S')),
                $calendar,
                $coordinates,
                $parameters,
                true,
            )->isoformat(),
        );

        try {
            $converter->convert($maghrib, $calendar, $coordinates, $parameters, true);
            self::fail('post-maghrib rollover past 2100 must throw ValidationException.');
        } catch (ValidationException $e) {
            self::assertNotSame('', $e->getMessage());
        }
    }

    /**
     * A plain AstronomicalException (Asr/Isha undefined) takes the
     * maghrib-only fallback too: #15 is "schedule undefined", not "the
     * pipeline threw one particular subclass".
     *
     * @return void
     */
    public function testSunsetRollFallsBackOnPlainAstronomicalException(): void
    {
        $utc = new DateTimeZone('UTC');
        $calculator = new class implements PrayerTimesCalculatorInterface {
            public function calculate(Coordinates $coordinates, CivilDate $date, CalculationParameters $parameters): PrayerSchedule
            {
                throw new AstronomicalException('Unable to compute Asr: the afternoon shadow length is undefined.');
            }

            public function maghribAt(Coordinates $coordinates, CivilDate $date, CalculationParameters $parameters): DateTimeImmutable
            {
                return new DateTimeImmutable($date->isoformat() . ' 10:00:00', new DateTimeZone('UTC'));
            }
        };

        $converter = new GregorianToHijriConverter($calculator);
        $calendar = new TabularCalendar();

        self::assertSame(
            $calendar->fromGregorian(CivilDate::fromYmd(2025, 3, 2))->isoformat(),
            $converter->convert(
                new DateTimeImmutable('2025-03-01 12:00:00', $utc),
                $calendar,
                $this->kualaLumpur(),
                CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters(),
                true,
            )->isoformat(),
        );
    }

    /**
     * Validation errors are not "schedule undefined": they must escape.
     *
     * A future widening to AlFalaqException (or Throwable) would make a bad
     * caller configuration look like a polar fallback; this pins the edge.
     *
     * @return void
     */
    public function testSunsetRollDoesNotSwallowValidationErrors(): void
    {
        $calculator = new class implements PrayerTimesCalculatorInterface {
            public function calculate(Coordinates $coordinates, CivilDate $date, CalculationParameters $parameters): PrayerSchedule
            {
                throw new ValidationException('Invalid parameters.');
            }

            public function maghribAt(Coordinates $coordinates, CivilDate $date, CalculationParameters $parameters): DateTimeImmutable
            {
                return new DateTimeImmutable($date->isoformat() . ' 10:00:00', new DateTimeZone('UTC'));
            }
        };

        $converter = new GregorianToHijriConverter($calculator);

        $this->expectException(ValidationException::class);

        $converter->convert(
            new DateTimeImmutable('2025-03-01 12:00:00', new DateTimeZone('UTC')),
            new TabularCalendar(),
            $this->kualaLumpur(),
            CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters(),
            true,
        );
    }
}
