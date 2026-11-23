<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Moon;

use AlFalaq\AlFalaq\Astro\Services\DeltaTProvider;
use AlFalaq\AlFalaq\Moon\ValueObjects\CrescentEvaluationOutcome;
use AlFalaq\AlFalaq\Moon\ValueObjects\CrescentGeometry;
use AlFalaq\AlFalaq\Moon\ValueObjects\CrescentGeometryOutcome;
use AlFalaq\AlFalaq\Moon\ValueObjects\CrescentVerdict;
use AlFalaq\AlFalaq\Moon\ValueObjects\MoonCalculationMetadata;
use AlFalaq\AlFalaq\SharedKernel\Enums\CalculationContext;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CalculationMetadata;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CalculationSource;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Moon metadata value objects: ΔT origin, geometry pairing, criteria order.
 */
final class MoonMetadataValueObjectsTest extends TestCase
{
    private static function geometry(): CrescentGeometry
    {
        return new CrescentGeometry(
            -0.8325,
            4.2545,
            6.1740,
            10.0,
            24.7257,
            1.0279,
            0.0029,
            6.0880,
            0.0953,
            10.4121,
        );
    }

    private static function common(): CalculationMetadata
    {
        return CalculationMetadata::create(CalculationContext::MOON, [], [
            CalculationSource::algorithm('moon.ephemeris.meeus-2ed', 'meeus-2ed'),
        ]);
    }

    private static function providerMetadata(): MoonCalculationMetadata
    {
        return new MoonCalculationMetadata(
            self::common(),
            Coordinates::fromDegrees(3.1390, 101.6869),
            CivilDate::fromYmd(2025, 2, 28),
            74.56,
            CalculationSource::algorithm(DeltaTProvider::MODEL_ID, range: DeltaTProvider::MODEL_RANGE),
            MoonCalculationMetadata::GEOMETRY_CALCULATED,
            [],
        );
    }

    private static function overrideMetadata(): MoonCalculationMetadata
    {
        return new MoonCalculationMetadata(
            self::common(),
            Coordinates::fromDegrees(3.1390, 101.6869),
            CivilDate::fromYmd(2025, 2, 28),
            69.0,
            CalculationSource::callerInput('caller.delta-t'),
            MoonCalculationMetadata::GEOMETRY_CALCULATED,
            [],
        );
    }

    private static function callerSuppliedMetadata(): MoonCalculationMetadata
    {
        return new MoonCalculationMetadata(
            self::common(),
            null,
            null,
            null,
            null,
            MoonCalculationMetadata::GEOMETRY_CALLER_SUPPLIED,
            [],
        );
    }

    private static function evaluationMetadata(): MoonCalculationMetadata
    {
        return new MoonCalculationMetadata(
            self::common(),
            Coordinates::fromDegrees(3.1390, 101.6869),
            CivilDate::fromYmd(2025, 2, 28),
            74.56,
            CalculationSource::algorithm(DeltaTProvider::MODEL_ID, range: DeltaTProvider::MODEL_RANGE),
            MoonCalculationMetadata::GEOMETRY_CALCULATED,
            MoonCalculationMetadata::CRITERIA,
        );
    }

    /**
     * @return array{yallop: CrescentVerdict, odeh: CrescentVerdict, mabims1992: CrescentVerdict, neomabims2021: CrescentVerdict}
     */
    private static function verdicts(): array
    {
        return [
            'yallop' => new CrescentVerdict('yallop', true, 1.2, 'A'),
            'odeh' => new CrescentVerdict('odeh', false, 3.1, 'C'),
            'mabims1992' => new CrescentVerdict('mabims1992', true, 9.1, 'visible'),
            'neomabims2021' => new CrescentVerdict('neomabims2021', true, 8.2, 'visible'),
        ];
    }

