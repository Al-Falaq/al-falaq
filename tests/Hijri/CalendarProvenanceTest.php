<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Hijri;

use AlFalaq\AlFalaq\Hijri\Contracts\DescribesCalendar;
use AlFalaq\AlFalaq\Hijri\Contracts\HijriCalendar;
use AlFalaq\AlFalaq\Hijri\Data\MabimsMonthStarts;
use AlFalaq\AlFalaq\Hijri\Data\UmmAlQuraMonthStarts;
use AlFalaq\AlFalaq\Hijri\Enums\CalendarCoverage;
use AlFalaq\AlFalaq\Hijri\Enums\CalendarFamily;
use AlFalaq\AlFalaq\Hijri\Enums\HijriDateFrame;
use AlFalaq\AlFalaq\Hijri\Enums\MabimsCountry;
use AlFalaq\AlFalaq\Hijri\Strategies\MabimsCalendar;
use AlFalaq\AlFalaq\Hijri\Strategies\TabularCalendar;
use AlFalaq\AlFalaq\Hijri\Strategies\UmmAlQuraCalendar;
use AlFalaq\AlFalaq\Hijri\ValueObjects\CalendarProvenance;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Calendar description vocabulary, built-in calendar identities, and the opt-in provenance guard.
 */
final class CalendarProvenanceTest extends TestCase
{
    private static function generated(): CalendarProvenance
    {
        return new CalendarProvenance(
            'mabims',
            CalendarFamily::MABIMS,
            CalendarCoverage::GENERATED_TABLE,
            authority: 'MY',
            range: '1445-01..1500-12 AH',
            digest: str_repeat('a', 64),
            source: 'tools/gen_hijri.py @ a7baa8b202790d3bc84c47a610ac763be6c0b7e7',
            license: 'MIT',
        );
    }

    public function testToArrayHasTheWireShape(): void
    {
        self::assertSame([
            'key' => 'mabims',
            'family' => 'mabims',
            'coverage' => 'generated_table',
            'authority' => 'MY',
            'range' => '1445-01..1500-12 AH',
            'digest' => str_repeat('a', 64),
            'source' => 'tools/gen_hijri.py @ a7baa8b202790d3bc84c47a610ac763be6c0b7e7',
            'license' => 'MIT',
        ], self::generated()->toArray());
    }

    public function testCustomCalendarProvenanceMayOmitFamilyAndCoverage(): void
    {
        self::assertSame([
            'key' => 'custom',
            'family' => null,
            'coverage' => null,
            'authority' => null,
            'range' => null,
            'digest' => null,
            'source' => null,
            'license' => null,
        ], (new CalendarProvenance('custom', null, null))->toArray());
    }

