<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\SharedKernel\Provenance;

use AlFalaq\AlFalaq\SharedKernel\Enums\CalculationContext;
use AlFalaq\AlFalaq\SharedKernel\Enums\CalculationStatus;
use AlFalaq\AlFalaq\SharedKernel\Enums\WarningCode;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CalculationMetadata;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CalculationSource;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CalculationWarning;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\LibraryVersion;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Metadata envelope: default status, warning-derived ranking, deterministic
 * ordering, de-duplication, and wire key order.
 */
final class CalculationMetadataTest extends TestCase
{
    public function testCreateDefaultsToCompleteAndCurrentLibraryVersion(): void
    {
        $metadata = CalculationMetadata::create(CalculationContext::PRAYER);

        self::assertSame(CalculationContext::PRAYER, $metadata->context);
        self::assertSame(CalculationStatus::COMPLETE, $metadata->status);
        self::assertSame(LibraryVersion::CURRENT, $metadata->libraryVersion->value);
        self::assertSame([], $metadata->warnings);
        self::assertSame([], $metadata->sources);
    }

    public function testCreateAcceptsAnExplicitLibraryVersion(): void
    {
        $metadata = CalculationMetadata::create(
            CalculationContext::MOON,
            [],
            [],
            LibraryVersion::fromString('1.2.3'),
        );

        self::assertSame('1.2.3', $metadata->libraryVersion->value);
    }

    public function testSchemaVersionIsOneZero(): void
    {
        self::assertSame('1.0', CalculationMetadata::SCHEMA_VERSION);
    }

    #[DataProvider('singleWarningStatusProvider')]
    public function testStatusDerivationForSingleWarnings(WarningCode $code, CalculationStatus $expected): void
    {
        $metadata = CalculationMetadata::create(CalculationContext::PRAYER, [self::warning($code)]);

        self::assertSame($expected, $metadata->status);
    }

    /**
     * @return iterable<string, array{WarningCode, CalculationStatus}>
     */
    public static function singleWarningStatusProvider(): iterable
    {
        yield 'delta-t extrapolated' => [WarningCode::DELTA_T_EXTRAPOLATED, CalculationStatus::EXTRAPOLATED];
        yield 'polar fallback' => [WarningCode::POLAR_FALLBACK_APPLIED, CalculationStatus::FALLBACK_APPLIED];
        yield 'maghrib fallback' => [WarningCode::SUNSET_FRAME_MAGHRIB_FALLBACK, CalculationStatus::FALLBACK_APPLIED];
        yield 'moonset indeterminate' => [WarningCode::MOONSET_INDETERMINATE, CalculationStatus::INDETERMINATE];
        yield 'criterion is not observation' => [WarningCode::CRESCENT_CRITERION_IS_NOT_OBSERVATION, CalculationStatus::DECISION_REQUIRED];
        yield 'generated table' => [WarningCode::GENERATED_CALENDAR_TABLE, CalculationStatus::COMPLETE];
        yield 'tabular estimate' => [WarningCode::TABULAR_CALENDAR_ESTIMATE, CalculationStatus::COMPLETE];
        yield 'unidentified source' => [WarningCode::UNIDENTIFIED_CALCULATION_SOURCE, CalculationStatus::COMPLETE];
    }

    public function testStatusUsesTheHighestRankAndKeepsEveryWarning(): void
    {
        $warnings = [
            self::warning(WarningCode::DELTA_T_EXTRAPOLATED),
            self::warning(WarningCode::POLAR_FALLBACK_APPLIED),
            self::warning(WarningCode::MOONSET_INDETERMINATE),
        ];

        $metadata = CalculationMetadata::create(CalculationContext::MOON, $warnings);

        self::assertSame(CalculationStatus::INDETERMINATE, $metadata->status);
        self::assertCount(3, $metadata->warnings);

        $warnings[] = self::warning(WarningCode::CRESCENT_CRITERION_IS_NOT_OBSERVATION);
        $metadata = CalculationMetadata::create(CalculationContext::MOON, $warnings);

        self::assertSame(CalculationStatus::DECISION_REQUIRED, $metadata->status);
        self::assertCount(4, $metadata->warnings);
    }

    public function testWarningsSortByCodeThenMessageThenDetails(): void
    {
        $warnings = [
            self::warning(WarningCode::UNIDENTIFIED_CALCULATION_SOURCE, 'b'),
            self::warning(WarningCode::POLAR_FALLBACK_APPLIED, 'z'),
            self::warning(WarningCode::POLAR_FALLBACK_APPLIED, 'a', ['component' => 'solar']),
            self::warning(WarningCode::POLAR_FALLBACK_APPLIED, 'a', ['component' => 'isha']),
        ];

        $metadata = CalculationMetadata::create(CalculationContext::PRAYER, $warnings);

        self::assertSame([
            ['polar_fallback_applied', 'a', ['component' => 'isha']],
            ['polar_fallback_applied', 'a', ['component' => 'solar']],
            ['polar_fallback_applied', 'z', []],
            ['unidentified_calculation_source', 'b', []],
        ], array_map(
            static fn(CalculationWarning $warning): array => [
                $warning->code->value,
                $warning->message,
                $warning->toArray()['details'],
            ],
            $metadata->warnings,
        ));
    }

