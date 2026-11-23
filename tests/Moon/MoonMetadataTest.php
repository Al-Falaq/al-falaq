<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Moon;

use AlFalaq\AlFalaq\Astro\Contracts\SolarDayEngine;
use AlFalaq\AlFalaq\Astro\Services\CrescentEphemeris;
use AlFalaq\AlFalaq\Astro\Services\MeanSolarCalculator;
use AlFalaq\AlFalaq\Astro\ValueObjects\SolarTime;
use AlFalaq\AlFalaq\Moon\Contracts\ConjunctionSearch;
use AlFalaq\AlFalaq\Moon\Contracts\CrescentEvaluator;
use AlFalaq\AlFalaq\Moon\Services\CrescentCalculator;
use AlFalaq\AlFalaq\Moon\Services\EphemerisConjunctionSearch;
use AlFalaq\AlFalaq\Moon\Strategies\Mabims1992Evaluator;
use AlFalaq\AlFalaq\Moon\Strategies\NeoMabims2021Evaluator;
use AlFalaq\AlFalaq\Moon\Strategies\OdehEvaluator;
use AlFalaq\AlFalaq\Moon\Strategies\YallopEvaluator;
use AlFalaq\AlFalaq\Moon\ValueObjects\CrescentGeometry;
use AlFalaq\AlFalaq\Moon\ValueObjects\CrescentVerdict;
use AlFalaq\AlFalaq\Moon\ValueObjects\MoonCalculationMetadata;
use AlFalaq\AlFalaq\SharedKernel\Contracts\IdentifiesCalculationSource;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CalculationSource;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\LibraryVersion;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Moon geometry metadata: sources, ΔT origin, extrapolation, no-moonset.
 */
final class MoonMetadataTest extends TestCase
{
    private static function kualaLumpur(): Coordinates
    {
        return Coordinates::fromDegrees(3.1390, 101.6869);
    }

    private static function date(): CivilDate
    {
        return CivilDate::fromYmd(2025, 2, 28);
    }

    public function testCalculatedGeometryIsCompleteWithTheRegistrySourceSet(): void
    {
        $outcome = CrescentCalculator::default()->atSunsetWithMetadata(self::kualaLumpur(), self::date());
        $array = $outcome->metadata->toArray();

        self::assertSame('1.0', $array['schema_version']);
        self::assertSame('moon', $array['context']);
        self::assertSame('complete', $array['status']);
        self::assertSame(LibraryVersion::CURRENT, $array['library_version']);
        self::assertSame([], $array['warnings']);
        self::assertSame([
            ['id' => 'moon.conjunction-search.meeus-hourly', 'kind' => 'algorithm', 'version' => 'meeus-hourly', 'digest' => null, 'range' => null],
            ['id' => 'moon.delta-t.espenak-2005-2050', 'kind' => 'algorithm', 'version' => null, 'digest' => null, 'range' => '2005..2050'],
            ['id' => 'moon.ephemeris.meeus-2ed', 'kind' => 'algorithm', 'version' => 'meeus-2ed', 'digest' => null, 'range' => null],
            ['id' => 'solar.mean-meeus', 'kind' => 'algorithm', 'version' => 'meeus-2ed', 'digest' => null, 'range' => null],
        ], $array['sources']);
        self::assertSame([
            'coordinates' => self::kualaLumpur()->toArray(),
            'date' => '2025-02-28',
        ], $array['input']);
        self::assertSame('provider', $array['delta_t']['origin'] ?? null);
        self::assertEqualsWithDelta(74.56, $array['delta_t']['seconds'] ?? NAN, 1e-2);
        self::assertSame(2005.0, $array['delta_t']['calibrated_from_year'] ?? null);
        self::assertSame(2050.0, $array['delta_t']['calibrated_to_year'] ?? null);
        self::assertSame('calculated', $array['geometry_source']);
        self::assertSame([], $array['criteria']);

        $json = json_encode($outcome->toArray(), JSON_THROW_ON_ERROR);

        self::assertStringNotContainsString('Astro\\', $json);
    }

