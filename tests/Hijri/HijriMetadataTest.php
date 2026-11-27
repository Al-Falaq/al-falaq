<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Hijri;

use AlFalaq\AlFalaq\Hijri\Contracts\DescribesCalendar;
use AlFalaq\AlFalaq\Hijri\Contracts\HijriCalendar;
use AlFalaq\AlFalaq\Hijri\Contracts\OffsetStore;
use AlFalaq\AlFalaq\Hijri\Data\MabimsMonthStarts;
use AlFalaq\AlFalaq\Hijri\Enums\CalendarCoverage;
use AlFalaq\AlFalaq\Hijri\Enums\CalendarFamily;
use AlFalaq\AlFalaq\Hijri\Enums\MabimsCountry;
use AlFalaq\AlFalaq\Hijri\Services\GregorianToHijriConverter;
use AlFalaq\AlFalaq\Hijri\Strategies\MabimsCalendar;
use AlFalaq\AlFalaq\Hijri\Strategies\TabularCalendar;
use AlFalaq\AlFalaq\Hijri\Strategies\UmmAlQuraCalendar;
use AlFalaq\AlFalaq\Hijri\ValueObjects\CalendarProvenance;
use AlFalaq\AlFalaq\Hijri\ValueObjects\HijriCalculationOutcome;
use AlFalaq\AlFalaq\Hijri\ValueObjects\HijriDate;
use AlFalaq\AlFalaq\Prayer\Contracts\PrayerTimesCalculatorInterface;
use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Enums\PolarCircleRule;
use AlFalaq\AlFalaq\Prayer\Enums\Prayer;
use AlFalaq\AlFalaq\Prayer\Exceptions\UndefinedScheduleException;
use AlFalaq\AlFalaq\Prayer\Services\PrayerTimesCalculator;
use AlFalaq\AlFalaq\Prayer\ValueObjects\CalculationParameters;
use AlFalaq\AlFalaq\Prayer\ValueObjects\PrayerSchedule;
use AlFalaq\AlFalaq\SharedKernel\Enums\WarningCode;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\AstronomicalException;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ConfigurationException;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CalculationSource;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CalculationWarning;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Gregorian→Hijri metadata: the shared trace, both frames, offsets, fallback, and calendar identity.
 */
final class HijriMetadataTest extends TestCase
{
    /**
     * Kuala Lumpur observer, used for the sunset-path fixtures.
     *
     * @return Coordinates Observer coordinates.
     */
    private function kualaLumpur(): Coordinates
    {
        return Coordinates::fromDegrees(3.1390, 101.6869);
    }

    /**
     * Honolulu observer (far west of Greenwich) — the backward rollover fixture.
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
     * Delegating calendar that does not implement DescribesCalendar.
     *
     * @param HijriCalendar $inner Calendar providing the numeric behaviour.
     * @param string $name Reported calendar key.
     * @return HijriCalendar Delegating double.
     */
    private function plainCalendar(HijriCalendar $inner, string $name): HijriCalendar
    {
        return new class ($inner, $name) implements HijriCalendar {
            /**
             * Bind the delegate and reported key.
             *
             * @param HijriCalendar $inner Calendar providing the numeric behaviour.
             * @param string $name Reported calendar key.
             */
            public function __construct(
                private readonly HijriCalendar $inner,
                private readonly string $name,
            ) {}

            /**
             * Delegate a Gregorian conversion.
             *
             * @param CivilDate $date Gregorian day.
             * @return HijriDate Converted date.
             */
            public function fromGregorian(CivilDate $date): HijriDate
            {
                return $this->inner->fromGregorian($date);
            }

            /**
             * Delegate a Hijri conversion.
             *
             * @param HijriDate $date Hijri date.
             * @return CivilDate Gregorian day.
             */
            public function toGregorian(HijriDate $date): CivilDate
            {
                return $this->inner->toGregorian($date);
            }

            /**
             * Delegate the month length.
             *
             * @param int $year Hijri year.
             * @param int $month Hijri month.
             * @return int Month length.
             */
            public function monthLength(int $year, int $month): int
            {
                return $this->inner->monthLength($year, $month);
            }

            /**
             * Delegate a day shift.
             *
             * @param HijriDate $date Starting date.
             * @param int $days Signed day count.
             * @return HijriDate Shifted date.
             */
            public function shift(HijriDate $date, int $days): HijriDate
            {
                return $this->inner->shift($date, $days);
            }

            /**
             * Report the configured key.
             *
             * @return string Calendar key.
             */
            public function name(): string
            {
                return $this->name;
            }
        };
    }