    public function testSourcesSortByIdKindAndVersionWithNullLast(): void
    {
        $sources = [
            CalculationSource::algorithm('prayer.polar.nearest_latitude', 'v2'),
            CalculationSource::callerInput('caller.adjustments'),
            CalculationSource::algorithm('prayer.polar.nearest_latitude'),
            CalculationSource::algorithm('prayer.isha.angle'),
            CalculationSource::algorithm('prayer.polar.nearest_latitude', 'v1'),
            CalculationSource::callerInput('prayer.polar.nearest_latitude'),
        ];

        $metadata = CalculationMetadata::create(CalculationContext::PRAYER, [], $sources);

        self::assertSame([
            ['caller.adjustments', 'caller_input', null],
            ['prayer.isha.angle', 'algorithm', null],
            ['prayer.polar.nearest_latitude', 'algorithm', 'v1'],
            ['prayer.polar.nearest_latitude', 'algorithm', 'v2'],
            ['prayer.polar.nearest_latitude', 'algorithm', null],
            ['prayer.polar.nearest_latitude', 'caller_input', null],
        ], array_map(
            static fn(CalculationSource $source): array => [$source->id, $source->kind->value, $source->version],
            $metadata->sources,
        ));
    }

    public function testIdenticalWarningsCollapseToOneEntry(): void
    {
        $warning = self::warning(WarningCode::POLAR_FALLBACK_APPLIED, 'message', ['rule' => 'nearest_latitude']);

        $metadata = CalculationMetadata::create(CalculationContext::PRAYER, [$warning, $warning]);

        self::assertCount(1, $metadata->warnings);
    }

    public function testWarningsWithDifferentComponentsDoNotCollapse(): void
    {
        $metadata = CalculationMetadata::create(CalculationContext::PRAYER, [
            self::warning(WarningCode::UNIDENTIFIED_CALCULATION_SOURCE, 'message', ['component' => 'solar']),
            self::warning(WarningCode::UNIDENTIFIED_CALCULATION_SOURCE, 'message', ['component' => 'isha']),
        ]);

        self::assertCount(2, $metadata->warnings);
    }

    public function testIdenticalSourcesCollapseToOneEntry(): void
    {
        $source = CalculationSource::algorithm('solar.mean-meeus', 'meeus-2ed');

        $metadata = CalculationMetadata::create(CalculationContext::PRAYER, [], [$source, $source]);

        self::assertCount(1, $metadata->sources);
    }

    public function testSourcesWithDifferentDigestsDoNotCollapse(): void
    {
        $metadata = CalculationMetadata::create(CalculationContext::PRAYER, [], [
            CalculationSource::generatedData('hijri.mabims.my', str_repeat('b', 64), '1445-01..1500-12 AH'),
            CalculationSource::generatedData('hijri.mabims.my', str_repeat('a', 64), '1445-01..1500-12 AH'),
        ]);

        self::assertCount(2, $metadata->sources);
        self::assertSame(str_repeat('a', 64), $metadata->sources[0]->digest);
        self::assertSame(str_repeat('b', 64), $metadata->sources[1]->digest);
    }

    public function testRejectsNonListWarnings(): void
    {
        $this->expectException(ValidationException::class);

        /** @phpstan-ignore-next-line deliberate contract violation */
        CalculationMetadata::create(CalculationContext::PRAYER, ['key' => self::warning(WarningCode::POLAR_FALLBACK_APPLIED)]);
    }

    public function testRejectsNonListSources(): void
    {
        $this->expectException(ValidationException::class);

        /** @phpstan-ignore-next-line deliberate contract violation */
        CalculationMetadata::create(CalculationContext::PRAYER, [], ['key' => CalculationSource::algorithm('solar.mean-meeus')]);
    }

    public function testRejectsNonWarningElements(): void
    {
        $this->expectException(ValidationException::class);

        /** @phpstan-ignore-next-line deliberate contract violation */
        CalculationMetadata::create(CalculationContext::PRAYER, [CalculationSource::algorithm('solar.mean-meeus')]);
    }

    public function testRejectsNonSourceElements(): void
    {
        $this->expectException(ValidationException::class);

        /** @phpstan-ignore-next-line deliberate contract violation */
        CalculationMetadata::create(CalculationContext::PRAYER, [], [self::warning(WarningCode::POLAR_FALLBACK_APPLIED)]);
    }

    public function testToArrayHasTheFixedKeyOrder(): void
    {
        $metadata = CalculationMetadata::create(
            CalculationContext::PRAYER,
            [self::warning(WarningCode::POLAR_FALLBACK_APPLIED)],
            [CalculationSource::algorithm('solar.mean-meeus', 'meeus-2ed')],
        );

        $array = $metadata->toArray();

        self::assertSame([
            'schema_version',
            'context',
            'status',
            'library_version',
            'warnings',
            'sources',
        ], array_keys($array));
        self::assertSame('1.0', $array['schema_version']);
        self::assertSame('prayer', $array['context']);
        self::assertSame('fallback_applied', $array['status']);
        self::assertSame(LibraryVersion::CURRENT, $array['library_version']);
        self::assertSame([[
            'code' => 'polar_fallback_applied',
            'message' => 'message',
            'details' => [],
        ]], $array['warnings']);
        self::assertSame([[
            'id' => 'solar.mean-meeus',
            'kind' => 'algorithm',
            'version' => 'meeus-2ed',
            'digest' => null,
            'range' => null,
        ]], $array['sources']);
        self::assertIsString(json_encode($array, JSON_THROW_ON_ERROR));
    }

    /**
     * Build one warning with a stable test message.
     *
     * @param WarningCode $code Warning code.
     * @param string $message Message.
     * @param array<string, int|float|string|bool|null> $details Scalar details.
     */
    private static function warning(WarningCode $code, string $message = 'message', array $details = []): CalculationWarning
    {
        return new CalculationWarning($code, $message, $details);
    }
}