    public function testGeometryResultEqualsTheLegacySunset(): void
    {
        $calculator = CrescentCalculator::default();

        self::assertSame(
            $calculator->atSunset(self::kualaLumpur(), self::date())->toArray(),
            $calculator->atSunsetWithMetadata(self::kualaLumpur(), self::date())->result->toArray(),
        );
    }

    public function testGeometryMetadataAcceptsACallerDeltaTOverride(): void
    {
        $outcome = CrescentCalculator::default()->atSunsetWithMetadata(self::kualaLumpur(), self::date(), 73.0);
        $array = $outcome->metadata->toArray();
        $ids = self::sourceIds($outcome->metadata->common->sources);

        self::assertSame([
            'seconds' => 73.0,
            'origin' => 'override',
            'calibrated_from_year' => null,
            'calibrated_to_year' => null,
        ], $array['delta_t']);
        self::assertContains('caller.delta-t', $ids);
        self::assertNotContains('moon.delta-t.espenak-2005-2050', $ids);
        self::assertSame('caller_input', self::sourceById($outcome->metadata->common->sources, 'caller.delta-t')['kind']);
        self::assertSame([], $array['warnings']);
        self::assertSame('complete', $array['status']);
    }

    public function testNonFiniteDeltaTOverrideStillThrows(): void
    {
        $calculator = CrescentCalculator::default();

        foreach ([NAN, INF] as $value) {
            self::assertThrows(
                ValidationException::class,
                static fn() => $calculator->atSunset(self::kualaLumpur(), self::date(), $value),
            );
            self::assertThrows(
                ValidationException::class,
                static fn() => $calculator->atSunsetWithMetadata(self::kualaLumpur(), self::date(), $value),
            );
        }
    }

    /**
     * @param int $year Out-of-range year.
     * @param int $month Month.
     * @param int $day Day.
     */
    #[DataProvider('outsideRangeProvider')]
    public function testProviderOutsideTheCalibratedRangeIsExtrapolated(int $year, int $month, int $day): void
    {
        $outcome = CrescentCalculator::default()->atSunsetWithMetadata(
            self::kualaLumpur(),
            CivilDate::fromYmd($year, $month, $day),
        );
        $array = $outcome->metadata->toArray();

        self::assertSame('extrapolated', $array['status']);
        self::assertCount(1, $array['warnings']);
        self::assertSame('delta_t_extrapolated', $array['warnings'][0]['code']);
        self::assertIsFloat($array['warnings'][0]['details']['year'] ?? null);
        self::assertSame(2005.0, $array['warnings'][0]['details']['calibrated_from_year'] ?? null);
        self::assertSame(2050.0, $array['warnings'][0]['details']['calibrated_to_year'] ?? null);
        self::assertContains('moon.delta-t.espenak-2005-2050', self::sourceIds($outcome->metadata->common->sources));
    }

    /**
     * @return iterable<string, array{int, int, int}>
     */
    public static function outsideRangeProvider(): iterable
    {
        yield 'after' => [2051, 1, 1];
        yield 'before' => [2004, 12, 31];
    }

    public function testFractionalYearBoundaryIsExplicit(): void
    {
        $calculator = CrescentCalculator::default();

        $lower = $calculator->atSunsetWithMetadata(self::kualaLumpur(), CivilDate::fromYmd(2005, 1, 1));

        self::assertSame('complete', $lower->metadata->common->status->value);
        self::assertSame([], $lower->metadata->common->warnings);

        self::assertSame(
            'complete',
            $calculator->atSunsetWithMetadata(self::kualaLumpur(), CivilDate::fromYmd(2050, 1, 1))
                ->metadata->common->status->value,
        );
        self::assertSame(
            'extrapolated',
            $calculator->atSunsetWithMetadata(self::kualaLumpur(), CivilDate::fromYmd(2050, 1, 2))
                ->metadata->common->status->value,
        );
    }

