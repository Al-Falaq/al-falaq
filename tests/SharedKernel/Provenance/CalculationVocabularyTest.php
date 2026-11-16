<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\SharedKernel\Provenance;

use AlFalaq\AlFalaq\SharedKernel\Enums\WarningCode;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CalculationSource;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CalculationWarning;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Warning and source wire shapes, validation, and deterministic detail ordering.
 */
final class CalculationVocabularyTest extends TestCase
{
    public function testWarningSerializesCodeMessageAndDetails(): void
    {
        $warning = new CalculationWarning(
            WarningCode::POLAR_FALLBACK_APPLIED,
            'message',
            ['z' => 1, 'a' => true],
        );

        self::assertSame([
            'code' => 'polar_fallback_applied',
            'message' => 'message',
            'details' => ['a' => true, 'z' => 1],
        ], $warning->toArray());
    }

    public function testWarningAcceptsEveryScalarDetail(): void
    {
        $warning = new CalculationWarning(
            WarningCode::GENERATED_CALENDAR_TABLE,
            'message',
            ['int' => 1, 'float' => 1.5, 'string' => 'x', 'bool' => false, 'null' => null],
        );

        self::assertSame([
            'bool' => false,
            'float' => 1.5,
            'int' => 1,
            'null' => null,
            'string' => 'x',
        ], $warning->toArray()['details']);
    }

    /**
     * @param string $message Blank message.
     */
    #[DataProvider('blankMessageProvider')]
    public function testWarningRejectsBlankMessage(string $message): void
    {
        $this->expectException(ValidationException::class);

        new CalculationWarning(WarningCode::MOONSET_INDETERMINATE, $message);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function blankMessageProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'spaces' => ['   '];
        yield 'tabs and newline' => ["\t\n"];
    }

    /**
     * @param string $key Blank detail key.
     */
    #[DataProvider('blankDetailKeyProvider')]
    public function testWarningRejectsEmptyDetailKey(string $key): void
    {
        $this->expectException(ValidationException::class);

        new CalculationWarning(WarningCode::MOONSET_INDETERMINATE, 'message', [$key => 1]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function blankDetailKeyProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'spaces' => ['   '];
    }

    public function testWarningRejectsIntegerDetailKey(): void
    {
        $this->expectException(ValidationException::class);

        /** @phpstan-ignore-next-line deliberate contract violation */
        new CalculationWarning(WarningCode::MOONSET_INDETERMINATE, 'message', [0 => 'value']);
    }

    public function testWarningRejectsNestedArrayDetail(): void
    {
        $this->expectException(ValidationException::class);

        /** @phpstan-ignore-next-line deliberate contract violation */
        new CalculationWarning(WarningCode::MOONSET_INDETERMINATE, 'message', ['nested' => ['x' => 1]]);
    }

    public function testWarningRejectsObjectDetail(): void
    {
        $this->expectException(ValidationException::class);

        /** @phpstan-ignore-next-line deliberate contract violation */
        new CalculationWarning(WarningCode::MOONSET_INDETERMINATE, 'message', ['object' => new \stdClass()]);
    }

    /**
     * @param float $value Non-finite float.
     */
    #[DataProvider('nonFiniteProvider')]
    public function testWarningRejectsNonFiniteDetail(float $value): void
    {
        $this->expectException(ValidationException::class);

        new CalculationWarning(WarningCode::MOONSET_INDETERMINATE, 'message', ['value' => $value]);
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

    public function testAlgorithmSourceHasTheFiveKeyShape(): void
    {
        self::assertSame([
            'id' => 'solar.mean-meeus',
            'kind' => 'algorithm',
            'version' => 'meeus-2ed',
            'digest' => null,
            'range' => null,
        ], CalculationSource::algorithm('solar.mean-meeus', 'meeus-2ed')->toArray());
    }

    public function testAlgorithmSourceAcceptsARangeWithoutAVersion(): void
    {
        self::assertSame([
            'id' => 'moon.delta-t.espenak-2005-2050',
            'kind' => 'algorithm',
            'version' => null,
            'digest' => null,
            'range' => '2005..2050',
        ], CalculationSource::algorithm('moon.delta-t.espenak-2005-2050', null, '2005..2050')->toArray());
    }

    /**
     * @param string $id Invalid identifier.
     */
    #[DataProvider('invalidIdProvider')]
    public function testSourceRejectsInvalidId(string $id): void
    {
        $this->expectException(ValidationException::class);

        CalculationSource::algorithm($id);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidIdProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'uppercase' => ['Upper'];
        yield 'leading dot' => ['.leading'];
        yield 'trailing space' => ['trailing '];
        yield 'space' => ['with space'];
        yield 'slash' => ['slash/id'];
        yield 'tab' => ["tab\tid"];
    }

    public function testSourceAcceptsDotsUnderscoresAndHyphens(): void
    {
        self::assertSame('prayer.method.muslim_world_league', CalculationSource::algorithm('prayer.method.muslim_world_league')->id);
        self::assertSame('custom.my-engine_v2', CalculationSource::algorithm('custom.my-engine_v2')->id);
    }

    public function testSourceRejectsEmptyVersion(): void
    {
        $this->expectException(ValidationException::class);

        CalculationSource::algorithm('prayer.isha.angle', '');
    }

    public function testSourceRejectsEmptyRange(): void
    {
        $this->expectException(ValidationException::class);

        CalculationSource::algorithm('prayer.isha.angle', null, '');
    }

    public function testGeneratedDataAcceptsAValidDigestAndRange(): void
    {
        $digest = str_repeat('a', 64);

        self::assertSame([
            'id' => 'hijri.mabims.my',
            'kind' => 'generated_data',
            'version' => null,
            'digest' => $digest,
            'range' => '1445-01..1500-12 AH',
        ], CalculationSource::generatedData('hijri.mabims.my', $digest, '1445-01..1500-12 AH')->toArray());
    }

    /**
     * @param string $digest Malformed digest.
     */
    #[DataProvider('malformedDigestProvider')]
    public function testGeneratedDataRejectsMalformedDigest(string $digest): void
    {
        $this->expectException(ValidationException::class);

        CalculationSource::generatedData('hijri.mabims.my', $digest, '1445-01..1500-12 AH');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedDigestProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'short' => ['abc'];
        yield '63 chars' => [str_repeat('a', 63)];
        yield '65 chars' => [str_repeat('a', 65)];
        yield 'uppercase' => [str_repeat('A', 64)];
        yield 'non-hex' => [str_repeat('g', 64)];
    }

    public function testGeneratedDataRejectsEmptyRange(): void
    {
        $this->expectException(ValidationException::class);

        CalculationSource::generatedData('hijri.mabims.my', str_repeat('a', 64), '');
    }

    public function testOfficialDataReportsItsKind(): void
    {
        $source = CalculationSource::officialData(
            'hijri.mabims.my',
            str_repeat('b', 64),
            '1445-01..1500-12 AH',
            'table-v1',
        );

        self::assertSame('official_data', $source->kind->value);
        self::assertSame('table-v1', $source->version);
    }

    public function testCallerInputCarriesOnlyAnId(): void
    {
        self::assertSame([
            'id' => 'caller.adjustments',
            'kind' => 'caller_input',
            'version' => null,
            'digest' => null,
            'range' => null,
        ], CalculationSource::callerInput('caller.adjustments')->toArray());
    }
}