    /**
     * Delegating calendar with a fixed provenance description.
     *
     * @param HijriCalendar $inner Calendar providing the numeric behaviour.
     * @param string $name Reported calendar key.
     * @param CalendarProvenance $provenance Provenance returned to metadata.
     * @return HijriCalendar Delegating double implementing DescribesCalendar.
     */
    private function describedCalendar(
        HijriCalendar $inner,
        string $name,
        CalendarProvenance $provenance,
    ): HijriCalendar {
        return new class ($inner, $name, $provenance) implements HijriCalendar, DescribesCalendar {
            /**
             * Bind the delegate, key, and provenance.
             *
             * @param HijriCalendar $inner Calendar providing the numeric behaviour.
             * @param string $name Reported calendar key.
             * @param CalendarProvenance $provenance Provenance returned to metadata.
             */
            public function __construct(
                private readonly HijriCalendar $inner,
                private readonly string $name,
                private readonly CalendarProvenance $provenance,
            ) {}

            /**
             * Delegate a Gregorian conversion.
             *
             * @param CivilDate $date Gregorian day.
             * @return HijriDate Converted date.
             */
            public function fromGregorian(CivilDate $date): HijriDate
            {
                return $this->inner->fromGregorian($date);
            }

            /**
             * Delegate a Hijri conversion.
             *
             * @param HijriDate $date Hijri date.
             * @return CivilDate Gregorian day.
             */
            public function toGregorian(HijriDate $date): CivilDate
            {
                return $this->inner->toGregorian($date);
            }

            /**
             * Delegate the month length.
             *
             * @param int $year Hijri year.
             * @param int $month Hijri month.
             * @return int Month length.
             */
            public function monthLength(int $year, int $month): int
            {
                return $this->inner->monthLength($year, $month);
            }

            /**
             * Delegate a day shift.
             *
             * @param HijriDate $date Starting date.
             * @param int $days Signed day count.
             * @return HijriDate Shifted date.
             */
            public function shift(HijriDate $date, int $days): HijriDate
            {
                return $this->inner->shift($date, $days);
            }

            /**
             * Report the configured key.
             *
             * @return string Calendar key.
             */
            public function name(): string
            {
                return $this->name;
            }

            /**
             * Return the configured provenance.
             *
             * @return CalendarProvenance Calendar identity.
             */
            public function calendarProvenance(): CalendarProvenance
            {
                return $this->provenance;
            }
        };
    }

    /**
     * Delegating calendar whose provenance read always throws.
     *
     * @param HijriCalendar $inner Calendar providing the numeric behaviour.
     * @param string $name Reported calendar key.
     * @return HijriCalendar Delegating double implementing DescribesCalendar.
     */
    private function throwingCalendar(HijriCalendar $inner, string $name): HijriCalendar
    {
        return new class ($inner, $name) implements HijriCalendar, DescribesCalendar {
            /**
             * Bind the delegate and reported key.
             *
             * @param HijriCalendar $inner Calendar providing the numeric behaviour.
             * @param string $name Reported calendar key.
             */
            public function __construct(
                private readonly HijriCalendar $inner,
                private readonly string $name,
            ) {}

            /**
             * Delegate a Gregorian conversion.
             *
             * @param CivilDate $date Gregorian day.
             * @return HijriDate Converted date.
             */
            public function fromGregorian(CivilDate $date): HijriDate
            {
                return $this->inner->fromGregorian($date);
            }

            /**
             * Delegate a Hijri conversion.
             *
             * @param HijriDate $date Hijri date.
             * @return CivilDate Gregorian day.
             */
            public function toGregorian(HijriDate $date): CivilDate
            {
                return $this->inner->toGregorian($date);
            }

            /**
             * Delegate the month length.
             *
             * @param int $year Hijri year.
             * @param int $month Hijri month.
             * @return int Month length.
             */
            public function monthLength(int $year, int $month): int
            {
                return $this->inner->monthLength($year, $month);
            }

            /**
             * Delegate a day shift.
             *
             * @param HijriDate $date Starting date.
             * @param int $days Signed day count.
             * @return HijriDate Shifted date.
             */
            public function shift(HijriDate $date, int $days): HijriDate
            {
                return $this->inner->shift($date, $days);
            }

            /**
             * Report the configured key.
             *
             * @return string Calendar key.
             */
            public function name(): string
            {
                return $this->name;
            }

            /**
             * Fail the call: the compact path must never reach provenance.
             *
             * @return CalendarProvenance Never returns.
             */
            public function calendarProvenance(): CalendarProvenance
            {
                throw new \RuntimeException('the compact path must not read calendar provenance');
            }
        };
    }

    /**
     * Sunset-frame outcome at Tromsø under the strict polar rule.
     *
     * @param string $instant ISO instant to convert.
     * @return HijriCalculationOutcome Outcome under test.
     */
    private static function tromsoOutcome(string $instant): HijriCalculationOutcome
    {
        return GregorianToHijriConverter::default()->convertWithMetadata(
            new DateTimeImmutable($instant),
            new TabularCalendar(),
            Coordinates::fromDegrees(69.6496, 18.9553),
            CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters()
                ->withPolarCircleRule(PolarCircleRule::NONE),
            true,
        );
    }

    /**
     * Warning codes in the envelope's sorted order.
     *
     * @param HijriCalculationOutcome $outcome Outcome to inspect.
     * @return list<string> Warning codes.
     */
    private static function warningCodes(HijriCalculationOutcome $outcome): array
    {
        return array_map(
            static fn(CalculationWarning $warning): string => $warning->code->value,
            $outcome->metadata->common->warnings,
        );
    }

    /**
     * Serialized details of the first warning with the given code.
     *
     * @param HijriCalculationOutcome $outcome Outcome to inspect.
     * @param WarningCode $code Warning code to find.
     * @return array<string, int|float|string|bool|null>|null Details, or null when absent.
     */
    private static function warningDetails(HijriCalculationOutcome $outcome, WarningCode $code): ?array
    {
        foreach ($outcome->metadata->common->warnings as $warning) {
            if ($warning->code === $code) {
                return $warning->toArray()['details'];
            }
        }

        return null;
    }