    public function testCalculatedGeometryMetadataHasTheWireShape(): void
    {
        $array = self::providerMetadata()->toArray();

        self::assertSame([
            'schema_version',
            'context',
            'status',
            'library_version',
            'warnings',
            'sources',
            'input',
            'delta_t',
            'geometry_source',
            'criteria',
        ], array_keys($array));

        self::assertSame('moon', $array['context']);
        self::assertSame('complete', $array['status']);
        self::assertSame([
            'coordinates' => ['latitude' => 3.1390, 'longitude' => 101.6869],
            'date' => '2025-02-28',
        ], $array['input']);
        self::assertSame([
            'seconds' => 74.56,
            'origin' => 'provider',
            'calibrated_from_year' => 2005.0,
            'calibrated_to_year' => 2050.0,
        ], $array['delta_t']);
        self::assertSame('calculated', $array['geometry_source']);
        self::assertSame([], $array['criteria']);
    }

    public function testOverrideMetadataReportsOverrideOriginAndNoCalibratedBounds(): void
    {
        self::assertSame([
            'seconds' => 69.0,
            'origin' => 'override',
            'calibrated_from_year' => null,
            'calibrated_to_year' => null,
        ], self::overrideMetadata()->toArray()['delta_t']);
    }

    public function testCallerSuppliedGeometryMetadataHasNoInputsOrDeltaT(): void
    {
        $array = self::callerSuppliedMetadata()->toArray();

        self::assertSame(['coordinates' => null, 'date' => null], $array['input']);
        self::assertNull($array['delta_t']);
        self::assertSame('caller_supplied', $array['geometry_source']);
        self::assertSame([], $array['criteria']);
    }

    public function testEvaluationMetadataCarriesTheFixedCriteriaOrder(): void
    {
        self::assertSame(
            ['yallop', 'odeh', 'mabims1992', 'neomabims2021'],
            self::evaluationMetadata()->toArray()['criteria'],
        );
    }

    /**
     * @param list<string> $criteria Invalid criteria order.
     */
    #[DataProvider('invalidCriteriaProvider')]
    public function testCriteriaOrderCannotBeReorderedOrTruncated(array $criteria): void
    {
        $this->expectException(ValidationException::class);

        new MoonCalculationMetadata(
            self::common(),
            null,
            null,
            null,
            null,
            MoonCalculationMetadata::GEOMETRY_CALLER_SUPPLIED,
            $criteria,
        );
    }

    /**
     * @return iterable<string, array{list<string>}>
     */
    public static function invalidCriteriaProvider(): iterable
    {
        yield 'reordered' => [['odeh', 'yallop', 'mabims1992', 'neomabims2021']];
        yield 'truncated' => [['yallop']];
        yield 'extra' => [['yallop', 'odeh', 'mabims1992', 'neomabims2021', 'extra']];
    }

    public function testMetadataRejectsAnotherCalculationContext(): void
    {
        $this->expectException(ValidationException::class);

        new MoonCalculationMetadata(
            CalculationMetadata::create(CalculationContext::QIBLA),
            null,
            null,
            null,
            null,
            MoonCalculationMetadata::GEOMETRY_CALLER_SUPPLIED,
            [],
        );
    }

    public function testUnknownGeometrySourceIsRejected(): void
    {
        $this->expectException(ValidationException::class);

        new MoonCalculationMetadata(
            self::common(),
            null,
            null,
            null,
            null,
            'bogus',
            [],
        );
    }

    /**
     * @param Coordinates|null $coordinates Missing coordinates.
     * @param CivilDate|null $date Missing date.
     * @param float|null $deltaTSeconds Missing ΔT.
     * @param CalculationSource|null $deltaTSource Missing ΔT source.
     */
    #[DataProvider('missingCalculatedFieldProvider')]
    public function testCalculatedGeometryRequiresCoordinatesDateDeltaTAndSource(
        ?Coordinates $coordinates,
        ?CivilDate $date,
        ?float $deltaTSeconds,
        ?CalculationSource $deltaTSource,
    ): void {
        $this->expectException(ValidationException::class);

        new MoonCalculationMetadata(
            self::common(),
            $coordinates,
            $date,
            $deltaTSeconds,
            $deltaTSource,
            MoonCalculationMetadata::GEOMETRY_CALCULATED,
            [],
        );
    }

