<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\SharedKernel\Provenance;

use AlFalaq\AlFalaq\SharedKernel\Enums\CalculationContext;
use AlFalaq\AlFalaq\SharedKernel\Enums\CalculationStatus;
use AlFalaq\AlFalaq\SharedKernel\Enums\SourceKind;
use AlFalaq\AlFalaq\SharedKernel\Enums\WarningCode;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\LibraryVersion;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Shared provenance vocabulary: enum values and the runtime version value object.
 */
final class SharedVocabularyTest extends TestCase
{
    public function testCalculationContextValues(): void
    {
        self::assertSame(
            ['prayer', 'qibla', 'moon', 'hijri'],
            array_map(
                static fn(CalculationContext $context): string => $context->value,
                CalculationContext::cases(),
            ),
        );
    }

    public function testCalculationStatusValues(): void
    {
        self::assertSame(
            ['complete', 'extrapolated', 'fallback_applied', 'indeterminate', 'decision_required'],
            array_map(
                static fn(CalculationStatus $status): string => $status->value,
                CalculationStatus::cases(),
            ),
        );
    }

    public function testWarningCodeValues(): void
    {
        self::assertSame(
            [
                'delta_t_extrapolated',
                'polar_fallback_applied',
                'sunset_frame_maghrib_fallback',
                'moonset_indeterminate',
                'crescent_criterion_is_not_observation',
                'generated_calendar_table',
                'tabular_calendar_estimate',
                'unidentified_calculation_source',
            ],
            array_map(
                static fn(WarningCode $code): string => $code->value,
                WarningCode::cases(),
            ),
        );
    }

    public function testSourceKindValues(): void
    {
        self::assertSame(
            ['algorithm', 'generated_data', 'official_data', 'caller_input'],
            array_map(
                static fn(SourceKind $kind): string => $kind->value,
                SourceKind::cases(),
            ),
        );
    }

    public function testLibraryVersionCurrentIsTheCheckedInRuntimeVersion(): void
    {
        self::assertSame(LibraryVersion::CURRENT, LibraryVersion::current()->value);
    }

    public function testLibraryVersionRoundTrips(): void
    {
        self::assertSame('0.3.0-dev', (string) LibraryVersion::fromString('0.3.0-dev'));
        self::assertSame('1.2.3', LibraryVersion::fromString('1.2.3')->value);
        self::assertSame('1.2.3-rc.1', LibraryVersion::fromString('1.2.3-rc.1')->value);
    }

    /**
     * @param string $value Malformed version string.
     */
    #[DataProvider('malformedVersionProvider')]
    public function testLibraryVersionRejectsMalformedValues(string $value): void
    {
        $this->expectException(ValidationException::class);

        LibraryVersion::fromString($value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedVersionProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'two segments' => ['0.3'];
        yield 'v prefix' => ['v0.3.0'];
        yield 'empty prerelease' => ['0.3.0-'];
        yield 'build metadata' => ['0.3.0+build'];
        yield 'trailing space' => ['0.3.0 '];
        yield 'trailing newline' => ["0.3.0-dev\n"];
    }
}
