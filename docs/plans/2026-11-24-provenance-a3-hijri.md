# Provenance A3 — Hijri Calendar Metadata Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Land A3 of the provenance/explainability programme: reuse the frozen `SharedKernel` vocabulary for the Hijri path, exposing the calendar key/family/coverage, MABIMS country authority, covered span, table digest, offset source, date frame, and maghrib-only fallback without changing any existing result, method signature, or numeric output.

**Architecture:** A3 is five stacked layers delivered as individually revertable commits:

1. **Calendar description vocabulary** — `HijriDateFrame`, `CalendarFamily`, `CalendarCoverage`, `CalendarProvenance`, and the `DescribesCalendar` driven capability. Validation enforces the spec's table-backed and official-table rules.
2. **Built-in calendar identities** — `TabularCalendar`, `UmmAlQuraCalendar`, and `MabimsCalendar` implement `DescribesCalendar`; the generated tables expose their existing spans and digests, never an official claim.
3. **Hijri payload and outcome** — `HijriDate::toArray()` plus `HijriCalculationMetadata` and `HijriCalculationOutcome`. The metadata value object validates the context and the frame/input pairing and serializes the spec §9.6 wire shape.
4. **Converter trace and opt-in method** — `GregorianToHijriConverter` gets one private `trace()` returning date, resolved civil date, offset days, and the aggregated maghrib-fallback flag. `convert()` returns `trace()['date']`; `convertWithMetadata()` builds the outcome from the same trace and reads `DescribesCalendar` only after the numeric path succeeds.
5. **Docs, integration snippet, benchmark, changelog, and gates** — contributor and published docs, executable wiring, an A3 benchmark line, and the full CI/mutation/bench/docs gates.

Existing behaviour is frozen: `convert()` keeps its signature, return type, exception types, and output. `HijriCalendar` and `OffsetStore` are unchanged. `DescribesCalendar` is a separate opt-in capability, not a new base contract.

**Tech Stack:** PHP 8.2+ (no runtime dependencies), PHPUnit 11, PHPStan max + strict-rules, php-cs-fixer `@PER`, Infection (MSI ≥ 85), Astro Starlight docs site, plain-PHP tooling under `tools/`.

**Design authority:** [`docs/design/2026-11-15-provenance-explainability-foundation.md`](../design/2026-11-15-provenance-explainability-foundation.md), especially §6.4 (Hijri metadata and calendar provenance), §6.5 (service method), §6.6 (`HijriDate` serializer), §7 (warnings/status), §8 (source registry and custom components), §9.1–9.2 and §9.6 (wire shapes), §10.4 (single-path trace), §12 (test matrix), and §14 A3. If implementation contradicts the spec, stop and fix the spec first; do not silently diverge.

**Plan location note:** the `writing-plans` skill defaults to `docs/superpowers/plans/`, which this repository gitignores. The plan is committed with the work and follows the `docs/plans/` precedent.

**Branch:** `feat/provenance-a3-hijri`, split from `main` at the A3 plan commit (record the exact commit in the PR).

**Baseline (verified on `main` at `25a48e0`, PHP 8.4.27):** 828 tests / 51,425 assertions (`composer test-unit`), 32 arch tests / 451 assertions (`composer arch`), `composer validate --strict` green, 0 PHPStan errors, 0 php-cs-fixer findings, MSI gate 85, benchmark tripwires 50 ms single-day / 30,000 ms year. If `main` has moved, record the new commit and counts in the PR; do not lower any gate.

**Frozen artefacts:** released user-docs snapshots under `docs/user/vX.Y.Z/` are never edited. Only current `docs/user/*.md` files change.

**Review status:** Confirmed on 2026-11-25. All eight decisions below are approved; Task 1 may start. The public signatures, validation rules, and wire shape in Tasks 1–4 are the contract; implementation follows them.

---

## Scope

### In scope

- `Hijri\Enums\HijriDateFrame`, `CalendarFamily`, `CalendarCoverage`.
- `Hijri\ValueObjects\CalendarProvenance`.
- `Hijri\Contracts\DescribesCalendar`.
- `DescribesCalendar` on `TabularCalendar`, `UmmAlQuraCalendar`, and `MabimsCalendar`.
- `HijriDate::toArray()`.
- `HijriCalculationMetadata`, `HijriCalculationOutcome`.
- `GregorianToHijriConverter::convertWithMetadata()` via one shared private `trace()`.
- Calendar coverage warnings, offset source, frame metadata, and maghrib-only fallback.
- Custom calendars with and without `DescribesCalendar`.
- API reference, contributor provenance page, published metadata guide, Hijri appendix, integration snippets, architecture/changelog/context updates.
- Tests for every new public type and every new branch.

### Out of scope (do not implement here)