    public function testOverrideSuppressesTheExtrapolationWarning(): void
    {
        $outcome = CrescentCalculator::default()->atSunsetWithMetadata(
            self::kualaLumpur(),
            CivilDate::fromYmd(2051, 1, 1),
            73.0,
        );

        self::assertSame('complete', $outcome->metadata->common->status->value);
        self::assertSame([], $outcome->metadata->common->warnings);
        self::assertContains('caller.delta-t', self::sourceIds($outcome->metadata->common->sources));
    }

    public function testNoMoonsetIsIndeterminateWithAWarning(): void
    {
        $outcome = CrescentCalculator::default()->atSunsetWithMetadata(
            Coordinates::fromDegrees(69.6496, 18.9553),
            CivilDate::fromYmd(2025, 1, 15),
        );
        $array = $outcome->metadata->toArray();

        self::assertNull($outcome->result->lagMinutes);
        self::assertNull($outcome->result->ageAtMoonsetHours);
        self::assertSame('indeterminate', $array['status']);
        self::assertCount(1, $array['warnings']);
        self::assertSame('moonset_indeterminate', $array['warnings'][0]['code']);
        self::assertSame([
            'date' => '2025-01-15',
            'reason' => 'no_moonset_on_civil_date',
        ], $array['warnings'][0]['details']);
    }

    public function testUnidentifiedSolarEngineIsReportedAsCustomWithAWarning(): void
    {
        $outcome = self::calculator(solar: self::delegatingSolarEngine())
            ->atSunsetWithMetadata(self::kualaLumpur(), self::date());
        $array = $outcome->metadata->toArray();

        self::assertContains('custom.solar', self::sourceIds($outcome->metadata->common->sources));
        self::assertNotContains('solar.mean-meeus', self::sourceIds($outcome->metadata->common->sources));
        self::assertCount(1, $array['warnings']);
        self::assertSame([
            'code' => 'unidentified_calculation_source',
            'message' => $array['warnings'][0]['message'],
            'details' => ['component' => 'solar', 'source_id' => 'custom.solar'],
        ], $array['warnings'][0]);
        self::assertSame('complete', $array['status']);
    }

    public function testUnidentifiedConjunctionSearchIsReportedAsCustomWithAWarning(): void
    {
        $outcome = self::calculator(search: self::delegatingSearch(false))
            ->atSunsetWithMetadata(self::kualaLumpur(), self::date());
        $array = $outcome->metadata->toArray();

        self::assertContains('custom.conjunction_search', self::sourceIds($outcome->metadata->common->sources));
        self::assertCount(1, $array['warnings']);
        self::assertSame('unidentified_calculation_source', $array['warnings'][0]['code']);
        self::assertSame(
            ['component' => 'conjunction_search', 'source_id' => 'custom.conjunction_search'],
            $array['warnings'][0]['details'],
        );
    }

    public function testIdentifiedCustomConjunctionSearchIsReportedWithoutAWarning(): void
    {
        $outcome = self::calculator(search: self::delegatingSearch(true))
            ->atSunsetWithMetadata(self::kualaLumpur(), self::date());

        self::assertContains('custom.test-search', self::sourceIds($outcome->metadata->common->sources));
        self::assertSame([], $outcome->metadata->common->warnings);
    }