    /**
     * @param string $key Key outside the CalculationSource id grammar.
     */
    #[DataProvider('nonSourceKeyProvider')]
    public function testKeyMayDifferFromTheSourceIdGrammar(string $key): void
    {
        $provenance = new CalendarProvenance($key, null, null);

        self::assertSame($key, $provenance->key);
        self::assertSame($key, $provenance->toArray()['key']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function nonSourceKeyProvider(): iterable
    {
        yield 'space' => ['My Calendar'];
        yield 'slash' => ['my/calendar'];
        yield 'padded' => ['  My Calendar  '];
    }

    /**
     * @param string $key Blank key.
     */
    #[DataProvider('blankKeyProvider')]
    public function testBlankKeyIsRejected(string $key): void
    {
        $this->expectException(ValidationException::class);

        new CalendarProvenance($key, null, null);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function blankKeyProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'whitespace' => ['   '];
    }

    /**
     * @param CalendarFamily|null $family Family side of the half-description.
     * @param CalendarCoverage|null $coverage Coverage side of the half-description.
     */
    #[DataProvider('halfDescribedProvider')]
    public function testHalfDescribedProvenanceIsRejected(
        ?CalendarFamily $family,
        ?CalendarCoverage $coverage,
    ): void {
        $this->expectException(ValidationException::class);

        new CalendarProvenance('custom', $family, $coverage);
    }

    /**
     * @return iterable<string, array{CalendarFamily|null, CalendarCoverage|null}>
     */
    public static function halfDescribedProvider(): iterable
    {
        yield 'family without coverage' => [CalendarFamily::TABULAR, null];
        yield 'coverage without family' => [null, CalendarCoverage::ARITHMETIC_RULE];
    }

    /**
     * @param string|null $digest Candidate digest.
     */
    #[DataProvider('invalidDigestProvider')]
    public function testGeneratedTableRequiresALowercaseSha256Digest(?string $digest): void
    {
        $this->expectException(ValidationException::class);

        new CalendarProvenance(
            'mabims',
            CalendarFamily::MABIMS,
            CalendarCoverage::GENERATED_TABLE,
            authority: 'MY',
            range: '1445-01..1500-12 AH',
            digest: $digest,
            source: 'tools/gen_hijri.py @ a7baa8b202790d3bc84c47a610ac763be6c0b7e7',
            license: 'MIT',
        );
    }

    /**
     * @return iterable<string, array{string|null}>
     */
    public static function invalidDigestProvider(): iterable
    {
        yield 'missing' => [null];
        yield 'empty' => [''];
        yield 'uppercase' => [str_repeat('A', 64)];
        yield 'too short' => [str_repeat('a', 63)];
        yield 'too long' => [str_repeat('a', 65)];
    }

    /**
     * @param string|null $range Candidate coverage range.
     */
    #[DataProvider('invalidRangeProvider')]
    public function testGeneratedTableRequiresANonEmptyRange(?string $range): void
    {
        $this->expectException(ValidationException::class);

        new CalendarProvenance(
            'mabims',
            CalendarFamily::MABIMS,
            CalendarCoverage::GENERATED_TABLE,
            authority: 'MY',
            range: $range,
            digest: str_repeat('a', 64),
            source: 'tools/gen_hijri.py @ a7baa8b202790d3bc84c47a610ac763be6c0b7e7',
            license: 'MIT',
        );
    }

    /**
     * @return iterable<string, array{string|null}>
     */
    public static function invalidRangeProvider(): iterable
    {
        yield 'missing' => [null];
        yield 'empty' => [''];
        yield 'whitespace' => ['   '];
    }

    /**
     * @param string|null $authority Missing or present authority.
     * @param string|null $source Missing or present source.
     * @param string|null $license Missing or present license.
     */
    #[DataProvider('officialIdentityProvider')]
    public function testOfficialTableRequiresAuthoritySourceAndLicense(
        ?string $authority,
        ?string $source,
        ?string $license,
    ): void {
        $this->expectException(ValidationException::class);

        new CalendarProvenance(
            'official',
            CalendarFamily::UMM_AL_QURA,
            CalendarCoverage::OFFICIAL_TABLE,
            authority: $authority,
            range: '1423-01..1500-12 AH',
            digest: str_repeat('a', 64),
            source: $source,
            license: $license,
        );
    }

    /**
     * @return iterable<string, array{string|null, string|null, string|null}>
     */
    public static function officialIdentityProvider(): iterable
    {
        yield 'authority' => [null, 'Official Umm al-Qura calendar', 'Public domain'];
        yield 'source' => ['SA', null, 'Public domain'];
        yield 'license' => ['SA', 'Official Umm al-Qura calendar', null];
        yield 'blank authority' => ['   ', 'Official Umm al-Qura calendar', 'Public domain'];
        yield 'blank source' => ['SA', '   ', 'Public domain'];
        yield 'blank license' => ['SA', 'Official Umm al-Qura calendar', '   '];
    }

    public function testOfficialTableWithCompleteIdentityIsValid(): void
    {
        $provenance = new CalendarProvenance(
            'official',
            CalendarFamily::UMM_AL_QURA,
            CalendarCoverage::OFFICIAL_TABLE,
            authority: 'SA',
            range: '1423-01..1500-12 AH',
            digest: str_repeat('a', 64),
            source: 'Official Umm al-Qura calendar',
            license: 'Public domain',
        );

        self::assertSame([
            'key' => 'official',
            'family' => 'umm_al_qura',
            'coverage' => 'official_table',
            'authority' => 'SA',
            'range' => '1423-01..1500-12 AH',
            'digest' => str_repeat('a', 64),
            'source' => 'Official Umm al-Qura calendar',
            'license' => 'Public domain',
        ], $provenance->toArray());
    }

    public function testArithmeticRuleMayOmitDigest(): void
    {
        $provenance = new CalendarProvenance(
            'tabular',
            CalendarFamily::TABULAR,
            CalendarCoverage::ARITHMETIC_RULE,
            range: '1900-01-01..2100-12-31 CE',
        );

        self::assertNull($provenance->digest);
        self::assertSame('1900-01-01..2100-12-31 CE', $provenance->toArray()['range']);
    }

    public function testTabularCalendarDescribesItsArithmeticCoverage(): void
    {
        self::assertSame([
            'key' => 'tabular',
            'family' => 'tabular',
            'coverage' => 'arithmetic_rule',
            'authority' => null,
            'range' => '1900-01-01..2100-12-31 CE',
            'digest' => null,
            'source' => null,
            'license' => null,
        ], (new TabularCalendar())->calendarProvenance()->toArray());
    }

    public function testUmmAlQuraCalendarDescribesItsGeneratedTable(): void
    {
        self::assertSame([
            'key' => 'uqu',
            'family' => 'umm_al_qura',
            'coverage' => 'generated_table',
            'authority' => null,
            'range' => '1423-01..1500-12 AH',
            'digest' => UmmAlQuraMonthStarts::SHA256,
            'source' => 'tools/gen_hijri.py @ a7baa8b202790d3bc84c47a610ac763be6c0b7e7',
            'license' => 'MIT',
        ], (new UmmAlQuraCalendar())->calendarProvenance()->toArray());
    }

    /**
     * @param MabimsCountry $country Member state whose table identity is asserted.
     */
    #[DataProvider('mabimsCountryProvider')]
    public function testEveryMabimsCountryDescribesItsOwnGeneratedTable(MabimsCountry $country): void
    {
        self::assertSame([
            'key' => 'mabims',
            'family' => 'mabims',
            'coverage' => 'generated_table',
            'authority' => $country->value,
            'range' => '1445-01..1500-12 AH',
            'digest' => MabimsMonthStarts::digestFor($country),
            'source' => 'tools/gen_hijri.py @ a7baa8b202790d3bc84c47a610ac763be6c0b7e7',
            'license' => 'MIT',
        ], (new MabimsCalendar($country))->calendarProvenance()->toArray());
    }

    /**
     * @return iterable<string, array{MabimsCountry}>
     */
    public static function mabimsCountryProvider(): iterable
    {
        foreach (MabimsCountry::cases() as $country) {
            yield $country->value => [$country];
        }
    }

    public function testShippedCalendarsNeverClaimAnOfficialTable(): void
    {
        foreach (self::shippedCalendars() as $label => $calendar) {
            self::assertNotSame(
                CalendarCoverage::OFFICIAL_TABLE,
                $calendar->calendarProvenance()->coverage,
                sprintf('%s must not claim an official table.', $label),
            );
        }
    }

    public function testEveryShippedCalendarImplementsDescribesCalendar(): void
    {
        foreach (self::shippedCalendars() as $label => $calendar) {
            self::assertInstanceOf(DescribesCalendar::class, $calendar, $label);
        }
    }

    /**
     * Every calendar shipped in the library, keyed by a readable label.
     *
     * @return iterable<string, HijriCalendar&DescribesCalendar>
     */
    private static function shippedCalendars(): iterable
    {
        yield 'tabular' => new TabularCalendar();
        yield 'uqu' => new UmmAlQuraCalendar();

        foreach (MabimsCountry::cases() as $country) {
            yield 'mabims-' . $country->value => new MabimsCalendar($country);
        }
    }

    public function testEnumsHaveTheFrozenValues(): void
    {
        self::assertSame([
            ['CIVIL_DATE', 'civil_date'],
            ['SUNSET_ROLLOVER', 'sunset_rollover'],
        ], self::enumPairs(HijriDateFrame::cases()));

        self::assertSame([
            ['TABULAR', 'tabular'],
            ['UMM_AL_QURA', 'umm_al_qura'],
            ['MABIMS', 'mabims'],
        ], self::enumPairs(CalendarFamily::cases()));

        self::assertSame([
            ['ARITHMETIC_RULE', 'arithmetic_rule'],
            ['GENERATED_TABLE', 'generated_table'],
            ['OFFICIAL_TABLE', 'official_table'],
        ], self::enumPairs(CalendarCoverage::cases()));
    }

    public function testDescribesCalendarStaysOptIn(): void
    {
        self::assertFalse(
            is_subclass_of(HijriCalendar::class, DescribesCalendar::class),
            'DescribesCalendar must stay an opt-in capability, not part of HijriCalendar.',
        );
    }

    /**
     * @param list<HijriDateFrame>|list<CalendarFamily>|list<CalendarCoverage> $cases Enum cases.
     * @return list<array{string, string}> Case name and backing value pairs.
     */
    private static function enumPairs(array $cases): array
    {
        return array_map(
            static fn(HijriDateFrame|CalendarFamily|CalendarCoverage $case): array => [$case->name, $case->value],
            $cases,
        );
    }
}