    /**
     * Source ids in the envelope's sorted order.
     *
     * @param HijriCalculationOutcome $outcome Outcome to inspect.
     * @return list<string> Source ids.
     */
    private static function sourceIds(HijriCalculationOutcome $outcome): array
    {
        return array_map(
            static fn(CalculationSource $source): string => $source->id,
            $outcome->metadata->common->sources,
        );
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

    /**
     * @return iterable<string, array{HijriCalendar, string, Coordinates|null, CalculationParameters|null, bool, int|null}>
     */
    public static function compactPathProvider(): iterable
    {
        $calendars = [
            'tabular' => new TabularCalendar(),
            'uqu' => new UmmAlQuraCalendar(),
            'mabims-my' => new MabimsCalendar(MabimsCountry::MY),
            'mabims-sg' => new MabimsCalendar(MabimsCountry::SG),
        ];

        $frames = [
            'civil' => ['2025-03-01T12:00:00+00:00', null, null, false],
            'sunset' => [
                '2025-03-01T20:00:00+00:00',
                Coordinates::fromDegrees(3.1390, 101.6869),
                CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters(),
                true,
            ],
        ];

        $offsets = ['none' => null, 'zero' => 0, 'two' => 2];

        foreach ($calendars as $calendarKey => $calendar) {
            foreach ($frames as $frameKey => [$instant, $coordinates, $parameters, $changeAtSunset]) {
                foreach ($offsets as $offsetKey => $offsetDays) {
                    yield sprintf('%s %s %s', $calendarKey, $frameKey, $offsetKey) => [
                        $calendar,
                        $instant,
                        $coordinates,
                        $parameters,
                        $changeAtSunset,
                        $offsetDays,
                    ];
                }
            }
        }
    }

    /**
     * The compact and metadata paths share one numeric trace.
     *
     * @param HijriCalendar $calendar Calendar under test.
     * @param string $instant Source instant.
     * @param Coordinates|null $coordinates Sunset-frame observer.
     * @param CalculationParameters|null $parameters Sunset-frame policy.
     * @param bool $changeAtSunset Whether the sunset frame is active.
     * @param int|null $offsetDays OffsetStore answer, null for no store.
     */
    #[DataProvider('compactPathProvider')]
    public function testCompactConvertEqualsMetadataResult(
        HijriCalendar $calendar,
        string $instant,
        ?Coordinates $coordinates,
        ?CalculationParameters $parameters,
        bool $changeAtSunset,
        ?int $offsetDays,
    ): void {
        $dateTime = new DateTimeImmutable($instant);
        $offsets = $offsetDays === null ? null : $this->offsets($offsetDays);
        $converter = GregorianToHijriConverter::default();

        $compact = $converter->convert($dateTime, $calendar, $coordinates, $parameters, $changeAtSunset, $offsets);
        $outcome = $converter->convertWithMetadata($dateTime, $calendar, $coordinates, $parameters, $changeAtSunset, $offsets);

        self::assertSame($compact->toArray(), $outcome->result->toArray());
    }

    /**
     * Metadata reads the offset store exactly once.
     *
     * @return void
     */
    public function testMetadataPathCallsTheOffsetStoreOnce(): void
    {
        $store = new class implements OffsetStore {
            /** @var int Recorded lookups. */
            public int $calls = 0;

            /**
             * Record the lookup and return a no-op correction.
             *
             * @param HijriDate $date Converted date.
             * @param HijriCalendar $calendar Source calendar.
             * @return int Always 0.
             */
            public function offsetDaysFor(HijriDate $date, HijriCalendar $calendar): int
            {
                ++$this->calls;

                return 0;
            }
        };

        GregorianToHijriConverter::default()->convertWithMetadata(
            new DateTimeImmutable('2025-03-01T12:00:00+00:00'),
            new TabularCalendar(),
            offsets: $store,
        );

        self::assertSame(1, $store->calls);
    }

    /**
     * convert() never reads DescribesCalendar, even when it is implemented.
     *
     * @return void
     */
    public function testCompactConvertDoesNotReadCalendarProvenance(): void
    {
        $converter = GregorianToHijriConverter::default();
        $calendar = $this->throwingCalendar(new TabularCalendar(), 'tabular');
        $instant = new DateTimeImmutable('2025-03-01T12:00:00+00:00');

        self::assertSame(
            (new TabularCalendar())->fromGregorian(CivilDate::fromYmd(2025, 3, 1))->toArray(),
            $converter->convert($instant, $calendar)->toArray(),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('the compact path must not read calendar provenance');

        $converter->convertWithMetadata($instant, $calendar);
    }

    /**
     * convertWithMetadata() reads calendar provenance exactly once.
     *
     * @return void
     */
    public function testMetadataPathReadsCalendarProvenanceOnce(): void
    {
        $calendar = new class (new TabularCalendar()) implements HijriCalendar, DescribesCalendar {
            /** @var int Recorded provenance reads. */
            public int $provenanceCalls = 0;

            /**
             * Bind the delegate.
             *
             * @param HijriCalendar $inner Calendar providing the numeric behaviour.
             */
            public function __construct(private readonly HijriCalendar $inner) {}

            /**
             * Delegate a Gregorian conversion.
             *
             * @param CivilDate $date Gregorian day.
             * @return HijriDate Converted date.
             */
            public function fromGregorian(CivilDate $date): HijriDate
            {
                return $this->inner->fromGregorian($date);
            }

            /**
             * Delegate a Hijri conversion.
             *
             * @param HijriDate $date Hijri date.
             * @return CivilDate Gregorian day.
             */
            public function toGregorian(HijriDate $date): CivilDate
            {
                return $this->inner->toGregorian($date);
            }

            /**
             * Delegate the month length.
             *
             * @param int $year Hijri year.
             * @param int $month Hijri month.
             * @return int Month length.
             */
            public function monthLength(int $year, int $month): int
            {
                return $this->inner->monthLength($year, $month);
            }

            /**
             * Delegate a day shift.
             *
             * @param HijriDate $date Starting date.
             * @param int $days Signed day count.
             * @return HijriDate Shifted date.
             */
            public function shift(HijriDate $date, int $days): HijriDate
            {
                return $this->inner->shift($date, $days);
            }

            /**
             * Report the tabular key.
             *
             * @return string Calendar key.
             */
            public function name(): string
            {
                return 'tabular';
            }

            /**
             * Count the read and describe the tabular rule.
             *
             * @return CalendarProvenance Calendar identity.
             */
            public function calendarProvenance(): CalendarProvenance
            {
                ++$this->provenanceCalls;

                return new CalendarProvenance(
                    'tabular',
                    CalendarFamily::TABULAR,
                    CalendarCoverage::ARITHMETIC_RULE,
                    range: '1900-01-01..2100-12-31 CE',
                );
            }
        };

        $converter = GregorianToHijriConverter::default();
        $instant = new DateTimeImmutable('2025-03-01T12:00:00+00:00');

        $converter->convert($instant, $calendar);
        self::assertSame(0, $calendar->provenanceCalls);

        $converter->convertWithMetadata($instant, $calendar);
        self::assertSame(1, $calendar->provenanceCalls);
    }

    /**
     * The civil frame reports the caller's local date and no astronomy inputs.
     *
     * @return void
     */
    public function testCivilFrameReportsTheLocalDateWithoutProvenanceInputs(): void
    {
        $instant = (new DateTimeImmutable('2024-03-01T20:00:00+00:00'))
            ->setTimezone(new DateTimeZone('Pacific/Honolulu'));

        $outcome = GregorianToHijriConverter::default()->convertWithMetadata(
            $instant,
            new TabularCalendar(),
        );
        $metadata = $outcome->metadata->toArray();

        self::assertSame([
            'instant' => '2024-03-01T20:00:00+00:00',
            'zone' => 'Pacific/Honolulu',
            'frame' => 'civil_date',
            'coordinates' => null,
            'parameters' => null,
        ], $metadata['input']);
        self::assertSame(['gregorian_date' => '2024-03-01'], $metadata['effective']);
        self::assertSame([], $metadata['sources']);
        self::assertSame(['applied' => false, 'kind' => null], $metadata['fallback']);
        self::assertSame('complete', $metadata['status']);
        self::assertSame(['tabular_calendar_estimate'], self::warningCodes($outcome));
    }

    /**
     * The civil frame nulls astronomy inputs even when the caller passes them.
     *
     * @return void
     */
    public function testCivilFrameIgnoresCoordinatesAndParameters(): void
    {
        $instant = new DateTimeImmutable('2024-03-01T20:00:00+00:00');
        $converter = GregorianToHijriConverter::default();
        $calendar = new TabularCalendar();

        $plain = $converter->convertWithMetadata($instant, $calendar);
        $withInputs = $converter->convertWithMetadata(
            $instant,
            $calendar,
            $this->kualaLumpur(),
            $this->parameters(),
        );

        self::assertSame($plain->result->toArray(), $withInputs->result->toArray());
        self::assertNull($withInputs->metadata->toArray()['input']['coordinates']);
        self::assertNull($withInputs->metadata->toArray()['input']['parameters']);
    }

    /**
     * The sunset frame converts the UTC date and reports the maghrib probe.
     *
     * @return void
     */
    public function testSunsetFrameReportsTheUtcDateAndProbeSource(): void
    {
        $instant = (new DateTimeImmutable('2024-03-01T20:00:00+00:00'))
            ->setTimezone(new DateTimeZone('Pacific/Honolulu'));
        $coordinates = $this->kualaLumpur();
        $parameters = $this->parameters();

        $outcome = GregorianToHijriConverter::default()->convertWithMetadata(
            $instant,
            new TabularCalendar(),
            $coordinates,
            $parameters,
            true,
        );
        $metadata = $outcome->metadata->toArray();

        self::assertSame('sunset_rollover', $metadata['input']['frame']);
        self::assertSame('Pacific/Honolulu', $metadata['input']['zone']);
        self::assertSame($coordinates->toArray(), $metadata['input']['coordinates']);
        self::assertSame($parameters->toArray(), $metadata['input']['parameters']);
        self::assertSame(['gregorian_date' => '2024-03-02'], $metadata['effective']);
        self::assertSame([
            [
                'id' => 'hijri.maghrib-probe',
                'kind' => 'algorithm',
                'version' => null,
                'digest' => null,
                'range' => null,
            ],
        ], $metadata['sources']);
        self::assertNotContains('sunset_frame_maghrib_fallback', self::warningCodes($outcome));
        self::assertSame('complete', $metadata['status']);
    }

    /**
     * Both methods require coordinates and parameters for the sunset frame.
     *
     * @param Coordinates|null $coordinates Missing observer in the violating case.
     * @param CalculationParameters|null $parameters Missing policy in the violating case.
     */
    #[DataProvider('missingSunsetInputProvider')]
    public function testSunsetFrameRequiresCoordinatesAndParameters(
        ?Coordinates $coordinates,
        ?CalculationParameters $parameters,
    ): void {
        $converter = GregorianToHijriConverter::default();
        $instant = new DateTimeImmutable('2024-01-02T12:00:00+00:00');
        $calendar = new TabularCalendar();

        self::assertThrows(
            ConfigurationException::class,
            static fn(): HijriDate => $converter->convert($instant, $calendar, $coordinates, $parameters, true),
        );
        self::assertThrows(
            ConfigurationException::class,
            static fn(): HijriCalculationOutcome => $converter->convertWithMetadata($instant, $calendar, $coordinates, $parameters, true),
        );
    }

    /**
     * @return iterable<string, array{Coordinates|null, CalculationParameters|null}>
     */
    public static function missingSunsetInputProvider(): iterable
    {
        yield 'coordinates' => [null, CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters()];
        yield 'parameters' => [Coordinates::fromDegrees(3.1390, 101.6869), null];
    }

    /**
     * Exactly at maghrib the effective date rolls forward.
     *
     * @return void
     */
    public function testMaghribBoundaryRollsForwardInMetadata(): void
    {
        $converter = GregorianToHijriConverter::default();
        $calendar = new TabularCalendar();
        $coordinates = $this->kualaLumpur();
        $parameters = $this->parameters();

        $before = $converter->convertWithMetadata(
            new DateTimeImmutable('2024-01-02T11:14:59+00:00'),
            $calendar,
            $coordinates,
            $parameters,
            true,
        );
        $at = $converter->convertWithMetadata(
            new DateTimeImmutable('2024-01-02T11:15:00+00:00'),
            $calendar,
            $coordinates,
            $parameters,
            true,
        );

        self::assertSame('2024-01-02', $before->metadata->toArray()['effective']['gregorian_date']);
        self::assertSame('2024-01-03', $at->metadata->toArray()['effective']['gregorian_date']);
    }

    /**
     * Before the previous day's maghrib the effective date rolls backward.
     *
     * @return void
     */
    public function testBackwardProbeRollsBackInMetadata(): void
    {
        $converter = GregorianToHijriConverter::default();
        $calendar = new TabularCalendar();
        $coordinates = $this->honolulu();
        $parameters = $this->parameters();

        $before = $converter->convertWithMetadata(
            new DateTimeImmutable('2024-01-02T04:00:59+00:00'),
            $calendar,
            $coordinates,
            $parameters,
            true,
        );
        $at = $converter->convertWithMetadata(
            new DateTimeImmutable('2024-01-02T04:01:00+00:00'),
            $calendar,
            $coordinates,
            $parameters,
            true,
        );

        self::assertSame('2024-01-01', $before->metadata->toArray()['effective']['gregorian_date']);
        self::assertSame('2024-01-02', $at->metadata->toArray()['effective']['gregorian_date']);
    }

    /**
     * At the lower CivilDate edge the backward probe is skipped.
     *
     * @return void
     */
    public function testLowerBoundarySkipsTheBackwardProbeInMetadata(): void
    {
        $outcome = GregorianToHijriConverter::default()->convertWithMetadata(
            new DateTimeImmutable('1900-01-01T06:00:00+00:00'),
            new TabularCalendar(),
            $this->kualaLumpur(),
            $this->parameters(),
            true,
        );
        $metadata = $outcome->metadata->toArray();

        self::assertSame(['gregorian_date' => '1900-01-01'], $metadata['effective']);
        self::assertSame(['applied' => false, 'kind' => null], $metadata['fallback']);
        self::assertSame('complete', $metadata['status']);
    }

    /**
     * A null offset answer is distinct from a real zero answer.
     *
     * @return void
     */
    public function testNoStoreIsDistinctFromAZeroAnswer(): void
    {
        $instant = new DateTimeImmutable('2025-03-01T12:00:00+00:00');
        $calendar = new TabularCalendar();
        $converter = GregorianToHijriConverter::default();

        $none = $converter->convertWithMetadata($instant, $calendar);
        $zero = $converter->convertWithMetadata($instant, $calendar, offsets: $this->offsets(0));
        $two = $converter->convertWithMetadata($instant, $calendar, offsets: $this->offsets(2));

        self::assertSame(['days' => null, 'applied' => false], $none->metadata->toArray()['offsets']);
        self::assertNotContains('hijri.offset.caller', self::sourceIds($none));
        self::assertSame(
            ['adjustment_days' => 0],
            self::warningDetails($none, WarningCode::TABULAR_CALENDAR_ESTIMATE),
        );

        self::assertSame(['days' => 0, 'applied' => true], $zero->metadata->toArray()['offsets']);
        self::assertContains('hijri.offset.caller', self::sourceIds($zero));
        self::assertSame($none->result->toArray(), $zero->result->toArray());
        self::assertSame(
            ['adjustment_days' => 0],
            self::warningDetails($zero, WarningCode::TABULAR_CALENDAR_ESTIMATE),
        );

        self::assertSame(['days' => 2, 'applied' => true], $two->metadata->toArray()['offsets']);
        self::assertSame($calendar->shift($none->result, 2)->toArray(), $two->result->toArray());
        self::assertSame(
            ['adjustment_days' => 2],
            self::warningDetails($two, WarningCode::TABULAR_CALENDAR_ESTIMATE),
        );
    }

    /**
     * A supplied OffsetStore is caller-owned input, not an unidentified source.
     *
     * @return void
     */
    public function testOffsetSourceIsCallerInput(): void
    {
        $outcome = GregorianToHijriConverter::default()->convertWithMetadata(
            new DateTimeImmutable('2025-03-01T12:00:00+00:00'),
            new TabularCalendar(),
            offsets: $this->offsets(0),
        );

        $source = null;

        foreach ($outcome->metadata->common->sources as $candidate) {
            if ($candidate->id === GregorianToHijriConverter::OFFSET_SOURCE_ID) {
                $source = $candidate;
            }
        }

        self::assertNotNull($source);
        self::assertSame([
            'id' => 'hijri.offset.caller',
            'kind' => 'caller_input',
            'version' => null,
            'digest' => null,
            'range' => null,
        ], $source->toArray());
    }

    /**
     * The maghrib-only fallback is reported even when the date is unchanged.
     *
     * @return void
     */
    public function testMaghribOnlyFallbackIsVisibleWhenTheDateIsUnchanged(): void
    {
        $outcome = self::tromsoOutcome('2025-11-27T08:00:00+00:00');
        $metadata = $outcome->metadata->toArray();

        self::assertSame(['applied' => true, 'kind' => 'maghrib_only'], $metadata['fallback']);
        self::assertSame('fallback_applied', $metadata['status']);
        self::assertSame(['gregorian_date' => '2025-11-27'], $metadata['effective']);
        self::assertSame(
            ['date' => '2025-11-27', 'reason' => 'full_schedule_undefined'],
            self::warningDetails($outcome, WarningCode::SUNSET_FRAME_MAGHRIB_FALLBACK),
        );
        self::assertContains('hijri.maghrib-probe', self::sourceIds($outcome));
    }

    /**
     * A fallback that rolls over names the effective Gregorian date.
     *
     * @return void
     */
    public function testMaghribOnlyFallbackWithRolloverReportsTheEffectiveDate(): void
    {
        $outcome = self::tromsoOutcome('2025-11-27T20:00:00+00:00');
        $metadata = $outcome->metadata->toArray();

        self::assertSame(['gregorian_date' => '2025-11-28'], $metadata['effective']);
        self::assertSame('fallback_applied', $metadata['status']);
        self::assertSame(
            ['date' => '2025-11-28', 'reason' => 'full_schedule_undefined'],
            self::warningDetails($outcome, WarningCode::SUNSET_FRAME_MAGHRIB_FALLBACK),
        );
    }

    /**
     * A normal sunset frame applies no fallback.
     *
     * @return void
     */
    public function testNormalSunsetFrameHasNoFallback(): void
    {
        $outcome = GregorianToHijriConverter::default()->convertWithMetadata(
            new DateTimeImmutable('2024-03-01T20:00:00+00:00'),
            new TabularCalendar(),
            $this->kualaLumpur(),
            $this->parameters(),
            true,
        );

        self::assertSame(['applied' => false, 'kind' => null], $outcome->metadata->toArray()['fallback']);
        self::assertNotContains('sunset_frame_maghrib_fallback', self::warningCodes($outcome));
        self::assertSame('complete', $outcome->metadata->toArray()['status']);
    }

    /**
     * A genuinely undefined maghrib still propagates from both methods.
     *
     * @return void
     */
    public function testUndefinedMaghribStillPropagates(): void
    {
        $converter = GregorianToHijriConverter::default();
        $instant = new DateTimeImmutable('2025-06-21T12:00:00+00:00');
        $coordinates = Coordinates::fromDegrees(69.6496, 18.9553);
        $parameters = $this->parameters()->withPolarCircleRule(PolarCircleRule::NONE);

        self::assertThrows(
            AstronomicalException::class,
            static fn(): HijriDate => $converter->convert($instant, new TabularCalendar(), $coordinates, $parameters, true),
        );
        self::assertThrows(
            AstronomicalException::class,
            static fn(): HijriCalculationOutcome => $converter->convertWithMetadata($instant, new TabularCalendar(), $coordinates, $parameters, true),
        );
    }

    /**
     * The fallback flag aggregates the forward and backward probes.
     *
     * @return void
     */
    public function testFallbackFlagAggregatesAcrossForwardAndBackwardProbes(): void
    {
        $real = PrayerTimesCalculator::default();
        $calculator = new class ($real) implements PrayerTimesCalculatorInterface {
            /**
             * Bind the real pipeline.
             *
             * @param PrayerTimesCalculatorInterface $real Delegated pipeline.
             */
            public function __construct(private readonly PrayerTimesCalculatorInterface $real) {}

            /**
             * Delegate every full schedule except the backward-probe fixture.
             *
             * @param Coordinates $coordinates Observer location.
             * @param CivilDate $date Civil date to compute.
             * @param CalculationParameters $parameters Method defaults + overrides.
             * @return PrayerSchedule All instants in UTC.
             */
            public function calculate(Coordinates $coordinates, CivilDate $date, CalculationParameters $parameters): PrayerSchedule
            {
                if ($date->isoformat() === '2024-01-01') {
                    throw new UndefinedScheduleException('full schedule undefined for the backward probe');
                }

                return $this->real->calculate($coordinates, $date, $parameters);
            }

            /**
             * Delegate the maghrib-only fallback.
             *
             * @param Coordinates $coordinates Observer location.
             * @param CivilDate $date Civil date to compute.
             * @param CalculationParameters $parameters Method defaults + overrides.
             * @return DateTimeImmutable Maghrib instant (UTC).
             */
            public function maghribAt(Coordinates $coordinates, CivilDate $date, CalculationParameters $parameters): DateTimeImmutable
            {
                return $this->real->maghribAt($coordinates, $date, $parameters);
            }
        };

        $honolulu = $this->honolulu();
        $instant = $this->maghrib($honolulu, CivilDate::fromYmd(2024, 1, 1))
            ->sub(new \DateInterval('PT1S'));

        $outcome = (new GregorianToHijriConverter($calculator))->convertWithMetadata(
            $instant,
            new TabularCalendar(),
            $honolulu,
            $this->parameters(),
            true,
        );
        $metadata = $outcome->metadata->toArray();

        self::assertSame(['gregorian_date' => '2024-01-01'], $metadata['effective']);
        self::assertSame(['applied' => true, 'kind' => 'maghrib_only'], $metadata['fallback']);
        self::assertSame(
            ['date' => '2024-01-01', 'reason' => 'full_schedule_undefined'],
            self::warningDetails($outcome, WarningCode::SUNSET_FRAME_MAGHRIB_FALLBACK),
        );
    }

    /**
     * A calendar without DescribesCalendar is reported as an unknown component.
     *
     * @return void
     */
    public function testCustomCalendarWithoutDescribesCalendarIsReportedAsUnknown(): void
    {
        $calendar = $this->plainCalendar(new TabularCalendar(), 'custom');
        $outcome = GregorianToHijriConverter::default()->convertWithMetadata(
            new DateTimeImmutable('2025-03-01T12:00:00+00:00'),
            $calendar,
        );
        $metadata = $outcome->metadata->toArray();

        self::assertSame([
            'key' => 'custom',
            'family' => null,
            'coverage' => null,
            'authority' => null,
            'range' => null,
            'digest' => null,
            'source' => null,
            'license' => null,
        ], $metadata['calendar']);
        self::assertSame(['unidentified_calculation_source'], self::warningCodes($outcome));
        self::assertSame(
            ['component' => 'calendar', 'source_id' => 'custom.calendar'],
            self::warningDetails($outcome, WarningCode::UNIDENTIFIED_CALCULATION_SOURCE),
        );
        self::assertSame([], $metadata['sources']);
        self::assertSame('complete', $metadata['status']);
    }

    /**
     * A custom generated table is labelled from its reported coverage.
     *
     * @return void
     */
    public function testCustomGeneratedCalendarIsLabelledHonestly(): void
    {
        $provenance = new CalendarProvenance(
            'custom-generated',
            CalendarFamily::MABIMS,
            CalendarCoverage::GENERATED_TABLE,
            authority: 'XX',
            range: '1445-01..1500-12 AH',
            digest: str_repeat('a', 64),
            source: 'test generator',
            license: 'MIT',
        );
        $calendar = $this->describedCalendar(new TabularCalendar(), 'custom-generated', $provenance);

        $outcome = GregorianToHijriConverter::default()->convertWithMetadata(
            new DateTimeImmutable('2025-03-01T12:00:00+00:00'),
            $calendar,
        );

        self::assertSame(['generated_calendar_table'], self::warningCodes($outcome));
        self::assertSame(
            ['calendar' => 'custom-generated', 'range' => '1445-01..1500-12 AH'],
            self::warningDetails($outcome, WarningCode::GENERATED_CALENDAR_TABLE),
        );
        self::assertSame('complete', $outcome->metadata->toArray()['status']);
    }

    /**
     * An official table emits no coverage warning.
     *
     * @return void
     */
    public function testOfficialTableCoverageEmitsNoCoverageWarning(): void
    {
        $provenance = new CalendarProvenance(
            'official',
            CalendarFamily::UMM_AL_QURA,
            CalendarCoverage::OFFICIAL_TABLE,
            authority: 'SA',
            range: '1423-01..1500-12 AH',
            digest: str_repeat('b', 64),
            source: 'Official test dataset',
            license: 'Public domain',
        );
        $calendar = $this->describedCalendar(new TabularCalendar(), 'official', $provenance);

        $outcome = GregorianToHijriConverter::default()->convertWithMetadata(
            new DateTimeImmutable('2025-03-01T12:00:00+00:00'),
            $calendar,
        );

        self::assertSame([], self::warningCodes($outcome));
        self::assertSame('complete', $outcome->metadata->toArray()['status']);
    }

    /**
     * The calendar key is preserved even outside the CalculationSource grammar.
     *
     * @return void
     */
    public function testCustomCalendarKeyMayUseNonSourceGrammar(): void
    {
        $provenance = new CalendarProvenance('My Calendar', null, null);
        $calendar = $this->describedCalendar(new TabularCalendar(), 'My Calendar', $provenance);

        $outcome = GregorianToHijriConverter::default()->convertWithMetadata(
            new DateTimeImmutable('2025-03-01T12:00:00+00:00'),
            $calendar,
        );

        self::assertSame('My Calendar', $outcome->metadata->toArray()['calendar']['key']);
    }

    /**
     * The sunset outcome matches the full wire shape.
     *
     * @return void
     */
    public function testSunsetOutcomeMatchesTheFullWireShape(): void
    {
        $calendar = new MabimsCalendar(MabimsCountry::MY);
        $instant = new DateTimeImmutable('2026-11-14 20:00:00', new DateTimeZone('Asia/Kuala_Lumpur'));
        $parameters = CalculationMethod::SINGAPORE->defaultParameters();
        $coordinates = $this->kualaLumpur();

        $outcome = GregorianToHijriConverter::default()->convertWithMetadata(
            $instant,
            $calendar,
            $coordinates,
            $parameters,
            true,
            $this->offsets(0),
        );
        $wrapper = $outcome->toArray();
        $metadata = $outcome->metadata->toArray();

        self::assertSame(['result', 'metadata'], array_keys($wrapper));
        self::assertSame(
            $calendar->fromGregorian(CivilDate::fromYmd(2026, 11, 15))->toArray(),
            $wrapper['result'],
        );
        self::assertSame([
            'schema_version',
            'context',
            'status',
            'library_version',
            'warnings',
            'sources',
            'input',
            'effective',
            'calendar',
            'offsets',
            'fallback',
        ], array_keys($metadata));
        self::assertSame('hijri', $metadata['context']);
        self::assertSame('complete', $metadata['status']);
        self::assertSame([
            'instant' => '2026-11-14T12:00:00+00:00',
            'zone' => 'Asia/Kuala_Lumpur',
            'frame' => 'sunset_rollover',
            'coordinates' => $coordinates->toArray(),
            'parameters' => $parameters->toArray(),
        ], $metadata['input']);
        self::assertSame(['gregorian_date' => '2026-11-15'], $metadata['effective']);
        self::assertSame([
            [
                'id' => 'hijri.maghrib-probe',
                'kind' => 'algorithm',
                'version' => null,
                'digest' => null,
                'range' => null,
            ],
            [
                'id' => 'hijri.offset.caller',
                'kind' => 'caller_input',
                'version' => null,
                'digest' => null,
                'range' => null,
            ],
        ], $metadata['sources']);
        self::assertSame(['generated_calendar_table'], self::warningCodes($outcome));
        self::assertSame(
            ['calendar' => 'mabims', 'range' => '1445-01..1500-12 AH'],
            self::warningDetails($outcome, WarningCode::GENERATED_CALENDAR_TABLE),
        );
        self::assertSame([
            'key' => 'mabims',
            'family' => 'mabims',
            'coverage' => 'generated_table',
            'authority' => 'MY',
            'range' => '1445-01..1500-12 AH',
            'digest' => MabimsMonthStarts::digestFor(MabimsCountry::MY),
            'source' => 'tools/gen_hijri.py @ a7baa8b202790d3bc84c47a610ac763be6c0b7e7',
            'license' => 'MIT',
        ], $metadata['calendar']);
        self::assertSame(['days' => 0, 'applied' => true], $metadata['offsets']);
        self::assertSame(['applied' => false, 'kind' => null], $metadata['fallback']);
    }

    /**
     * Warnings are sorted by code and the highest-ranked status wins.
     *
     * @return void
     */
    public function testWarningOrderIsDeterministic(): void
    {
        $parameters = $this->parameters()->withPolarCircleRule(PolarCircleRule::NONE);

        $outcome = GregorianToHijriConverter::default()->convertWithMetadata(
            new DateTimeImmutable('2025-11-27T20:00:00+00:00'),
            new TabularCalendar(),
            Coordinates::fromDegrees(69.6496, 18.9553),
            $parameters,
            true,
            $this->offsets(0),
        );

        self::assertSame(
            ['sunset_frame_maghrib_fallback', 'tabular_calendar_estimate'],
            self::warningCodes($outcome),
        );
        self::assertSame('fallback_applied', $outcome->metadata->toArray()['status']);
    }

    /**
     * Identical inputs produce identical metadata.
     *
     * @return void
     */
    public function testMetadataIsDeterministic(): void
    {
        $calendar = new MabimsCalendar(MabimsCountry::MY);
        $instant = new DateTimeImmutable('2026-11-14 20:00:00', new DateTimeZone('Asia/Kuala_Lumpur'));
        $parameters = CalculationMethod::SINGAPORE->defaultParameters();
        $converter = GregorianToHijriConverter::default();

        $first = $converter->convertWithMetadata(
            $instant,
            $calendar,
            $this->kualaLumpur(),
            $parameters,
            true,
            $this->offsets(0),
        );
        $second = $converter->convertWithMetadata(
            $instant,
            $calendar,
            $this->kualaLumpur(),
            $parameters,
            true,
            $this->offsets(0),
        );

        self::assertSame($first->toArray(), $second->toArray());
    }

    /**
     * The wire payload is JSON-encodable and exposes no internal types.
     *
     * @return void
     */
    public function testJsonEncodingSucceedsAndLeaksNoInternals(): void
    {
        $outcome = GregorianToHijriConverter::default()->convertWithMetadata(
            new DateTimeImmutable('2026-11-14 20:00:00', new DateTimeZone('Asia/Kuala_Lumpur')),
            new MabimsCalendar(MabimsCountry::MY),
            $this->kualaLumpur(),
            CalculationMethod::SINGAPORE->defaultParameters(),
            true,
            $this->offsets(0),
        );

        $json = json_encode($outcome->toArray(), JSON_THROW_ON_ERROR);

        self::assertIsString($json);
        self::assertStringNotContainsString('AlFalaq\\', $json);
        self::assertStringNotContainsString('HijriCalendar', $json);
        self::assertStringNotContainsString('DateTime', $json);
        self::assertStringNotContainsString('Astro', $json);
    }

    /**
     * The outcome wraps the compact payload and its own metadata.
     *
     * @return void
     */
    public function testOutcomeToArrayWrapsTheCompactPayload(): void
    {
        $instant = new DateTimeImmutable('2025-03-01T12:00:00+00:00');
        $calendar = new TabularCalendar();
        $converter = GregorianToHijriConverter::default();

        $outcome = $converter->convertWithMetadata($instant, $calendar, offsets: $this->offsets(0));

        self::assertSame(
            $converter->convert($instant, $calendar, offsets: $this->offsets(0))->toArray(),
            $outcome->toArray()['result'],
        );
        self::assertSame($outcome->metadata->toArray(), $outcome->toArray()['metadata']);
    }
}