    public function testEvaluationOutcomeIsDecisionRequiredWithCriteriaSources(): void
    {
        $calculator = CrescentCalculator::default();
        $outcome = $calculator->evaluateWithMetadata(self::kualaLumpur(), self::date());
        $array = $outcome->toArray();
        $metadata = $outcome->metadata->toArray();

        self::assertSame('moon', $metadata['context']);
        self::assertSame('decision_required', $metadata['status']);
        self::assertSame(MoonCalculationMetadata::CRITERIA, $metadata['criteria']);
        self::assertSame('calculated', $metadata['geometry_source']);
        self::assertCount(1, $metadata['warnings']);
        self::assertSame('crescent_criterion_is_not_observation', $metadata['warnings'][0]['code']);
        self::assertSame(
            ['criteria' => 'yallop,odeh,mabims1992,neomabims2021'],
            $metadata['warnings'][0]['details'],
        );
        self::assertSame([
            'moon.conjunction-search.meeus-hourly',
            'moon.criterion.mabims-1992',
            'moon.criterion.neo-mabims-2021',
            'moon.criterion.odeh',
            'moon.criterion.yallop',
            'moon.delta-t.espenak-2005-2050',
            'moon.ephemeris.meeus-2ed',
            'solar.mean-meeus',
        ], self::sourceIds($outcome->metadata->common->sources));

        foreach ($outcome->metadata->common->sources as $source) {
            self::assertSame('algorithm', $source->kind->value);
        }

        self::assertSame(
            $calculator->atSunset(self::kualaLumpur(), self::date())->toArray(),
            $array['result']['geometry'],
        );
        self::assertSame(
            MoonCalculationMetadata::CRITERIA,
            array_keys($array['result']['verdicts']),
        );

        foreach ($calculator->evaluate(self::kualaLumpur(), self::date()) as $criterion => $verdict) {
            self::assertSame($verdict->toArray(), $array['result']['verdicts'][$criterion]);
        }

        $json = json_encode($array, JSON_THROW_ON_ERROR);

        self::assertStringNotContainsString('Astro\\', $json);
    }

    public function testEvaluationResultEqualsTheLegacyEvaluation(): void
    {
        $calculator = CrescentCalculator::default();
        $outcome = $calculator->evaluateWithMetadata(self::kualaLumpur(), self::date());

        self::assertSame(
            $calculator->atSunset(self::kualaLumpur(), self::date())->toArray(),
            $outcome->result->toArray(),
        );
        self::assertEquals(
            $calculator->evaluate(self::kualaLumpur(), self::date()),
            $outcome->verdicts,
        );
    }

    public function testEvaluationPropagatesDeltaTAndNoMoonsetWarnings(): void
    {
        $calculator = CrescentCalculator::default();

        $extrapolated = $calculator->evaluateWithMetadata(
            self::kualaLumpur(),
            CivilDate::fromYmd(2051, 1, 1),
        )->metadata->toArray();

        self::assertSame('decision_required', $extrapolated['status']);
        self::assertSame(
            ['crescent_criterion_is_not_observation', 'delta_t_extrapolated'],
            array_column($extrapolated['warnings'], 'code'),
        );

        $noMoonset = $calculator->evaluateWithMetadata(
            Coordinates::fromDegrees(69.6496, 18.9553),
            CivilDate::fromYmd(2025, 1, 15),
        )->metadata->toArray();

        self::assertSame('decision_required', $noMoonset['status']);
        self::assertSame(
            ['crescent_criterion_is_not_observation', 'moonset_indeterminate'],
            array_column($noMoonset['warnings'], 'code'),
        );
    }

    public function testCallerSuppliedGeometryDoesNotInventProvenance(): void
    {
        $calculator = CrescentCalculator::default();
        $geometry = $calculator->atSunset(self::kualaLumpur(), self::date());
        $outcome = $calculator->evaluateGeometryWithMetadata($geometry);
        $array = $outcome->metadata->toArray();

        self::assertSame(['coordinates' => null, 'date' => null], $array['input']);
        self::assertNull($array['delta_t']);
        self::assertSame('caller_supplied', $array['geometry_source']);
        self::assertSame(MoonCalculationMetadata::CRITERIA, $array['criteria']);
        self::assertSame([
            'moon.criterion.mabims-1992',
            'moon.criterion.neo-mabims-2021',
            'moon.criterion.odeh',
            'moon.criterion.yallop',
        ], self::sourceIds($outcome->metadata->common->sources));
        self::assertSame('decision_required', $array['status']);
        self::assertSame('crescent_criterion_is_not_observation', $array['warnings'][0]['code']);
    }

    public function testCallerSuppliedNoMoonsetStillReportsIndeterminate(): void
    {
        $geometry = new CrescentGeometry(-0.8325, 4.2545, 6.1740, 10.0, null, 1.0279, 0.0029, 6.0880, 0.0953, null);
        $array = CrescentCalculator::default()->evaluateGeometryWithMetadata($geometry)->metadata->toArray();

        self::assertSame('decision_required', $array['status']);
        self::assertSame(
            ['crescent_criterion_is_not_observation', 'moonset_indeterminate'],
            array_column($array['warnings'], 'code'),
        );
        self::assertSame(
            ['date' => null, 'reason' => 'no_moonset_on_civil_date'],
            $array['warnings'][1]['details'],
        );
    }

