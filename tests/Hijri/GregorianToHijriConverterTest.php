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
use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Enums\PolarCircleRule;
use AlFalaq\AlFalaq\Prayer\Enums\Prayer;
use AlFalaq\AlFalaq\Prayer\Services\PrayerTimesCalculator;
use AlFalaq\AlFalaq\Prayer\ValueObjects\CalculationParameters;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\AstronomicalException;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ConfigurationException;
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
}