- A release bump, changelog header change, tag, or GitHub Release. `LibraryVersion::CURRENT` stays `0.3.0-dev`.
- P0.2 applicability intervals, uncertainty, and status interpretation.
- An official Umm al-Qura dataset (#16); `CalendarCoverage::OFFICIAL_TABLE` is implemented for validation and future use but no shipped calendar claims it.
- MABIMS-1992 pre-conjunction guard (#12), Brunei CSV empty cells (#11), Tehran/Morocco arbitration (#9).
- Changing `HijriCalendar` or `OffsetStore`.
- Changing `convert()`'s signature, return type, behavior, or exception types.
- Metadata for Sunnah, NextPrayer, or timetable envelopes.
- A global warning mode, `set_error_handler`, logging, or exception promotion inside the library.
- Package metadata reads, clock reads, network calls, or Composer runtime dependencies.
- Regenerating or editing released docs snapshots.

### Non-negotiable invariants

1. Every existing test keeps passing unchanged; no golden is re-pinned; `convert()` output is byte-identical for every calendar, frame, and offset state.
2. `convert()` and `convertWithMetadata()` share one private `trace()`. Metadata never recomputes astronomy, offsets, or calendar provenance.
3. `convert()` never calls `DescribesCalendar::calendarProvenance()`, builds warnings, or reads provenance-only code. A custom calendar whose `calendarProvenance()` throws must still convert through `convert()`.
4. `CalculationMetadata::create()` is the only envelope constructor. Status is derived from warnings; callers cannot supply it.
5. Warning codes are stable; warning messages are explanatory and are not pinned by tests.
6. The calendar is reported in the `calendar` block, not as a `CalculationSource`. A custom calendar without `DescribesCalendar` gets `custom.calendar` + `unidentified_calculation_source` but no `custom.calendar` source entry.
7. Warning/source ordering and de-duplication stay owned by `CalculationMetadata::create()`.
8. Metadata `toArray()` shapes are key-ordered, contain all nullable keys with `null`, and contain optional lists as `[]`.
9. `src/` stays `strict_types=1`, final/readonly where required, docblock-complete, and free of `Astro`/`Moon` imports under `src/Hijri/`.
10. New public class-likes are added to `docs/user/api-reference.md` in the same commit that creates them; `tests/ArchApiReferenceTest.php` is exact in both directions.
11. `HijriCalendar` and `OffsetStore` are not modified. `DescribesCalendar` is an additive capability.
12. No `docs/user/v*/` change and no runtime `require` addition.

---

## Confirmed decisions

These are the points the spec leaves implicit. All eight were confirmed on 2026-11-25 before implementation; the tests and docs pin the chosen answer.

1. **`HijriCalculationMetadata` is a validated value object, not a bag.** It rejects a context other than `hijri`, a civil frame carrying coordinates/parameters or a maghrib fallback, and a sunset frame missing coordinates/parameters. This mirrors the A2 `MoonCalculationMetadata` review fix and prevents impossible provenance from being published through the public constructor.

2. **The fallback warning's `date` detail is the resolved civil date.** Spec §10.4 fixes the trace to one boolean flag and no probe-date field. `resolvedCivilDate` is the effective Gregorian day the frame produced and is always available. The warning therefore names the effective day, not necessarily the day whose individual probe took the fallback. Make the warning message say that `date` is the effective Gregorian date, and document the same in `docs/provenance.md` and `docs/user/calculation-metadata.md`.

3. **A custom calendar is not a `CalculationSource`.** Spec §8.1 reports the calendar in the `calendar` block. A custom calendar without `DescribesCalendar` adds the `unidentified_calculation_source` warning with `source_id = custom.calendar`, but does **not** add a `custom.calendar` entry to `sources`. The `calendar` value in §8.2 is the warning namespace, not a source-list slot.

4. **`offset` is not warned as unidentified.** Every `OffsetStore` is caller-owned by contract. When a store is supplied, the converter reports the synthetic `hijri.offset.caller` caller-input source. No identity capability is added to `OffsetStore`, and no `custom.offset` warning is emitted.

5. **Generated-table `source` is `tools/gen_hijri.py @ a7baa8b202790d3bc84c47a610ac763be6c0b7e7` and `license` is `MIT`.** The full pin SHA is unambiguous and matches the generated file headers and `UQ_PROVENANCE.md`; the spec's short form is illustrative. This is descriptive metadata, not a second source of truth. Tabular arithmetic has a null source and license.

6. **`OFFICIAL_TABLE` is implemented and unused by shipped calendars.** No shipped calendar claims official status. The validation path is tested with a custom `DescribesCalendar` double so the reserved coverage cannot rot.

7. **Frame inputs are nulled in metadata for the civil-date frame even when the caller passes coordinates/parameters.** `convert()` ignores them; metadata must not pretend it consulted them. `convertWithMetadata()` passes `null` to the metadata object in that case.

8. **`CalendarProvenance` preserves the supplied key; it only rejects a blank key.** A custom calendar may use a name that is not a valid `CalculationSource` id (for example, a space or slash). Built-in keys remain `tabular`, `uqu`, and `mabims`.

---

## Pre-flight

**Files:** none.

- [ ] **Step 1: Create the branch**

```bash
git switch main
git pull --ff-only
git switch -c feat/provenance-a3-hijri
```

- [ ] **Step 2: Re-run the baseline**

```bash
composer validate --strict
composer test-unit
composer arch
```

Expected: 828 tests / 51,425 assertions, 32 arch tests / 451 assertions, no failures. Record the commit and counts in the PR description.

- [ ] **Step 3: Read the frozen spec sections**

Read spec §6.4 (Hijri metadata and calendar provenance), §6.5 (method signature), §6.6 (`HijriDate` serializer), §7 (warnings/status), §8 (source registry and custom components), §9.1–9.2 and §9.6 (wire shapes), §10.4 (trace), §12 (test matrix), and §14 A3. Keep the spec open beside the implementation.

- [ ] **Step 4: Confirm the internal trace contract**

The converter trace is exactly the spec §10.4 shape and stays private:

```php
/**
 * @return array{
 *     date: HijriDate,
 *     resolvedCivilDate: CivilDate,
 *     offsetDays: int|null,
 *     maghribFallbackApplied: bool,
 * }
 */
private function trace(
    \DateTimeImmutable $dateTime,
    HijriCalendar $calendar,
    ?Coordinates $coordinates,
    ?CalculationParameters $parameters,
    bool $changeAtSunset,
    ?OffsetStore $offsets,
): array;
```

`convert()` returns `trace()['date']`. `convertWithMetadata()` uses the rest. The trace does **not** read `DescribesCalendar`; only the metadata method does, after the trace succeeds.

- [x] **Step 5: Review gate**

Approved on 2026-11-25. Proceed to Task 1; the public surface and wire shape in Tasks 1–4 are confirmed.

---

## Task 1: Calendar description vocabulary

**Files:**

- Create `src/Hijri/Enums/HijriDateFrame.php`
- Create `src/Hijri/Enums/CalendarFamily.php`
- Create `src/Hijri/Enums/CalendarCoverage.php`
- Create `src/Hijri/ValueObjects/CalendarProvenance.php`
- Create `src/Hijri/Contracts/DescribesCalendar.php`
- Create `tests/Hijri/CalendarProvenanceTest.php`
- Modify `docs/user/api-reference.md`

- [ ] **Step 1: Write the failing `CalendarProvenance` tests**

`tests/Hijri/CalendarProvenanceTest.php`:

**Wire shape**

- `testToArrayHasTheWireShape` — construct a complete generated-table provenance and assert exactly, in order:

```php
[
    'key' => 'mabims',
    'family' => 'mabims',
    'coverage' => 'generated_table',
    'authority' => 'MY',
    'range' => '1445-01..1500-12 AH',
    'digest' => str_repeat('a', 64),
    'source' => 'tools/gen_hijri.py @ a7baa8b202790d3bc84c47a610ac763be6c0b7e7',
    'license' => 'MIT',
]
```

- `testCustomCalendarProvenanceMayOmitFamilyAndCoverage` — `new CalendarProvenance('custom', null, null)` serializes `family`, `coverage`, `authority`, `range`, `digest`, `source`, and `license` as `null`.
- `testKeyMayDifferFromTheSourceIdGrammar` — a key with a space or slash (for example `'My Calendar'`) is accepted and preserved.

**Validation**

- `testBlankKeyIsRejected` — `''` and `'   '` throw `ValidationException`.
- `testHalfDescribedProvenanceIsRejected` — family without coverage and coverage without family both throw.
- `testGeneratedTableRequiresALowercaseSha256Digest` — provider over `null`, `''`, `str_repeat('A', 64)`, `str_repeat('a', 63)`, and `str_repeat('a', 65)`; each throws.
- `testGeneratedTableRequiresANonEmptyRange` — provider over `null`, `''`, and `'   '`; each throws.
- `testOfficialTableRequiresAuthoritySourceAndLicense` — provider over each missing field; each throws.
- `testOfficialTableWithCompleteIdentityIsValid` — all fields set; serialization succeeds.
- `testArithmeticRuleMayOmitDigest` — a non-null range with a null digest is valid.
- `testEnumsHaveTheFrozenValues` — `HijriDateFrame::cases()`, `CalendarFamily::cases()`, and `CalendarCoverage::cases()` equal the spec values (name + value).

- [ ] **Step 2: Run the tests to verify they fail**

```bash
vendor/bin/phpunit --no-coverage --filter CalendarProvenanceTest
```

Expected: the enums, value object, and interface do not exist.

- [ ] **Step 3: Implement the enums**

```php
// src/Hijri/Enums/HijriDateFrame.php
enum HijriDateFrame: string
{
    case CIVIL_DATE = 'civil_date';
    case SUNSET_ROLLOVER = 'sunset_rollover';
}

// src/Hijri/Enums/CalendarFamily.php
enum CalendarFamily: string
{
    case TABULAR = 'tabular';
    case UMM_AL_QURA = 'umm_al_qura';
    case MABIMS = 'mabims';
}

// src/Hijri/Enums/CalendarCoverage.php
enum CalendarCoverage: string
{
    case ARITHMETIC_RULE = 'arithmetic_rule';
    case GENERATED_TABLE = 'generated_table';
    case OFFICIAL_TABLE = 'official_table';
}
```

Each file needs `declare(strict_types=1)`, the `AlFalaq\AlFalaq\Hijri\Enums` namespace, and a class docblock summary.

- [ ] **Step 4: Implement `CalendarProvenance`**

```php
final readonly class CalendarProvenance
{
    /**
     * Bind a validated calendar identity.
     *
     * @param string $key Calendar's stable name(), not a CalculationSource id.
     * @param CalendarFamily|null $family Family, or null for an undescribed custom calendar.
     * @param CalendarCoverage|null $coverage Coverage, or null for an undescribed custom calendar.
     * @param string|null $authority Member-state code for MABIMS; null otherwise.
     * @param string|null $range Covered span, required for table-backed calendars.
     * @param string|null $digest Lowercase SHA-256, required for table-backed calendars.
     * @param string|null $source Human-readable generator/pin reference.
     * @param string|null $license Redistribution status.
     */
    public function __construct(
        public string $key,
        public ?CalendarFamily $family,
        public ?CalendarCoverage $coverage,
        public ?string $authority = null,
        public ?string $range = null,
        public ?string $digest = null,
        public ?string $source = null,
        public ?string $license = null,
    ) {
        if (trim($key) === '') {
            throw new ValidationException('Calendar provenance key must not be blank.');
        }

        if (($family === null) !== ($coverage === null)) {
            throw new ValidationException('Calendar provenance family and coverage must both be set or both be null.');
        }

        if ($coverage === CalendarCoverage::GENERATED_TABLE || $coverage === CalendarCoverage::OFFICIAL_TABLE) {
            if ($digest === null || preg_match('/\A[a-f0-9]{64}\z/', $digest) !== 1) {
                throw new ValidationException('Table-backed calendar provenance requires a lowercase SHA-256 digest.');
            }

            if ($range === null || trim($range) === '') {
                throw new ValidationException('Table-backed calendar provenance requires a coverage range.');
            }
        }

        if ($coverage === CalendarCoverage::OFFICIAL_TABLE) {
            foreach (['authority' => $authority, 'source' => $source, 'license' => $license] as $name => $value) {
                if ($value === null || trim($value) === '') {
                    throw new ValidationException(sprintf('Official table provenance requires a %s.', $name));
                }
            }
        }
    }

    /**
     * Wire shape in constructor order; nullable facts are always present.
     *
     * @return array{
     *     key: string,
     *     family: string|null,
     *     coverage: string|null,
     *     authority: string|null,
     *     range: string|null,
     *     digest: string|null,
     *     source: string|null,
     *     license: string|null,
     * }
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'family' => $this->family?->value,
            'coverage' => $this->coverage?->value,
            'authority' => $this->authority,
            'range' => $this->range,
            'digest' => $this->digest,
            'source' => $this->source,
            'license' => $this->license,
        ];
    }
}
```

- [ ] **Step 5: Implement `DescribesCalendar`**

```php
interface DescribesCalendar
{
    /**
     * Stable, auditable identity for this calendar.
     *
     * @return CalendarProvenance Calendar key, family, coverage, and data identity.
     */
    public function calendarProvenance(): CalendarProvenance;
}
```

`DescribesCalendar` is a driven capability, so it keeps the short name. It is **not** added to `HijriCalendar`; custom calendars must remain source-compatible.

- [ ] **Step 6: Add the API-reference rows**

Add rows for the three enums, `CalendarProvenance` (constructor fields and `toArray()`), and `DescribesCalendar` (`calendarProvenance()`). Group the enums with the existing `Hijri\Enums` rows.

- [ ] **Step 7: Verify**

```bash
vendor/bin/phpunit --no-coverage --filter CalendarProvenanceTest
composer lint
composer stan
composer arch
```

- [ ] **Step 8: Commit**

```bash
git add src/Hijri/Enums/HijriDateFrame.php src/Hijri/Enums/CalendarFamily.php src/Hijri/Enums/CalendarCoverage.php src/Hijri/ValueObjects/CalendarProvenance.php src/Hijri/Contracts/DescribesCalendar.php tests/Hijri/CalendarProvenanceTest.php docs/user/api-reference.md
git commit -m "feat(hijri): add calendar provenance vocabulary"
```

---

## Task 2: Built-in calendar identities

**Files:**

- Modify `src/Hijri/Strategies/TabularCalendar.php`
- Modify `src/Hijri/Strategies/UmmAlQuraCalendar.php`
- Modify `src/Hijri/Strategies/MabimsCalendar.php`
- Modify `tests/Hijri/CalendarProvenanceTest.php`
- Modify `docs/user/api-reference.md`

- [ ] **Step 1: Write the failing identity tests**

In `CalendarProvenanceTest`, add:

- `testTabularCalendarDescribesItsArithmeticCoverage` — `new TabularCalendar()->calendarProvenance()->toArray()` is exactly:

```php
[
    'key' => 'tabular',
    'family' => 'tabular',
    'coverage' => 'arithmetic_rule',
    'authority' => null,
    'range' => '1900-01-01..2100-12-31 CE',
    'digest' => null,
    'source' => null,
    'license' => null,
]
```

- `testUmmAlQuraCalendarDescribesItsGeneratedTable` — key `uqu`, family `umm_al_qura`, coverage `generated_table`, authority `null`, range `1423-01..1500-12 AH`, digest `UmmAlQuraMonthStarts::SHA256`, source `tools/gen_hijri.py @ a7baa8b202790d3bc84c47a610ac763be6c0b7e7`, license `MIT`.
- `testEveryMabimsCountryDescribesItsOwnGeneratedTable` — data provider over `MabimsCountry::cases()`:
  - key `mabims`, family `mabims`, coverage `generated_table`;
  - authority equals `$country->value`;
  - range `1445-01..1500-12 AH`;
  - digest equals `MabimsMonthStarts::digestFor($country)`;
  - source `tools/gen_hijri.py @ a7baa8b202790d3bc84c47a610ac763be6c0b7e7`, license `MIT`.
- `testShippedCalendarsNeverClaimAnOfficialTable` — over `TabularCalendar`, `UmmAlQuraCalendar`, and every `MabimsCalendar`; coverage is never `CalendarCoverage::OFFICIAL_TABLE`.
- `testEveryShippedCalendarImplementsDescribesCalendar` — the three calendar classes (and every MABIMS country) are `instanceof DescribesCalendar`.

- [ ] **Step 2: Run the tests to verify they fail**

```bash
vendor/bin/phpunit --no-coverage --filter CalendarProvenanceTest
```

Expected: the three calendars do not implement `DescribesCalendar`.

- [ ] **Step 3: Implement the calendar identities**

`TabularCalendar`:

```php
final class TabularCalendar implements HijriCalendar, DescribesCalendar
{
    // ...
    private const PROVENANCE_RANGE = '1900-01-01..2100-12-31 CE';

    public function calendarProvenance(): CalendarProvenance
    {
        return new CalendarProvenance(
            $this->name(),
            CalendarFamily::TABULAR,
            CalendarCoverage::ARITHMETIC_RULE,
            range: self::PROVENANCE_RANGE,
        );
    }
}
```

`UmmAlQuraCalendar`:

```php
final class UmmAlQuraCalendar implements HijriCalendar, DescribesCalendar
{
    // ...
    public function calendarProvenance(): CalendarProvenance
    {
        return new CalendarProvenance(
            $this->name(),
            CalendarFamily::UMM_AL_QURA,
            CalendarCoverage::GENERATED_TABLE,
            range: sprintf(
                '%d-%02d..%d-%02d AH',
                UmmAlQuraMonthStarts::FIRST_YEAR,
                UmmAlQuraMonthStarts::FIRST_MONTH,
                UmmAlQuraMonthStarts::LAST_YEAR,
                UmmAlQuraMonthStarts::LAST_MONTH,
            ),
            digest: UmmAlQuraMonthStarts::SHA256,
            source: self::DATA_SOURCE,
            license: self::DATA_LICENSE,
        );
    }
}
```

`MabimsCalendar`:

```php
final class MabimsCalendar implements HijriCalendar, DescribesCalendar
{
    // ...
    public function calendarProvenance(): CalendarProvenance
    {
        return new CalendarProvenance(
            $this->name(),
            CalendarFamily::MABIMS,
            CalendarCoverage::GENERATED_TABLE,
            authority: $this->country->value,
            range: sprintf(
                '%d-%02d..%d-%02d AH',
                MabimsMonthStarts::FIRST_YEAR,
                MabimsMonthStarts::FIRST_MONTH,
                MabimsMonthStarts::LAST_YEAR,
                MabimsMonthStarts::LAST_MONTH,
            ),
            digest: MabimsMonthStarts::digestFor($this->country),
            source: self::DATA_SOURCE,
            license: self::DATA_LICENSE,
        );
    }
}
```

Add `private const DATA_SOURCE = 'tools/gen_hijri.py @ a7baa8b202790d3bc84c47a610ac763be6c0b7e7';` and `private const DATA_LICENSE = 'MIT';` to both table-backed strategies. Do not duplicate the digest constants; the data classes remain the source of truth.

`MabimsCalendar::name()` stays the generic `'mabims'` key for all four countries; `authority` carries the member state. The docs must tell consumers to key per-country corrections by `calendar.authority`, not `calendar.key`.

- [ ] **Step 4: Add the API-reference mentions**

Extend the three strategy rows with `DescribesCalendar` and the exact provenance values. Note that `uqu` is a generated table, not an official Saudi dataset.

- [ ] **Step 5: Verify**

```bash
vendor/bin/phpunit --no-coverage --filter CalendarProvenanceTest
composer lint
composer stan
composer arch
```

- [ ] **Step 6: Commit**

```bash
git add src/Hijri/Strategies/TabularCalendar.php src/Hijri/Strategies/UmmAlQuraCalendar.php src/Hijri/Strategies/MabimsCalendar.php tests/Hijri/CalendarProvenanceTest.php docs/user/api-reference.md
git commit -m "feat(hijri): describe built-in calendar identities"
```

---

## Task 3: `HijriDate::toArray()`, metadata, and outcome

**Files:**

- Modify `src/Hijri/ValueObjects/HijriDate.php`
- Modify `tests/Hijri/HijriDateTest.php`
- Create `src/Hijri/ValueObjects/HijriCalculationMetadata.php`
- Create `src/Hijri/ValueObjects/HijriCalculationOutcome.php`
- Create `tests/Hijri/HijriMetadataValueObjectsTest.php`
- Modify `docs/user/api-reference.md`

- [ ] **Step 1: Write the failing serializer and value-object tests**

In `HijriDateTest`, add:

- `testToArrayHasTheWireShape` — `new HijriDate(1448, 5, 25)->toArray()` is exactly `['year' => 1448, 'month' => 5, 'day' => 25, 'iso' => '1448-05-25']`.

In `HijriMetadataValueObjectsTest`:

**`HijriCalculationMetadata` wire shape**

- `testCivilMetadataMatchesTheWireShape` — build:

```php
$common = CalculationMetadata::create(CalculationContext::HIJRI, [], []);
$instant = new \DateTimeImmutable('2026-11-15 12:00:00', new \DateTimeZone('Asia/Kuala_Lumpur'));
$metadata = new HijriCalculationMetadata(
    $common,
    $instant,
    HijriDateFrame::CIVIL_DATE,
    CivilDate::fromYmd(2026, 11, 15),
    new CalendarProvenance(
        'tabular',
        CalendarFamily::TABULAR,
        CalendarCoverage::ARITHMETIC_RULE,
        range: '1900-01-01..2100-12-31 CE',
    ),
    null,
    false,
);
```

Assert the full array key order and values:

```php
[
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
]
```

- `testSunsetMetadataMatchesTheWireShape` — construct with `SUNSET_ROLLOVER`, coordinates, `CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters()`, `offsetDays = 0`, `maghribFallbackApplied = true`. Assert:
  - `input.coordinates` is `$coordinates->toArray()`;
  - `input.parameters` is exactly `$parameters->toArray()` (do not duplicate the policy snapshot);
  - `offsets = ['days' => 0, 'applied' => true]`;
  - `fallback = ['applied' => true, 'kind' => 'maghrib_only']`.
- `testOutcomeToArrayWrapsResultAndMetadata` — exactly `result` then `metadata`; `result` equals `HijriDate::toArray()`; `metadata` equals `HijriCalculationMetadata::toArray()`.

**Validation**

- `testNonHijriContextIsRejected` — `CalculationMetadata::create(CalculationContext::MOON, ...)` throws `ValidationException`.
- `testCivilFrameRejectsCoordinatesParametersAndFallback` — data provider over a non-null `Coordinates`, a non-null `CalculationParameters`, and `maghribFallbackApplied = true`; each throws.
- `testSunsetFrameRequiresCoordinatesAndParameters` — provider over a null coordinates and a null parameters; each throws.
- `testOffsetsAppliedIsDerivedFromANullableAnswer` — `offsetDays` `null` → `days = null`, `applied = false`; `0` → `days = 0`, `applied = true`; `2` → `days = 2`, `applied = true`.
- `testFallbackKindIsDerivedFromTheFlag` — false → `kind = null`; true → `kind = 'maghrib_only'`.
- `testRequestedInstantIsRenderedInUtcWithTheOriginalZone` — `2026-11-15 12:00:00 Asia/Kuala_Lumpur` renders `2026-11-15T04:00:00+00:00` and zone `Asia/Kuala_Lumpur`.

- [ ] **Step 2: Run the tests to verify they fail**

```bash
vendor/bin/phpunit --no-coverage --filter 'HijriMetadataValueObjectsTest|HijriDateTest'
```

Expected: `toArray()` and the two metadata classes are missing.

- [ ] **Step 3: Implement `HijriDate::toArray()`**

```php
/**
 * Wire shape: year, month, day, then ISO rendering.
 *
 * @return array{year: int, month: int, day: int, iso: string}
 */
public function toArray(): array
{
    return [
        'year' => $this->year,
        'month' => $this->month,
        'day' => $this->day,
        'iso' => $this->isoformat(),
    ];
}
```

Properties, validation, `isoformat()`, `compareTo()`, and `equals()` stay unchanged.

- [ ] **Step 4: Implement `HijriCalculationMetadata`**

```php
final readonly class HijriCalculationMetadata
{
    /**
     * Fallback kind used when the sunset frame resolves maghrib alone.
     */
    public const FALLBACK_MAGHRIB_ONLY = 'maghrib_only';

    /**
     * Bind validated Hijri provenance.
     *
     * @param CalculationMetadata $common Shared envelope.
     * @param \DateTimeImmutable $requestedInstant Caller's zone-aware instant.
     * @param HijriDateFrame $frame Frame that selected the civil date.
     * @param CivilDate $resolvedCivilDate Gregorian date actually converted.
     * @param CalendarProvenance $calendar Calendar identity.
     * @param int|null $offsetDays OffsetStore answer; null means no store.
     * @param bool $maghribFallbackApplied True when a sunset probe used maghribAt().
     * @param Coordinates|null $coordinates Sunset-frame observer; null for the civil frame.
     * @param CalculationParameters|null $parameters Sunset-frame policy; null for the civil frame.
     */
    public function __construct(
        public CalculationMetadata $common,
        public \DateTimeImmutable $requestedInstant,
        public HijriDateFrame $frame,
        public CivilDate $resolvedCivilDate,
        public CalendarProvenance $calendar,
        public ?int $offsetDays,
        public bool $maghribFallbackApplied,
        public ?Coordinates $coordinates = null,
        public ?CalculationParameters $parameters = null,
    ) {
        if ($common->context !== CalculationContext::HIJRI) {
            throw new ValidationException(sprintf(
                'Hijri metadata requires the hijri calculation context, got "%s".',
                $common->context->value,
            ));
        }

        if ($frame === HijriDateFrame::CIVIL_DATE) {
            if ($coordinates !== null || $parameters !== null) {
                throw new ValidationException('The civil-date frame must not carry coordinates or parameters.');
            }

            if ($maghribFallbackApplied) {
                throw new ValidationException('The civil-date frame cannot report a maghrib-only fallback.');
            }

            return;
        }

        if ($coordinates === null || $parameters === null) {
            throw new ValidationException('The sunset frame requires coordinates and parameters.');
        }
    }

    /**
     * Wire shape: common six keys, then input, effective, calendar, offsets, fallback.
     *
     * @return array{
     *     schema_version: string,
     *     context: string,
     *     status: string,
     *     library_version: string,
     *     warnings: list<array{code: string, message: string, details: array<string, int|float|string|bool|null>}>,
     *     sources: list<array{id: string, kind: string, version: string|null, digest: string|null, range: string|null}>,
     *     input: array{
     *         instant: string,
     *         zone: string,
     *         frame: string,
     *         coordinates: array{latitude: float, longitude: float}|null,
     *         parameters: array<string, mixed>|null,
     *     },
     *     effective: array{gregorian_date: string},
     *     calendar: array{
     *         key: string,
     *         family: string|null,
     *         coverage: string|null,
     *         authority: string|null,
     *         range: string|null,
     *         digest: string|null,
     *         source: string|null,
     *         license: string|null,
     *     },
     *     offsets: array{days: int|null, applied: bool},
     *     fallback: array{applied: bool, kind: string|null},
     * }
     */
    public function toArray(): array
    {
        return [
            ...$this->common->toArray(),
            'input' => [
                'instant' => $this->requestedInstant
                    ->setTimezone(new \DateTimeZone('UTC'))
                    ->format('Y-m-d\TH:i:sP'),
                'zone' => $this->requestedInstant->getTimezone()->getName(),
                'frame' => $this->frame->value,
                'coordinates' => $this->coordinates?->toArray(),
                'parameters' => $this->parameters?->toArray(),
            ],
            'effective' => [
                'gregorian_date' => $this->resolvedCivilDate->isoformat(),
            ],
            'calendar' => $this->calendar->toArray(),
            'offsets' => [
                'days' => $this->offsetDays,
                'applied' => $this->offsetDays !== null,
            ],
            'fallback' => [
                'applied' => $this->maghribFallbackApplied,
                'kind' => $this->maghribFallbackApplied ? self::FALLBACK_MAGHRIB_ONLY : null,
            ],
        ];
    }
}
```

`Hijri\ValueObjects` may import `Prayer\ValueObjects\CalculationParameters`; the existing converter already composes the Prayer pipeline. It must not import `Astro` or `Moon`.

- [ ] **Step 5: Implement `HijriCalculationOutcome`**

```php
final readonly class HijriCalculationOutcome
{
    /**
     * Bind a result and its provenance.
     *
     * @param HijriDate $result Converted date (same value the compact path returns).
     * @param HijriCalculationMetadata $metadata Provenance for this conversion.
     */
    public function __construct(
        public HijriDate $result,
        public HijriCalculationMetadata $metadata,
    ) {}

    /**
     * Wire shape: result then metadata.
     *
     * @return array{
     *     result: array{year: int, month: int, day: int, iso: string},
     *     metadata: array<string, mixed>,
     * }
     */
    public function toArray(): array
    {
        return [
            'result' => $this->result->toArray(),
            'metadata' => $this->metadata->toArray(),
        ];
    }
}
```

- [ ] **Step 6: Add the API-reference rows**

- Extend `Hijri\ValueObjects\HijriDate` with `toArray()`.
- Add `Hijri\ValueObjects\HijriCalculationMetadata` (constructor fields, `FALLBACK_MAGHRIB_ONLY`, `toArray()`).
- Add `Hijri\ValueObjects\HijriCalculationOutcome` (`result` + `metadata`, `toArray()`).

- [ ] **Step 7: Verify**

```bash
vendor/bin/phpunit --no-coverage --filter 'HijriMetadataValueObjectsTest|HijriDateTest'
composer lint
composer stan
composer arch
```

- [ ] **Step 8: Commit**

```bash
git add src/Hijri/ValueObjects/HijriDate.php src/Hijri/ValueObjects/HijriCalculationMetadata.php src/Hijri/ValueObjects/HijriCalculationOutcome.php tests/Hijri/HijriDateTest.php tests/Hijri/HijriMetadataValueObjectsTest.php docs/user/api-reference.md
git commit -m "feat(hijri): add calculation metadata value objects"
```

---

## Task 4: Converter trace and `convertWithMetadata()`

**Files:**

- Modify `src/Hijri/Services/GregorianToHijriConverter.php`
- Create `tests/Hijri/HijriMetadataTest.php`
- Modify `docs/user/api-reference.md`

- [ ] **Step 1: Write the failing converter-metadata tests**

`tests/Hijri/HijriMetadataTest.php` uses the existing converter fixtures (KL, Honolulu, Tromsø). Helpers mirror `GregorianToHijriConverterTest`:

```php
private function kualaLumpur(): Coordinates
private function honolulu(): Coordinates
private function parameters(): CalculationParameters
private function maghrib(Coordinates $coordinates, CivilDate $date): \DateTimeImmutable
private function offsets(int $days): OffsetStore
```

**Compact-path equality**

- `testCompactConvertEqualsMetadataResult` — data provider over:
  - calendars: `TabularCalendar`, `UmmAlQuraCalendar`, `MabimsCalendar(MY)`, `MabimsCalendar(SG)`;
  - frames: civil (`2025-03-01T12:00:00Z`, no coordinates) and sunset (`2025-03-01T20:00:00Z`, KL + parameters, `changeAtSunset: true`);
  - offsets: `null`, `0`, `2`.

  Assert `convert(...)->toArray() === convertWithMetadata(...)->result->toArray()` for every combination.
- `testMetadataPathCallsTheOffsetStoreOnce` — a counting `OffsetStore`; `convertWithMetadata()` records exactly one `offsetDaysFor()` call.
- `testCompactConvertDoesNotReadCalendarProvenance` — a delegating `HijriCalendar` that implements `DescribesCalendar` and throws `\RuntimeException` from `calendarProvenance()`:
  - `convert()` returns the converted date;
  - `convertWithMetadata()` throws the same `\RuntimeException`.
- `testMetadataPathReadsCalendarProvenanceOnce` — the same double with a counter; `convertWithMetadata()` calls `calendarProvenance()` exactly once; `convert()` calls it zero times.

**Civil frame**

- `testCivilFrameReportsTheLocalDateWithoutProvenanceInputs` — `2024-03-01T20:00:00Z` labeled `Pacific/Honolulu` (local `2024-03-01`):
  - `input.frame = 'civil_date'`, `input.instant = '2024-03-01T20:00:00+00:00'`, `input.zone = 'Pacific/Honolulu'`;
  - `input.coordinates = null`, `input.parameters = null`;
  - `effective.gregorian_date = '2024-03-01'`;
  - `sources = []` and `fallback = ['applied' => false, 'kind' => null]`;
  - `status = 'complete'` with a `tabular_calendar_estimate` warning.
- `testCivilFrameIgnoresCoordinatesAndParameters` — pass coordinates and parameters with `changeAtSunset: false`; metadata still serializes both as `null`, and the result equals the call without them.

**Sunset frame**

- `testSunsetFrameReportsTheUtcDateAndProbeSource` — the same instant with KL coordinates, parameters, and `changeAtSunset: true`:
  - `frame = 'sunset_rollover'`, `effective.gregorian_date = '2024-03-02'`;
  - `input.zone = 'Pacific/Honolulu'` (the caller's label, not the frame);
  - `input.coordinates` and `input.parameters` are present;
  - `sources` is exactly `hijri.maghrib-probe` (algorithm, null version/digest/range);
  - no `sunset_frame_maghrib_fallback` warning; status `complete`.
- `testSunsetFrameRequiresCoordinatesAndParameters` — both `convert()` and `convertWithMetadata()` throw `ConfigurationException` when either is missing; no partial outcome.
- `testMaghribBoundaryRollsForwardInMetadata` — KL `2024-01-02`:
  - one second before `11:15:00Z` → effective `2024-01-02`;
  - exactly at `11:15:00Z` → effective `2024-01-03`.
- `testBackwardProbeRollsBackInMetadata` — Honolulu `2024-01-02`:
  - one second before the previous day's `04:01:00Z` maghrib → effective `2024-01-01`;
  - exactly at that instant → effective `2024-01-02`.
- `testLowerBoundarySkipsTheBackwardProbeInMetadata` — `1900-01-01T06:00:00Z` → effective `1900-01-01`, no failure, no fallback.

**Offsets**

- `testNoStoreIsDistinctFromAZeroAnswer`:
  - no store: `offsets = ['days' => null, 'applied' => false]`; no `hijri.offset.caller` source; tabular warning `adjustment_days = 0`;
  - store returns `0`: `offsets = ['days' => 0, 'applied' => true]`; `hijri.offset.caller` present; result unchanged; `adjustment_days = 0`;
  - store returns `2`: `offsets = ['days' => 2, 'applied' => true]`; result equals `$calendar->shift($plain, 2)`; `adjustment_days = 2`.
- `testOffsetSourceIsCallerInput` — `hijri.offset.caller` has kind `caller_input` and null version/digest/range.

**Maghrib-only fallback**

- `testMaghribOnlyFallbackIsVisibleWhenTheDateIsUnchanged` — Tromsø `2025-11-27T08:00:00Z`, `PolarCircleRule::NONE`:
  - `fallback = ['applied' => true, 'kind' => 'maghrib_only']`;
  - one `sunset_frame_maghrib_fallback` warning with `details = ['date' => '2025-11-27', 'reason' => 'full_schedule_undefined']`;
  - status `fallback_applied`;
  - effective date `2025-11-27`;
  - `hijri.maghrib-probe` still listed.
- `testMaghribOnlyFallbackWithRolloverReportsTheEffectiveDate` — same fixture at `20:00:00Z`:
  - effective date `2025-11-28`;
  - the same warning with `date = '2025-11-28'`;
  - status `fallback_applied`.
- `testNormalSunsetFrameHasNoFallback` — KL normal evening: `applied = false`, no fallback warning, status `complete`.
- `testUndefinedMaghribStillPropagates` — Tromsø `2025-06-21` (polar day) with `PolarCircleRule::NONE`: both methods throw `AstronomicalException`; no partial outcome.
- `testFallbackFlagAggregatesAcrossForwardAndBackwardProbes` — a delegating `PrayerTimesCalculatorInterface` that throws `UndefinedScheduleException` for the full schedule of `2024-01-01` but delegates `maghribAt()` and every other date to the real calculator. With Honolulu and an instant one second before `2024-01-01`'s maghrib:
  - the forward probe for `2024-01-02` is defined;
  - the backward probe for `2024-01-01` uses `maghribAt()`;
  - effective date is `2024-01-01`;
  - `fallback.applied = true`, warning date `2024-01-01`;
  - this is the aggregation proof; a per-probe-only flag would miss it.

**Custom calendars**

- `testCustomCalendarWithoutDescribesCalendarIsReportedAsUnknown`:
  - a delegating `HijriCalendar` without `DescribesCalendar`, `name() = 'custom'`;
  - `calendar` block: key `custom`; family/coverage/authority/range/digest/source/license all `null`;
  - exactly one `unidentified_calculation_source` warning with `details = ['component' => 'calendar', 'source_id' => 'custom.calendar']`;
  - no `custom.calendar` entry in `sources`;
  - no `generated_calendar_table` or `tabular_calendar_estimate` warning;
  - status `complete`.
- `testCustomGeneratedCalendarIsLabelledHonestly` — a custom calendar implementing `DescribesCalendar` with `GENERATED_TABLE`, a valid digest and range, `authority = 'XX'`, `source`/`license`:
  - `generated_calendar_table` warning with the custom key and range;
  - no `unidentified_calculation_source` warning;
  - status `complete`.
- `testOfficialTableCoverageEmitsNoCoverageWarning` — a custom calendar implementing `DescribesCalendar` with `OFFICIAL_TABLE`, authority, range, digest, source, and license:
  - no coverage warning and no unidentified warning;
  - status `complete`.
- `testCustomCalendarKeyMayUseNonSourceGrammar` — a custom calendar named `'My Calendar'` serializes that key in `calendar.key` without trying to build a `CalculationSource` id from it.

**Wire shape and determinism**

- `testSunsetOutcomeMatchesTheFullWireShape` — MABIMS MY, `2026-11-14T20:00:00+08:00` (= `2026-11-14T12:00:00Z`), `CalculationMethod::SINGAPORE`, `changeAtSunset: true`, offset store `0`:
  - result is the calendar's `1448-06-05` (compute it in the test, do not hardcode the triple twice);
  - `input` has the UTC instant `2026-11-14T12:00:00+00:00`, zone `Asia/Kuala_Lumpur`, frame `sunset_rollover`, the KL coordinates, and `$parameters->toArray()`;
  - `effective.gregorian_date = '2026-11-15'` (the 14 November evening rolls into 15 November);
  - `sources` is exactly `hijri.maghrib-probe` then `hijri.offset.caller` (id order);
  - warnings contain `generated_calendar_table` with `calendar = 'mabims'`, `range = '1445-01..1500-12 AH'`;
  - `calendar` is the exact MABIMS MY block with `authority = 'MY'` and `MabimsMonthStarts::digestFor(MY)`;
  - `offsets = ['days' => 0, 'applied' => true]`;
  - `fallback = ['applied' => false, 'kind' => null]`;
  - key order is the common six, then `input`, `effective`, `calendar`, `offsets`, `fallback`.
- `testWarningOrderIsDeterministic` — Tromsø + `TabularCalendar` + offset store `0`:
  - warning codes are `sunset_frame_maghrib_fallback` then `tabular_calendar_estimate` (code order);
  - status `fallback_applied` (highest rank wins).
- `testMetadataIsDeterministic` — two identical calls produce identical `toArray()` output.
- `testJsonEncodingSucceedsAndLeaksNoInternals` — `json_encode($outcome->toArray(), JSON_THROW_ON_ERROR)` succeeds; the JSON contains no `AlFalaq\\`, `HijriCalendar`, `DateTime`, or `Astro` token.
- `testOutcomeToArrayWrapsTheCompactPayload` — `result` equals `convert(...)->toArray()`; `metadata` equals `outcome->metadata->toArray()`.

- [ ] **Step 2: Run the tests to verify they fail**

```bash
vendor/bin/phpunit --no-coverage --filter HijriMetadataTest
```

Expected: `convertWithMetadata()` is missing.

- [ ] **Step 3: Refactor the converter to one private trace**

Add `use DescribesCalendar;` and the two public source-id constants:

```php
public const MAGHRIB_PROBE_SOURCE_ID = 'hijri.maghrib-probe';
public const OFFSET_SOURCE_ID = 'hijri.offset.caller';
```

Keep `convert()`'s signature and docblock. Replace its body with:

```php
public function convert(
    \DateTimeImmutable $dateTime,
    HijriCalendar $calendar,
    ?Coordinates $coordinates = null,
    ?CalculationParameters $parameters = null,
    bool $changeAtSunset = false,
    ?OffsetStore $offsets = null,
): HijriDate {
    return $this->trace($dateTime, $calendar, $coordinates, $parameters, $changeAtSunset, $offsets)['date'];
}
```

Add:

```php
/**
 * Shared numeric path: conversion, rollover, and offset application.
 *
 * The compact path returns `date` and never reads calendar provenance.
 *
 * @param \DateTimeImmutable $dateTime Source instant (zone-aware).
 * @param HijriCalendar $calendar Target calendar system.
 * @param Coordinates|null $coordinates Required with changeAtSunset (maghrib probe).
 * @param CalculationParameters|null $parameters Required with changeAtSunset (maghrib).
 * @param bool $changeAtSunset Roll the civil date at the observer's maghrib.
 * @param OffsetStore|null $offsets Official day corrections, if any.
 * @return array{
 *     date: HijriDate,
 *     resolvedCivilDate: CivilDate,
 *     offsetDays: int|null,
 *     maghribFallbackApplied: bool,
 * }
 */
private function trace(
    \DateTimeImmutable $dateTime,
    HijriCalendar $calendar,
    ?Coordinates $coordinates,
    ?CalculationParameters $parameters,
    bool $changeAtSunset,
    ?OffsetStore $offsets,
): array {
    $resolved = $this->resolveCivilDate($dateTime, $coordinates, $parameters, $changeAtSunset);
    $hijri = $calendar->fromGregorian($resolved['civilDate']);
    $offsetDays = $offsets?->offsetDaysFor($hijri, $calendar);

    return [
        'date' => $offsetDays === null ? $hijri : $calendar->shift($hijri, $offsetDays),
        'resolvedCivilDate' => $resolved['civilDate'],
        'offsetDays' => $offsetDays,
        'maghribFallbackApplied' => $resolved['maghribFallbackApplied'],
    ];
}
```

Change `resolveCivilDate()` to return the frame and the aggregated flag. Keep the existing order, comments, and exception behavior:

```php
/**
 * @return array{civilDate: CivilDate, maghribFallbackApplied: bool}
 */
private function resolveCivilDate(
    \DateTimeImmutable $dateTime,
    ?Coordinates $coordinates,
    ?CalculationParameters $parameters,
    bool $changeAtSunset,
): array {
    if (! $changeAtSunset) {
        return [
            'civilDate' => CivilDate::fromDateTime($dateTime),
            'maghribFallbackApplied' => false,
        ];
    }

    if ($coordinates === null || $parameters === null) {
        throw new ConfigurationException('changeAtSunset requires both $coordinates and $parameters.');
    }

    $utc = CivilDate::fromDateTime($dateTime->setTimezone(new \DateTimeZone('UTC')));
    $forward = $this->maghribProbe($coordinates, $parameters, $utc);

    if ($dateTime >= $forward['instant']) {
        return [
            'civilDate' => $utc->plusDays(1),
            'maghribFallbackApplied' => $forward['fallbackApplied'],
        ];
    }

    $fallback = $forward['fallbackApplied'];

    try {
        $previous = $utc->plusDays(-1);
    } catch (ValidationException) {
        // 1900-01-01 is the first CivilDate; there is no previous day to
        // probe, so the instant belongs to the current UTC day.
        return ['civilDate' => $utc, 'maghribFallbackApplied' => $fallback];
    }

    try {
        $backward = $this->maghribProbe($coordinates, $parameters, $previous);
    } catch (AstronomicalException) {
        // The previous day's schedule can be undefined (for example polar
        // night) while the current day's maghrib is defined. Without a
        // previous maghrib there is no evidence of a backward roll.
        return ['civilDate' => $utc, 'maghribFallbackApplied' => $fallback];
    }

    $fallback = $fallback || $backward['fallbackApplied'];

    return [
        'civilDate' => $dateTime < $backward['instant'] ? $previous : $utc,
        'maghribFallbackApplied' => $fallback,
    ];
}
```

Replace `maghrib()` with `maghribProbe()`:

```php
/**
 * Predicted maghrib instant plus whether the maghrib-only fallback ran.
 *
 * @param Coordinates $coordinates Observer.
 * @param CalculationParameters $parameters Calculation method.
 * @param CivilDate $date UTC civil day to evaluate.
 * @return array{instant: \DateTimeImmutable, fallbackApplied: bool}
 */
private function maghribProbe(
    Coordinates $coordinates,
    CalculationParameters $parameters,
    CivilDate $date,
): array {
    try {
        return [
            'instant' => $this->calculator->calculate($coordinates, $date, $parameters)->timeFor(Prayer::MAGHRIB),
            'fallbackApplied' => false,
        ];
    } catch (AstronomicalException) {
        // Any astronomical "schedule undefined" — polar day/night or an
        // undefined Fajr/Asr/Isha component — falls back to maghrib alone.
        // A genuinely undefined maghrib still propagates; validation and
        // configuration errors are not AstronomicalException and surface.
        return [
            'instant' => $this->calculator->maghribAt($coordinates, $date, $parameters),
            'fallbackApplied' => true,
        ];
    }
}
```

Do not change any numeric statement, comparison, comment, or exception type. The existing `GregorianToHijriConverterTest` is the regression proof for Task 4's refactor.

- [ ] **Step 4: Implement `convertWithMetadata()`**

```php
/**
 * Convert one instant and report the calendar, frame, offsets, and fallback.
 *
 * Shares trace() with convert(); provenance is read only after the numeric
 * path succeeds. The compact path never calls DescribesCalendar.
 *
 * @param \DateTimeImmutable $dateTime Source instant (zone-aware).
 * @param HijriCalendar $calendar Target calendar system.
 * @param Coordinates|null $coordinates Required with changeAtSunset (maghrib probe).
 * @param CalculationParameters|null $parameters Required with changeAtSunset (maghrib).
 * @param bool $changeAtSunset Roll the civil date at the observer's maghrib.
 * @param OffsetStore|null $offsets Official day corrections, if any.
 * @return HijriCalculationOutcome Converted date plus provenance.
 */
public function convertWithMetadata(
    \DateTimeImmutable $dateTime,
    HijriCalendar $calendar,
    ?Coordinates $coordinates = null,
    ?CalculationParameters $parameters = null,
    bool $changeAtSunset = false,
    ?OffsetStore $offsets = null,
): HijriCalculationOutcome {
    $trace = $this->trace($dateTime, $calendar, $coordinates, $parameters, $changeAtSunset, $offsets);
    $warnings = [];
    $sources = [];

    if ($changeAtSunset) {
        $sources[] = CalculationSource::algorithm(self::MAGHRIB_PROBE_SOURCE_ID);
    }

    if ($offsets !== null) {
        $sources[] = CalculationSource::callerInput(self::OFFSET_SOURCE_ID);
    }

    $provenance = self::calendarProvenance($calendar, $warnings);

    if ($provenance->coverage === CalendarCoverage::GENERATED_TABLE) {
        $warnings[] = new CalculationWarning(
            WarningCode::GENERATED_CALENDAR_TABLE,
            'This calendar is a generated table, not an official authority dataset.',
            [
                'calendar' => $provenance->key,
                'range' => $provenance->range,
            ],
        );
    } elseif ($provenance->coverage === CalendarCoverage::ARITHMETIC_RULE) {
        $warnings[] = new CalculationWarning(
            WarningCode::TABULAR_CALENDAR_ESTIMATE,
            'This calendar uses an arithmetic month rule, not an observed month length.',
            ['adjustment_days' => $trace['offsetDays'] ?? 0],
        );
    }

    if ($trace['maghribFallbackApplied']) {
        $warnings[] = new CalculationWarning(
            WarningCode::SUNSET_FRAME_MAGHRIB_FALLBACK,
            'The full prayer schedule was undefined while resolving the sunset roll; maghrib alone was used. The date is the effective Gregorian date.',
            [
                'date' => $trace['resolvedCivilDate']->isoformat(),
                'reason' => 'full_schedule_undefined',
            ],
        );
    }

    return new HijriCalculationOutcome(
        $trace['date'],
        new HijriCalculationMetadata(
            CalculationMetadata::create(CalculationContext::HIJRI, $warnings, $sources),
            $dateTime,
            $changeAtSunset ? HijriDateFrame::SUNSET_ROLLOVER : HijriDateFrame::CIVIL_DATE,
            $trace['resolvedCivilDate'],
            $provenance,
            $trace['offsetDays'],
            $trace['maghribFallbackApplied'],
            $changeAtSunset ? $coordinates : null,
            $changeAtSunset ? $parameters : null,
        ),
    );
}
```

Add the calendar-provenance resolver:

```php
/**
 * Read the calendar's own identity, or report an undescribed custom calendar.
 *
 * The calendar is reported in the metadata's `calendar` block, not as a
 * CalculationSource. A custom calendar adds only the warning.
 *
 * @param HijriCalendar $calendar Wired calendar.
 * @param list<CalculationWarning> $warnings Warning sink, appended in place.
 * @return CalendarProvenance Described identity or a key-only fallback.
 */
private static function calendarProvenance(HijriCalendar $calendar, array &$warnings): CalendarProvenance
{
    if ($calendar instanceof DescribesCalendar) {
        return $calendar->calendarProvenance();
    }

    $warnings[] = new CalculationWarning(
        WarningCode::UNIDENTIFIED_CALCULATION_SOURCE,
        'The calendar component does not implement DescribesCalendar.',
        ['component' => 'calendar', 'source_id' => 'custom.calendar'],
    );

    return new CalendarProvenance($calendar->name(), null, null);
}
```

The coverage warning is driven by the reported `coverage`, not by a class-name check. A custom generated table is labelled honestly; an official table emits no coverage warning.

- [ ] **Step 5: Add the API-reference method and constant mentions**

Extend the `Hijri\Services\GregorianToHijriConverter` row with `convertWithMetadata(...)`, `MAGHRIB_PROBE_SOURCE_ID`, and `OFFSET_SOURCE_ID`.

- [ ] **Step 6: Verify**

```bash
vendor/bin/phpunit --no-coverage --filter 'HijriMetadataTest|GregorianToHijriConverterTest|HijriMetadataValueObjectsTest|CalendarProvenanceTest'
composer lint
composer stan
composer arch
```

- [ ] **Step 7: Commit**

```bash
git add src/Hijri/Services/GregorianToHijriConverter.php tests/Hijri/HijriMetadataTest.php docs/user/api-reference.md
git commit -m "feat(hijri): add metadata-aware Gregorian conversion"
```

---

## Task 5: Documentation, integration snippet, benchmark, changelog, and final gates

**Files:**

- Modify `docs/provenance.md`
- Modify `docs/user/calculation-metadata.md`
- Modify `docs/hijri.md`
- Modify `docs/user/hijri-converter.md`
- Modify `docs/user/integration.md`
- Modify `docs/user/migration.md`
- Modify `docs/integrations.md`
- Modify `docs/README.md`
- Modify `ARCHITECTURE.md`
- Modify `AGENTS.md`, `CONTEXT.md`
- Modify `CHANGELOG.md`
- Modify `docs/user/changelog.md` (generated, do not hand-edit)
- Modify `examples/bench.php`
- Modify `tests/Wiring/CalculatorWiringTest.php`

- [ ] **Step 1: Update the contributor page**

In `docs/provenance.md`:

- Intro: A1, A2, and A3 shipped; the provenance surface is complete for prayer, Qibla, Moon, and Hijri.
- Warning table: A3 now emits `sunset_frame_maghrib_fallback`, `generated_calendar_table`, `tabular_calendar_estimate`, and `unidentified_calculation_source` for calendars. Remove the "three Hijri codes remain reserved" sentence.
- Source registry: move `hijri.maghrib-probe` and `hijri.offset.caller` out of "reserved" into a shipped Hijri registry.
- Add a "Hijri metadata" section with:
  - the `CalendarProvenance` table for `tabular`, `uqu`, and `mabims` (key, family, coverage, authority, range, digest, source, license);
  - the rule that the calendar is reported in `calendar`, not `sources`;
  - sunset frame → `hijri.maghrib-probe`; supplied `OffsetStore` → `hijri.offset.caller`; neither → no Hijri source;
  - `offsets.days` distinguishes no store (`null`) from a real zero (`0`) and `offsets.applied` is `days !== null`;
  - `fallback.kind = maghrib_only` plus `sunset_frame_maghrib_fallback`; the warning's `date` is the resolved civil date (the effective Gregorian date), not necessarily the probe date;
  - coverage-driven warnings: `generated_table` → `generated_calendar_table`, `arithmetic_rule` → `tabular_calendar_estimate`, `official_table` → no coverage warning;
  - a custom calendar without `DescribesCalendar` gets `custom.calendar` + `unidentified_calculation_source`, but no source entry;
  - MABIMS consumers must key per-country corrections by `calendar.authority`, not `calendar.key`.
- Implementation notes:
  - `convert()` returns `trace()['date']` and never calls `DescribesCalendar`; a calendar whose `calendarProvenance()` throws still converts through the compact path;
  - the fallback flag is aggregated across the forward and backward probes;
  - `HijriCalculationMetadata` validates the frame/input pairing.
- Reproducibility recipe: same calendar key *and* MABIMS country, same frame, same parameters, same offset store, same library version.
- Compact methods list: add `convert()` to the compact methods that expose no warnings, and state explicitly that `convert()` is not deprecated.
- Replace every "legacy" reference in `docs/provenance.md` and `docs/user/calculation-metadata.md` with "compact" terminology; do not leave wording that implies the compact methods are deprecated.

- [ ] **Step 2: Update the published guide**

In `docs/user/calculation-metadata.md`:

- Title/description and intro: four contexts — prayer, Qibla, Moon, and Hijri. Remove "Hijri metadata remains a later phase."
- Add `## Hijri` with:
  - `GregorianToHijriConverter::default()->convertWithMetadata(...)` for a sunset-frame MABIMS conversion;
  - a full JSON example generated from the implementation, showing `result`, the common six keys, `input` (instant/zone/frame/coordinates/parameters), `effective.gregorian_date`, `calendar`, `offsets`, and `fallback`;
  - a civil-date variant showing `coordinates`/`parameters` as `null` and no `hijri.maghrib-probe` source;
  - the offset-store variants (`null`, `0`, non-zero) and the `applied` flag;
  - the maghrib-only fallback example and the unchanged-date case, stating that the warning's `date` is the effective Gregorian date, not necessarily the probe date;
  - the MABIMS `authority` note.
- Rewrite the existing `## Generated and official Hijri tables` section in present tense and place it under `## Hijri` (or keep it as a sub-note there).
- Replace "The legacy methods expose no warnings" with "The compact methods expose no warnings", and state explicitly that `convert()` is not deprecated.
- Update `## Handling warnings at your boundary` with the three Hijri codes and `fallback_applied`.
- Every JSON block must be valid JSON; generate the examples from the implementation, then re-indent them for readability. Do not hand-invent digests or dates.

- [ ] **Step 3: Update the Hijri appendix and user pointers**

- `docs/hijri.md`: add a `## Provenance` section with `convertWithMetadata()`, the calendar block fields, the frame/offset/fallback semantics, and the rule that `convert()` is the non-deprecated compact path and does not read provenance. Link `user/calculation-metadata.md#hijri`.
- `docs/user/hijri-converter.md`: one-sentence pointer to the metadata guide.
- `docs/user/integration.md`: extend the metadata pointer to mention Hijri conversion.
- `docs/user/migration.md`: name `convertWithMetadata()` in the additive provenance note.

- [ ] **Step 4: Add the executable integration snippet**

In `docs/integrations.md`, add a `## Hijri metadata` section after the existing `## Hijri conversion` section:

```php
use AlFalaq\AlFalaq\Hijri\Enums\MabimsCountry;
use AlFalaq\AlFalaq\Hijri\Services\GregorianToHijriConverter;
use AlFalaq\AlFalaq\Hijri\Strategies\MabimsCalendar;
use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

$hijriMetadataOutcome = GregorianToHijriConverter::default()->convertWithMetadata(
    new \DateTimeImmutable('2026-11-14 20:00:00', new \DateTimeZone('Asia/Kuala_Lumpur')),
    new MabimsCalendar(MabimsCountry::MY),
    Coordinates::fromDegrees(3.1390, 101.6869),
    CalculationMethod::SINGAPORE->defaultParameters(),
    changeAtSunset: true,
);
$hijriMetadata = $hijriMetadataOutcome->metadata->toArray();
```

The snippet's instant is `12:00Z` on 14 November, after the KL maghrib, so the UTC date rolls forward to 15 November predictably. The result stays a `HijriDate`; the metadata reports `calendar.key = mabims`, `calendar.authority = MY`, and the generated-table warning.

Extend `tests/Wiring/CalculatorWiringTest.php`:

- import `Hijri\ValueObjects\HijriCalculationOutcome`;
- add the section expectation:

```php
'Hijri metadata' => [
    'hijriMetadataOutcome' => HijriCalculationOutcome::class,
    'hijriMetadata' => 'array',
],
```

- add assertions after the Moon metadata assertions:

```php
$hijriMetadataOutcome = $bySection['Hijri metadata']['hijriMetadataOutcome'] ?? null;
self::assertInstanceOf(HijriCalculationOutcome::class, $hijriMetadataOutcome);
self::assertSame('hijri', $hijriMetadataOutcome->metadata->common->context->value);
```

Do not pin warning text or numeric values in the wiring test.

- [ ] **Step 5: Update the architecture and status docs**

- `ARCHITECTURE.md` §8 provenance subsection: add the Hijri `trace()` single-path rule, the `DescribesCalendar` capability, the calendar-not-a-source rule, the fallback aggregation, and the coverage warnings.
- `AGENTS.md`: status says provenance A1, A2, and A3 landed (prayer/timetable, Qibla, Moon, Hijri). Add gotchas:
  - `convert()` must remain provenance-free; only `convertWithMetadata()` reads `DescribesCalendar`;
  - a custom calendar is reported in `calendar`, not `sources`;
  - `Hijri\ValueObjects` may import `Prayer\ValueObjects` but must not import `Astro` or `Moon`.
- `CONTEXT.md`: add A3 to the current-status section; do not claim P0.2.
- `docs/README.md`: the design-spec row already links the A1, A2, and A3 plans from the plan commit; confirm the link still resolves after the implementation changes and that `ArchDocsTest` stays green.

- [ ] **Step 6: Update the benchmark harness**

In `examples/bench.php`, keep every existing tripwire and measurement unchanged. Add an A3 provenance line after the A2 line:

```php
// A3 provenance: the Hijri sunset-frame envelope (NOT gated).
$hijriConverter = GregorianToHijriConverter::default();
$hijriCalendar = new UmmAlQuraCalendar();
$hijriInstant = new \DateTimeImmutable('2025-03-01 20:00:00', new \DateTimeZone('UTC'));
$hijriSamples = [];

for ($i = 0; $i < 11; $i++) {
    $start = hrtime(true);
    $hijriConverter->convertWithMetadata($hijriInstant, $hijriCalendar, $coords, $params, true);
    $hijriSamples[] = (hrtime(true) - $start) / 1e6;
}

sort($hijriSamples);

printf(
    "bench: A3 metadata (NOT gated): hijri median %.3f ms (11 runs)\n",
    $hijriSamples[5],
);
```

Add the imports for `GregorianToHijriConverter` and `UmmAlQuraCalendar`. Do not fold this into the existing tripwires.

- [ ] **Step 7: Changelog and generated page**

Add under `CHANGELOG.md` → `[Unreleased]` → `### Added`:

- opt-in Hijri metadata: `GregorianToHijriConverter::convertWithMetadata()` returns `HijriCalculationOutcome` with `HijriCalculationMetadata`, covering calendar key/family/coverage, MABIMS country authority, span, digest, offset source, frame, and maghrib-only fallback;
- `CalendarProvenance`, `DescribesCalendar`, and the `HijriDateFrame` / `CalendarFamily` / `CalendarCoverage` enums;
- built-in calendar identities on `TabularCalendar`, `UmmAlQuraCalendar`, and `MabimsCalendar`, with generated-table warnings and per-country MABIMS digests;
- `HijriDate::toArray()`;
- generated/arithmetic/official coverage semantics and the `sunset_frame_maghrib_fallback`, `generated_calendar_table`, and `tabular_calendar_estimate` warnings;
- docs updates for Hijri metadata.

Then:

```bash
composer changelog
composer changelog:check
```

Never hand-edit `docs/user/changelog.md`.

- [ ] **Step 8: Run the complete gate**

```bash
composer validate --strict
composer lint
composer cs
composer stan
composer test-unit
composer test
composer arch
composer changelog:check
composer ci
```

Expected: 0 lint errors, 0 CS findings, 0 PHPStan errors, coverage ≥ 95%, all tests green, 32+ arch tests green.

- [ ] **Step 9: Run the mutation and performance gates**

```bash
COMPOSER_PROCESS_TIMEOUT=0 composer mutation
composer bench
```

- Mutation: MSI ≥ 85. If a new mutant escapes, add the missing assertion; do not lower the gate and do not add a baseline entry.
- Benchmark: existing tripwires pass; record the baseline, A1, A2, and A3 medians from the output.

- [ ] **Step 10: Build the docs site**

```bash
cd docs_site
npm run build
cd ..
```

Expected: the updated metadata page renders, the existing sidebar entry resolves, and no version snapshot is modified. If the build needs dependencies, run `npm ci` first; do not commit `node_modules/`.

- [ ] **Step 11: Verify the frozen surface**

```bash
git diff --stat main...HEAD
git status --short
```

Check:

- no changes under `docs/user/v*/`;
- no changes to `src/Hijri/Contracts/HijriCalendar.php` or `src/Hijri/Contracts/OffsetStore.php`;
- no change to `convert()`'s signature or return type;
- no new runtime `require` in `composer.json`;
- no `Astro` or `Moon` reference under `src/Hijri/`;
- no `DateTimeInterface` unions, framework imports, or clock reads;
- `LibraryVersion::CURRENT` is still `0.3.0-dev`.

- [ ] **Step 12: Commit**

```bash
git add docs ARCHITECTURE.md AGENTS.md CONTEXT.md CHANGELOG.md examples/bench.php tests/Wiring/CalculatorWiringTest.php
git commit -m "docs: document Hijri calculation metadata"
```

- [ ] **Step 13: Push and open the PR**

```bash
git push -u origin feat/provenance-a3-hijri
gh pr create --base main --head feat/provenance-a3-hijri \
  --title "feat: add Hijri calculation provenance (A3)" \
  --body-file /tmp/opencode/pr-a3-body.md
```

The PR body records: the A3 scope, the compact-path compatibility grid (4 calendars × 2 frames × 3 offset states), the exact calendar provenance table, the fallback-date decision, the gate evidence (tests/coverage/arch/mutation/bench/docs build), and the fact that no release bump is included. Watch `gh pr checks <n>` until green; do not merge without the user's instruction.

---

## Final acceptance checklist

- [ ] `composer validate --strict`, `composer ci`, `composer arch`, and `composer changelog:check` are green.
- [ ] `composer test` reports line coverage ≥ 95% (never lowered).
- [ ] `composer mutation` reports MSI ≥ 85.
- [ ] `composer bench` stays within 50 ms single-day / 30,000 ms year; baseline, A1, A2, and A3 medians are recorded.
- [ ] The 828 baseline tests plus the new provenance/metadata tests all pass; the assertion count only grows.
- [ ] `convert()` results are byte-for-byte equal to `convertWithMetadata()->result` across every calendar, both frames, and offset states `null`/`0`/`2`.
- [ ] Metadata identifies the calendar key/family/coverage, MABIMS country authority, span, digest, offset source, frame, and maghrib-only fallback.
- [ ] `convert()` never calls `calendarProvenance()`; a throwing `calendarProvenance()` still converts through the compact path.
- [ ] A custom calendar without `DescribesCalendar` gets `custom.calendar` + `unidentified_calculation_source` and no source entry.
- [ ] Generated, arithmetic, and official coverage warnings are driven by `CalendarCoverage`, not by class names.
- [ ] Offset `null` and `0` are distinguishable in `offsets`; `applied` is derived from `days !== null`.
- [ ] The fallback warning is visible when the resolved date is unchanged.
- [ ] Every new public class-like appears in `docs/user/api-reference.md`; the reverse gate finds no stale entry.
- [ ] `docs/README.md` links the A3 plan; `docs/provenance.md` and the published guide describe A1+A2+A3, not P0.2.
- [ ] `docs_site` builds with the updated page; no version snapshot changed.
- [ ] No P0.2 applicability/uncertainty types, no official dataset, and no release version bump were introduced.
- [ ] `HijriCalendar` and `OffsetStore` are unchanged; no `Astro`/`Moon` imports under `src/Hijri/`.

## Rollback

Every task is one commit. If a gate fails late, revert the offending commit rather than weakening a gate:

```bash
git revert <commit>
```

Tasks 1–3 are additive vocabulary/value objects and can be reverted independently. Task 4 stacks on all three and must be reverted before any of them. Task 5 is documentation/release-note work except for the benchmark and wiring test; it can be reverted independently, but the generated changelog must be regenerated afterwards.

---

## After A3: the release boundary

This is the real planning decision after implementation, not another task in this plan.

- **If `0.3.0` is the provenance release:** A1+A2+A3 is a coherent additive minor. Bump `LibraryVersion::CURRENT` from `0.3.0-dev` to `0.3.0`, regenerate the changelog/docs snapshot, run the pre-release workflow, and tag.
- **If `0.3.0` is the broader “Trust foundation” release named in the design:** continue with **P0.2 — applicability, uncertainty, and status** before tagging. P0.2 owns the full validity matrix that A1–A3 deliberately record only as warnings/status.

Recommendation: finish A3, then treat P0.2 as the next workstream rather than blocking the provenance release on it. A3 completes the public metadata surface cleanly; P0.2 changes the interpretation of that surface and deserves its own review cycle. A1–A3 can ship as `0.3.0`, and P0.2 can become `0.4.0`.

## Backlog ordering after that

1. **#41 — mutation only by demand**: now relevant because the PR mutation gate runs ~24 minutes; reduces CI cost without weakening required checks.
2. **#16 — official Umm al-Qura table**: natural follow-on to A3's `OFFICIAL_TABLE` enum and the `authority`/`license` fields, but gated on a sourced, redistributable dataset.
3. **#12 — MABIMS-1992 pre-conjunction guard**: domain enhancement with its own goldens, explicitly post-1.0.
4. **#11 — Brunei CSV empty cells**: correctness bug, but blocked on a sourced KHEU Doha definition; not a quick fix.
5. **#9 — Tehran/Morocco arbitration**: watch item; no action until new evidence appears.