    public function testCallerSuppliedEvaluationEqualsTheLegacyFanOut(): void
    {
        $calculator = CrescentCalculator::default();
        $geometry = $calculator->atSunset(self::kualaLumpur(), self::date());

        self::assertEquals(
            $calculator->evaluateGeometry($geometry),
            $calculator->evaluateGeometryWithMetadata($geometry)->verdicts,
        );
    }

    public function testUnidentifiedEvaluatorIsReportedAsCustomCriterionWithAWarning(): void
    {
        $outcome = self::calculator(yallop: self::delegatingEvaluator(false))
            ->evaluateWithMetadata(self::kualaLumpur(), self::date());
        $metadata = $outcome->metadata->toArray();
        $ids = self::sourceIds($outcome->metadata->common->sources);
        $unidentified = array_values(array_filter(
            $metadata['warnings'],
            static fn(array $warning): bool => $warning['code'] === 'unidentified_calculation_source',
        ));

        self::assertContains('custom.criterion', $ids);
        self::assertNotContains('moon.criterion.yallop', $ids);
        self::assertContains('moon.criterion.odeh', $ids);
        self::assertContains('moon.criterion.mabims-1992', $ids);
        self::assertContains('moon.criterion.neo-mabims-2021', $ids);
        self::assertCount(1, $unidentified);
        self::assertSame(
            ['component' => 'criterion', 'source_id' => 'custom.criterion'],
            $unidentified[0]['details'],
        );
        self::assertSame(MoonCalculationMetadata::CRITERIA, array_keys($outcome->verdicts));
    }

    public function testIdentifiedCustomEvaluatorIsReportedWithoutAWarning(): void
    {
        $outcome = self::calculator(yallop: self::delegatingEvaluator(true))
            ->evaluateWithMetadata(self::kualaLumpur(), self::date());

        self::assertContains('custom.test-criterion', self::sourceIds($outcome->metadata->common->sources));

        foreach ($outcome->metadata->common->warnings as $warning) {
            self::assertNotSame('unidentified_calculation_source', $warning->code->value);
        }
    }

    /**
     * Build a calculator with optional custom doubles.
     *
     * @param SolarDayEngine|null $solar Solar engine override.
     * @param ConjunctionSearch|null $search Conjunction search override.
     * @param CrescentEvaluator|null $yallop Yallop criterion override.
     * @return CrescentCalculator Wired calculator.
     */
    private static function calculator(
        ?SolarDayEngine $solar = null,
        ?ConjunctionSearch $search = null,
        ?CrescentEvaluator $yallop = null,
    ): CrescentCalculator {
        $ephemeris = new CrescentEphemeris();

        return new CrescentCalculator(
            $solar ?? new MeanSolarCalculator(),
            $ephemeris,
            $search ?? new EphemerisConjunctionSearch($ephemeris),
            $yallop ?? new YallopEvaluator(),
            new OdehEvaluator(),
            new Mabims1992Evaluator(),
            new NeoMabims2021Evaluator(),
        );
    }

    /**
     * Delegating Yallop criterion, optionally with a custom identity.
     *
     * @param bool $identified Whether the double declares a source identity.
     * @return CrescentEvaluator Criterion double.
     */
    private static function delegatingEvaluator(bool $identified): CrescentEvaluator
    {
        $inner = new YallopEvaluator();

        if (! $identified) {
            return new class ($inner) implements CrescentEvaluator {
                public function __construct(private readonly CrescentEvaluator $inner) {}

                public function evaluate(CrescentGeometry $geometry): CrescentVerdict
                {
                    return $this->inner->evaluate($geometry);
                }
            };
        }

        return new class ($inner) implements CrescentEvaluator, IdentifiesCalculationSource {
            public function __construct(private readonly CrescentEvaluator $inner) {}

            public function evaluate(CrescentGeometry $geometry): CrescentVerdict
            {
                return $this->inner->evaluate($geometry);
            }

            public function calculationSource(): CalculationSource
            {
                return CalculationSource::algorithm('custom.test-criterion', 'v1');
            }
        };
    }

