<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Hijri;

use AlFalaq\AlFalaq\Hijri\Enums\CalendarCoverage;
use AlFalaq\AlFalaq\Hijri\Enums\CalendarFamily;
use AlFalaq\AlFalaq\Hijri\Enums\HijriDateFrame;
use AlFalaq\AlFalaq\Hijri\ValueObjects\CalendarProvenance;
use AlFalaq\AlFalaq\Hijri\ValueObjects\HijriCalculationMetadata;
use AlFalaq\AlFalaq\Hijri\ValueObjects\HijriCalculationOutcome;
use AlFalaq\AlFalaq\Hijri\ValueObjects\HijriDate;
use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\ValueObjects\CalculationParameters;
use AlFalaq\AlFalaq\SharedKernel\Enums\CalculationContext;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CalculationMetadata;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\LibraryVersion;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Hijri metadata value objects: frame validation, offsets, fallback, and the wire shape.
 */
final class HijriMetadataValueObjectsTest extends TestCase
{
    /**
     * Shared hijri envelope with no warnings or sources.
     *
     * @return CalculationMetadata Common envelope.
     */
    private static function common(): CalculationMetadata
    {
        return CalculationMetadata::create(CalculationContext::HIJRI, [], []);
    }

    /**
     * Caller's zone-aware instant used by the fixtures.
     *
     * @return \DateTimeImmutable 2026-11-15 12:00 in Kuala Lumpur.
     */
    private static function instant(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-11-15 12:00:00', new \DateTimeZone('Asia/Kuala_Lumpur'));
    }

    /**
     * Tabular arithmetic-rule identity.
     *
     * @return CalendarProvenance Calendar identity.
     */
    private static function tabular(): CalendarProvenance
    {
        return new CalendarProvenance(
            'tabular',
            CalendarFamily::TABULAR,
            CalendarCoverage::ARITHMETIC_RULE,
            range: '1900-01-01..2100-12-31 CE',
        );
    }

    /**
     * Civil-frame metadata with no offsets and no fallback.
     *
     * @return HijriCalculationMetadata Civil-frame record.
     */
    private static function civilMetadata(): HijriCalculationMetadata
    {
        return new HijriCalculationMetadata(
            self::common(),
            self::instant(),
            HijriDateFrame::CIVIL_DATE,
            CivilDate::fromYmd(2026, 11, 15),
            self::tabular(),
            null,
            false,
        );
    }

    /**
     * Sunset-frame metadata with an explicit observer and policy.
     *
     * @param int|null $offsetDays OffsetStore answer, null for no store.
     * @param bool $maghribFallbackApplied Whether a sunset probe used maghribAt().
     * @return HijriCalculationMetadata Sunset-frame record.
     */
    private static function sunsetMetadata(
        ?int $offsetDays = 0,
        bool $maghribFallbackApplied = true,
    ): HijriCalculationMetadata {
        return new HijriCalculationMetadata(
            self::common(),
            self::instant(),
            HijriDateFrame::SUNSET_ROLLOVER,
            CivilDate::fromYmd(2026, 11, 15),
            self::tabular(),
            $offsetDays,
            $maghribFallbackApplied,
            Coordinates::fromDegrees(3.1390, 101.6869),
            CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters(),
        );
    }

    /**
     * Civil metadata renders the fixed envelope and hijri blocks in order.
     *
     * @return void
     */
    public function testCivilMetadataMatchesTheWireShape(): void
    {
        self::assertSame([
            'schema_version' => '1.0',
            'context' => 'hijri',
            'status' => 'complete',
            'library_version' => LibraryVersion::CURRENT,
            'warnings' => [],
            'sources' => [],
            'input' => [
                'instant' => '2026-11-15T04:00:00+00:00',
                'zone' => 'Asia/Kuala_Lumpur',
                'frame' => 'civil_date',
                'coordinates' => null,
                'parameters' => null,
            ],
            'effective' => ['gregorian_date' => '2026-11-15'],
            'calendar' => [
                'key' => 'tabular',
                'family' => 'tabular',
                'coverage' => 'arithmetic_rule',
                'authority' => null,
                'range' => '1900-01-01..2100-12-31 CE',
                'digest' => null,
                'source' => null,
                'license' => null,
            ],
            'offsets' => ['days' => null, 'applied' => false],
            'fallback' => ['applied' => false, 'kind' => null],
        ], self::civilMetadata()->toArray());
    }

    /**
     * Sunset metadata records the observer, policy, offset and fallback.
     *
     * @return void
     */
    public function testSunsetMetadataMatchesTheWireShape(): void
    {
        $metadata = self::sunsetMetadata();
        $parameters = CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters();
        $coordinates = Coordinates::fromDegrees(3.1390, 101.6869);
        $array = $metadata->toArray();

        self::assertSame('sunset_rollover', $array['input']['frame']);
        self::assertSame($coordinates->toArray(), $array['input']['coordinates']);
        self::assertSame($parameters->toArray(), $array['input']['parameters']);
        self::assertSame(['days' => 0, 'applied' => true], $array['offsets']);
        self::assertSame(['applied' => true, 'kind' => 'maghrib_only'], $array['fallback']);
    }