    /**
     * @return iterable<string, array{?Coordinates, ?CivilDate, ?float, ?CalculationSource}>
     */
    public static function missingCalculatedFieldProvider(): iterable
    {
        $coordinates = Coordinates::fromDegrees(3.1390, 101.6869);
        $date = CivilDate::fromYmd(2025, 2, 28);
        $source = CalculationSource::algorithm(DeltaTProvider::MODEL_ID, range: DeltaTProvider::MODEL_RANGE);

        yield 'coordinates' => [null, $date, 74.56, $source];
        yield 'date' => [$coordinates, null, 74.56, $source];
        yield 'delta t' => [$coordinates, $date, null, $source];
        yield 'delta t source' => [$coordinates, $date, 74.56, null];
    }

    /**
     * @param Coordinates|null $coordinates Non-null coordinates.
     * @param CivilDate|null $date Non-null date.
     * @param float|null $deltaTSeconds Non-null ΔT.
     * @param CalculationSource|null $deltaTSource Non-null ΔT source.
     */
    #[DataProvider('callerSuppliedProvenanceProvider')]
    public function testCallerSuppliedGeometryRejectsProvenanceFields(
        ?Coordinates $coordinates,
        ?CivilDate $date,
        ?float $deltaTSeconds,
        ?CalculationSource $deltaTSource,
    ): void {
        $this->expectException(ValidationException::class);

        new MoonCalculationMetadata(
            self::common(),
            $coordinates,
            $date,
            $deltaTSeconds,
            $deltaTSource,
            MoonCalculationMetadata::GEOMETRY_CALLER_SUPPLIED,
            [],
        );
    }

    /**
     * @return iterable<string, array{?Coordinates, ?CivilDate, ?float, ?CalculationSource}>
     */
    public static function callerSuppliedProvenanceProvider(): iterable
    {
        yield 'coordinates' => [Coordinates::fromDegrees(3.1390, 101.6869), null, null, null];
        yield 'date' => [null, CivilDate::fromYmd(2025, 2, 28), null, null];
        yield 'delta t' => [null, null, 74.56, null];
        yield 'delta t source' => [null, null, null, CalculationSource::callerInput('caller.delta-t')];
    }

    /**
     * @param float $value Non-finite ΔT.
     */
    #[DataProvider('nonFiniteProvider')]
    public function testNonFiniteDeltaTSecondsIsRejected(float $value): void
    {
        $this->expectException(ValidationException::class);

        new MoonCalculationMetadata(
            self::common(),
            Coordinates::fromDegrees(3.1390, 101.6869),
            CivilDate::fromYmd(2025, 2, 28),
            $value,
            CalculationSource::algorithm(DeltaTProvider::MODEL_ID, range: DeltaTProvider::MODEL_RANGE),
            MoonCalculationMetadata::GEOMETRY_CALCULATED,
            [],
        );
    }

    /**
     * @return iterable<string, array{float}>
     */
    public static function nonFiniteProvider(): iterable
    {
        yield 'nan' => [NAN];
        yield 'inf' => [INF];
        yield 'negative inf' => [-INF];
    }

    public function testDeltaTSourceKindMustBeAlgorithmOrCallerInput(): void
    {
        $this->expectException(ValidationException::class);

        new MoonCalculationMetadata(
            self::common(),
            Coordinates::fromDegrees(3.1390, 101.6869),
            CivilDate::fromYmd(2025, 2, 28),
            74.56,
            CalculationSource::generatedData('moon.delta-t.x', str_repeat('a', 64), '2005..2050'),
            MoonCalculationMetadata::GEOMETRY_CALCULATED,
            [],
        );
    }

    /**
     * @param CalculationSource $source Algorithm source with an invalid range.
     */
    #[DataProvider('invalidRangeProvider')]
    public function testProviderSourceRequiresAParseableRange(CalculationSource $source): void
    {
        $this->expectException(ValidationException::class);

        new MoonCalculationMetadata(
            self::common(),
            Coordinates::fromDegrees(3.1390, 101.6869),
            CivilDate::fromYmd(2025, 2, 28),
            74.56,
            $source,
            MoonCalculationMetadata::GEOMETRY_CALCULATED,
            [],
        );
    }