    /**
     * Delegating solar engine without a source identity.
     *
     * @return SolarDayEngine Engine double.
     */
    private static function delegatingSolarEngine(): SolarDayEngine
    {
        $inner = new MeanSolarCalculator();

        return new class ($inner) implements SolarDayEngine {
            public function __construct(private readonly SolarDayEngine $inner) {}

            public function solarTime(Coordinates $coordinates, CivilDate $date, mixed $elevationM): SolarTime
            {
                return $this->inner->solarTime($coordinates, $date, $elevationM);
            }

            public function hourAngleFloat(Coordinates $coordinates, CivilDate $date, mixed $elevationM, float $angleDeg, bool $afterTransit): ?float
            {
                return $this->inner->hourAngleFloat($coordinates, $date, $elevationM, $angleDeg, $afterTransit);
            }

            public function hourAngle(Coordinates $coordinates, CivilDate $date, mixed $elevationM, float $angleDeg, bool $afterTransit): ?\DateTimeImmutable
            {
                return $this->inner->hourAngle($coordinates, $date, $elevationM, $angleDeg, $afterTransit);
            }

            public function afternoon(Coordinates $coordinates, CivilDate $date, mixed $elevationM, mixed $shadowLength): ?\DateTimeImmutable
            {
                return $this->inner->afternoon($coordinates, $date, $elevationM, $shadowLength);
            }

            public function isSolarDefined(Coordinates $coordinates, CivilDate $date, mixed $elevationM): bool
            {
                return $this->inner->isSolarDefined($coordinates, $date, $elevationM);
            }
        };
    }

    /**
     * Delegating conjunction search, optionally with a custom identity.
     *
     * @param bool $identified Whether the double declares a source identity.
     * @return ConjunctionSearch Search double.
     */
    private static function delegatingSearch(bool $identified): ConjunctionSearch
    {
        $inner = EphemerisConjunctionSearch::default();

        if (! $identified) {
            return new class ($inner) implements ConjunctionSearch {
                public function __construct(private readonly ConjunctionSearch $inner) {}

                public function ageDays(float $julianDayTt): float
                {
                    return $this->inner->ageDays($julianDayTt);
                }

                public function separation(float $ra1Deg, float $dec1Deg, float $ra2Deg, float $dec2Deg): float
                {
                    return $this->inner->separation($ra1Deg, $dec1Deg, $ra2Deg, $dec2Deg);
                }
            };
        }

        return new class ($inner) implements ConjunctionSearch, IdentifiesCalculationSource {
            public function __construct(private readonly ConjunctionSearch $inner) {}

            public function ageDays(float $julianDayTt): float
            {
                return $this->inner->ageDays($julianDayTt);
            }

            public function separation(float $ra1Deg, float $dec1Deg, float $ra2Deg, float $dec2Deg): float
            {
                return $this->inner->separation($ra1Deg, $dec1Deg, $ra2Deg, $dec2Deg);
            }

            public function calculationSource(): CalculationSource
            {
                return CalculationSource::algorithm('custom.test-search', 'v1');
            }
        };
    }

    /**
     * Ids of the sorted sources in one metadata envelope.
     *
     * @param list<CalculationSource> $sources Sorted sources.
     * @return list<string>
     */
    private static function sourceIds(array $sources): array
    {
        return array_map(static fn(CalculationSource $source): string => $source->id, $sources);
    }

    /**
     * One source's wire shape by id.
     *
     * @param list<CalculationSource> $sources Sorted sources.
     * @param string $id Registry id.
     * @return array{id: string, kind: string, version: string|null, digest: string|null, range: string|null}
     */
    private static function sourceById(array $sources, string $id): array
    {
        foreach ($sources as $source) {
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
}