    /**
     * The outcome wraps the result first and the metadata second.
     *
     * @return void
     */
    public function testOutcomeToArrayWrapsResultAndMetadata(): void
    {
        $result = new HijriDate(1448, 5, 25);
        $metadata = self::civilMetadata();
        $array = (new HijriCalculationOutcome($result, $metadata))->toArray();

        self::assertSame(['result', 'metadata'], array_keys($array));
        self::assertSame($result->toArray(), $array['result']);
        self::assertSame($metadata->toArray(), $array['metadata']);
    }

    /**
     * A non-hijri envelope is rejected before the frame is considered.
     *
     * @return void
     */
    public function testNonHijriContextIsRejected(): void
    {
        $this->expectException(ValidationException::class);

        new HijriCalculationMetadata(
            CalculationMetadata::create(CalculationContext::MOON, [], []),
            self::instant(),
            HijriDateFrame::CIVIL_DATE,
            CivilDate::fromYmd(2026, 11, 15),
            self::tabular(),
            null,
            false,
        );
    }

    /**
     * The civil frame must not carry astronomy inputs or a fallback.
     *
     * @param Coordinates|null $coordinates Non-null coordinates in the violating case.
     * @param CalculationParameters|null $parameters Non-null parameters in the violating case.
     * @param bool $maghribFallbackApplied True in the fallback violating case.
     */
    #[DataProvider('civilFrameViolationProvider')]
    public function testCivilFrameRejectsCoordinatesParametersAndFallback(
        ?Coordinates $coordinates,
        ?CalculationParameters $parameters,
        bool $maghribFallbackApplied,
    ): void {
        $this->expectException(ValidationException::class);

        new HijriCalculationMetadata(
            self::common(),
            self::instant(),
            HijriDateFrame::CIVIL_DATE,
            CivilDate::fromYmd(2026, 11, 15),
            self::tabular(),
            null,
            $maghribFallbackApplied,
            $coordinates,
            $parameters,
        );
    }

    /**
     * @return iterable<string, array{?Coordinates, ?CalculationParameters, bool}>
     */
    public static function civilFrameViolationProvider(): iterable
    {
        yield 'coordinates' => [Coordinates::fromDegrees(3.1390, 101.6869), null, false];
        yield 'parameters' => [null, CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters(), false];
        yield 'fallback' => [null, null, true];
    }

    /**
     * The sunset frame requires both the observer and the policy.
     *
     * @param Coordinates|null $coordinates Observer, null in the violating case.
     * @param CalculationParameters|null $parameters Policy, null in the violating case.
     */
    #[DataProvider('sunsetFrameMissingProvider')]
    public function testSunsetFrameRequiresCoordinatesAndParameters(
        ?Coordinates $coordinates,
        ?CalculationParameters $parameters,
    ): void {
        $this->expectException(ValidationException::class);

        new HijriCalculationMetadata(
            self::common(),
            self::instant(),
            HijriDateFrame::SUNSET_ROLLOVER,
            CivilDate::fromYmd(2026, 11, 15),
            self::tabular(),
            0,
            false,
            $coordinates,
            $parameters,
        );
    }

    /**
     * @return iterable<string, array{?Coordinates, ?CalculationParameters}>
     */
    public static function sunsetFrameMissingProvider(): iterable
    {
        $coordinates = Coordinates::fromDegrees(3.1390, 101.6869);
        $parameters = CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters();

        yield 'coordinates' => [null, $parameters];
        yield 'parameters' => [$coordinates, null];
    }

    /**
     * Offsets are "applied" exactly when the store answered with a value.
     *
     * @param int|null $offsetDays OffsetStore answer.
     * @param array{days: int|null, applied: bool} $expected Wire offsets block.
     */
    #[DataProvider('offsetProvider')]
    public function testOffsetsAppliedIsDerivedFromANullableAnswer(?int $offsetDays, array $expected): void
    {
        self::assertSame($expected, self::sunsetMetadata($offsetDays)->toArray()['offsets']);
    }

    /**
     * @return iterable<string, array{?int, array{days: int|null, applied: bool}}>
     */
    public static function offsetProvider(): iterable
    {
        yield 'no store' => [null, ['days' => null, 'applied' => false]];
        yield 'zero' => [0, ['days' => 0, 'applied' => true]];
        yield 'positive' => [2, ['days' => 2, 'applied' => true]];
    }

    /**
     * The fallback kind is derived from the boolean flag.
     *
     * @param bool $applied Fallback flag.
     * @param string|null $expected Expected kind.
     */
    #[DataProvider('fallbackProvider')]
    public function testFallbackKindIsDerivedFromTheFlag(bool $applied, ?string $expected): void
    {
        $metadata = $applied
            ? self::sunsetMetadata()
            : self::civilMetadata();

        self::assertSame(
            ['applied' => $applied, 'kind' => $expected],
            $metadata->toArray()['fallback'],
        );
    }

    /**
     * @return iterable<string, array{bool, string|null}>
     */
    public static function fallbackProvider(): iterable
    {
        yield 'none' => [false, null];
        yield 'maghrib only' => [true, 'maghrib_only'];
    }

    /**
     * The requested instant renders in UTC while the zone stays the original.
     *
     * @return void
     */
    public function testRequestedInstantIsRenderedInUtcWithTheOriginalZone(): void
    {
        $input = self::civilMetadata()->toArray()['input'];

        self::assertSame('2026-11-15T04:00:00+00:00', $input['instant']);
        self::assertSame('Asia/Kuala_Lumpur', $input['zone']);
    }
}