    public function testProviderSourceAcceptsAnEqualCalibratedRange(): void
    {
        $metadata = new MoonCalculationMetadata(
            self::common(),
            Coordinates::fromDegrees(3.1390, 101.6869),
            CivilDate::fromYmd(2025, 2, 28),
            74.56,
            CalculationSource::algorithm('moon.delta-t.x', range: '2005..2005'),
            MoonCalculationMetadata::GEOMETRY_CALCULATED,
            [],
        );

        self::assertSame([
            'seconds' => 74.56,
            'origin' => 'provider',
            'calibrated_from_year' => 2005.0,
            'calibrated_to_year' => 2005.0,
        ], $metadata->toArray()['delta_t']);
    }

    /**
     * @return iterable<string, array{CalculationSource}>
     */
    public static function invalidRangeProvider(): iterable
    {
        yield 'missing' => [CalculationSource::algorithm('moon.delta-t.x')];
        yield 'single bound' => [CalculationSource::algorithm('moon.delta-t.x', range: '2005')];
        yield 'reversed' => [CalculationSource::algorithm('moon.delta-t.x', range: '2050..2005')];
    }

    public function testGeometryOutcomeToArrayWrapsResultAndMetadata(): void
    {
        $outcome = new CrescentGeometryOutcome(self::geometry(), self::providerMetadata());
        $array = $outcome->toArray();

        self::assertSame(['result', 'metadata'], array_keys($array));
        self::assertSame(self::geometry()->toArray(), $array['result']);
        self::assertSame(self::providerMetadata()->toArray(), $array['metadata']);
    }

    public function testEvaluationOutcomeToArrayUsesTheFixedVerdictOrder(): void
    {
        $verdicts = self::verdicts();
        $shuffled = [
            'odeh' => $verdicts['odeh'],
            'yallop' => $verdicts['yallop'],
            'neomabims2021' => $verdicts['neomabims2021'],
            'mabims1992' => $verdicts['mabims1992'],
        ];
        $outcome = new CrescentEvaluationOutcome(self::geometry(), $shuffled, self::evaluationMetadata());
        $array = $outcome->toArray();

        self::assertSame(['result', 'metadata'], array_keys($array));
        self::assertSame(['geometry', 'verdicts'], array_keys($array['result']));
        self::assertSame(MoonCalculationMetadata::CRITERIA, array_keys($array['result']['verdicts']));

        foreach ($verdicts as $criterion => $verdict) {
            self::assertSame($verdict->toArray(), $array['result']['verdicts'][$criterion]);
        }
    }

    /**
     * @param array<array-key, mixed> $verdicts Invalid verdict set.
     */
    #[DataProvider('invalidVerdictsProvider')]
    public function testEvaluationOutcomeRejectsMissingExtraOrNonVerdictEntries(array $verdicts): void
    {
        $this->expectException(ValidationException::class);

        (new \ReflectionClass(CrescentEvaluationOutcome::class))->newInstance(
            self::geometry(),
            $verdicts,
            self::evaluationMetadata(),
        );
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>}>
     */
    public static function invalidVerdictsProvider(): iterable
    {
        $valid = self::verdicts();

        yield 'missing' => [[]];
        yield 'extra' => [[...$valid, 'extra' => new CrescentVerdict('extra', true, 0.0, 'A')]];
        yield 'wrong key' => [[
            'yallop' => $valid['yallop'],
            'odeh' => $valid['odeh'],
            'mabims1992' => $valid['mabims1992'],
            'extra' => new CrescentVerdict('extra', true, 0.0, 'A'),
        ]];
        yield 'non verdict' => [[
            'yallop' => new \stdClass(),
            'odeh' => $valid['odeh'],
            'mabims1992' => $valid['mabims1992'],
            'neomabims2021' => $valid['neomabims2021'],
        ]];
    }
}
