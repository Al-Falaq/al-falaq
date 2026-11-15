# Provenance A1 — Shared Vocabulary and Prayer Metadata Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Land the first executable slice of the provenance/explainability programme: the frozen shared vocabulary in `SharedKernel`, the opt-in prayer/timetable metadata path, and its documentation, without changing any legacy result, method signature, or numeric output.

**Architecture:** A1 is three stacked layers delivered as individually revertable commits:

1. **Shared vocabulary** — four enums, four value objects, and one driven-port contract in `SharedKernel`. `CalculationMetadata::create()` is the only construction path; it sorts, de-duplicates, and derives status from warnings so no service can hand-build an inconsistent envelope.
2. **Component identity** — `IdentifiesCalculationSource` is implemented by the built-in solar engine and prayer strategies. Services read the wired object, never a second enum lookup; an unidentified custom component becomes `custom.<component>` plus one warning, never a guess.
3. **Prayer trace** — `PrayerTimesCalculator` extracts its existing pipeline into one private `run()` routine. `calculate()` reads `['schedule']`; `calculateWithMetadata()` reads the same trace and adds inputs, effective inputs, policy, fallback, sources, and warnings. `NightResolver` reports the selected night mode beside the two instants. `TimetableService::monthlyWithMetadata()` calls `calculateWithMetadata()` per day.

Legacy behaviour is frozen: exceptions, return values, and `PrayerSchedule::toArray()` keep their current contract. A2 (Qibla/Moon) and A3 (Hijri) are out of scope and must reuse this vocabulary unchanged.

**Tech Stack:** PHP 8.2+ (no runtime dependencies), PHPUnit 11, PHPStan max + strict-rules, php-cs-fixer `@PER`, Infection (MSI ≥ 85), Astro Starlight docs site, plain-PHP tooling under `tools/`.

**Design authority:** [`docs/design/2026-11-15-provenance-explainability-foundation.md`](../design/2026-11-15-provenance-explainability-foundation.md). If implementation contradicts the spec, stop and fix the spec first; do not silently diverge. This plan is linked from `docs/README.md` beside the spec when the plan is committed.

**Plan location note:** the `writing-plans` skill defaults to `docs/superpowers/plans/`, which this repository gitignores. The plan is committed with the work and follows the `docs/plans/` precedent.

**Branch:** `feat/provenance-a1-prayer`, split from `main` at `06610b0` (the design-spec commit).

**Baseline (verified on `main` at `06610b0`):** 607 tests / 50,536 assertions, 32 arch tests / 383 assertions, 98.6% line coverage, 0 PHPStan errors, 0 php-cs-fixer findings, MSI gate 85, benchmark tripwires 50 ms single-day / 30,000 ms year (measured 0.956 ms / 348 ms / 6.0 MB on 2026-11-15).

**Frozen artefacts:** released user-docs snapshots under `docs/user/vX.Y.Z/` are never edited. Only current `docs/user/*.md` files change.

---

## Scope

### In scope

- `SharedKernel` provenance enums, value objects, and `IdentifiesCalculationSource`.
- `CalculationParameters::toArray()` and `PrayerAdjustments::toArray()`.
- `NightResolver` mode trace (no numeric change).
- `PrayerTimesCalculator::calculateWithMetadata()` via one shared private pipeline.
- `PrayerCalculationMetadata`, `PrayerCalculationOutcome`, `PrayerMetadataCalculatorInterface`.
- `TimetableService::monthlyWithMetadata()`.
- Source identity on the built-in solar engine and prayer strategies.
- `CalculationMethod::SOURCE_VERSION`.
- API reference, contributor provenance page, published metadata guide, integration snippet, architecture/changelog/context updates.
- Tests for every new public type and every new branch.

### Out of scope (do not implement here)

- Qibla and Moon metadata (A2): `calculateWithMetadata()`, `atSunsetWithMetadata()`, `evaluateWithMetadata()`, ΔT warnings, criterion warnings.
- Hijri metadata (A3): `convertWithMetadata()`, `CalendarProvenance`, `DescribesCalendar`, `HijriDateFrame`.
- A magnetic-declination model; the Qibla metadata path remains true-north only in A2.
- Any change to `PrayerTimesCalculatorInterface`, `TimetableServiceInterface`, `PrayerSchedule::toArray()`, `SolarDayEngine`, or `PrayerSchedule`'s constructor.
- A global warning mode, `set_error_handler`, logging, or exception promotion inside the library.
- Package metadata reads, clock reads, network calls, or Composer runtime dependencies.

### Non-negotiable invariants

1. Every legacy test keeps passing unchanged; no numeric golden is re-pinned.
2. `calculate()` and `calculateWithMetadata()` share one numeric run; metadata never recomputes astronomy or parses exception strings.
3. `CalculationMetadata::create()` is the only envelope constructor. Status is derived from warnings; callers cannot supply it.
4. Warning codes are stable; warning messages are explanatory and are not pinned by tests.
5. Source ids are stable within schema major version 1; a custom component that does not implement `IdentifiesCalculationSource` gets `custom.<component>` plus `unidentified_calculation_source`.
6. Metadata `toArray()` shapes are key-ordered, contain all nullable keys with `null`, and contain all optional lists with `[]`.
7. `src/` stays `strict_types=1`, final/readonly where required, docblock-complete, and free of framework/Astro imports outside the documented wiring exception.
8. New public class-likes are added to `docs/user/api-reference.md` in the same commit that creates them; `tests/ArchApiReferenceTest.php` is exact in both directions.

---

## Pre-flight

**Files:** none.

- [ ] **Step 1: Create the branch**

```bash
git switch main
git pull --ff-only
git switch -c feat/provenance-a1-prayer
```

- [ ] **Step 2: Re-run the baseline**

```bash
composer validate --strict
composer ci
composer arch
```

Expected: everything green, 607 tests in the unit suite, 32 arch tests. If `main` has moved, record the new commit and test counts in the PR description; do not lower any gate.

- [ ] **Step 3: Read the frozen spec sections**

Read spec sections 5–10 (constraints, public API, status/warnings, source identity, serialization, internal trace), 12 (testing/evidence), and 14 A1. Keep the spec open beside the implementation; it is the contract.

---

## Task 1: Shared enums and `LibraryVersion`

**Files:**

- Create `src/SharedKernel/Enums/CalculationContext.php`
- Create `src/SharedKernel/Enums/CalculationStatus.php`
- Create `src/SharedKernel/Enums/WarningCode.php`
- Create `src/SharedKernel/Enums/SourceKind.php`
- Create `src/SharedKernel/ValueObjects/LibraryVersion.php`
- Create `tests/SharedKernel/Provenance/SharedVocabularyTest.php`
- Modify `docs/user/api-reference.md`

- [ ] **Step 1: Write the failing tests**

Create `tests/SharedKernel/Provenance/SharedVocabularyTest.php` with `declare(strict_types=1)` and namespace `AlFalaq\AlFalaq\Tests\SharedKernel\Provenance`.

Tests:

- `testCalculationContextValues` — `array_map(static fn (CalculationContext $c): string => $c->value, CalculationContext::cases())` is exactly `['prayer', 'qibla', 'moon', 'hijri']`, in that order.
- `testCalculationStatusValues` — exactly `['complete', 'extrapolated', 'fallback_applied', 'indeterminate', 'decision_required']`.
- `testWarningCodeValues` — all eight values from spec §6.2, in declaration order:
  `delta_t_extrapolated`, `polar_fallback_applied`, `sunset_frame_maghrib_fallback`, `moonset_indeterminate`, `crescent_criterion_is_not_observation`, `generated_calendar_table`, `tabular_calendar_estimate`, `unidentified_calculation_source`.
- `testSourceKindValues` — exactly `['algorithm', 'generated_data', 'official_data', 'caller_input']`.
- `testLibraryVersionCurrentIsTheCheckedInRuntimeVersion` — `LibraryVersion::CURRENT === '0.3.0-dev'` and `LibraryVersion::current()->value === LibraryVersion::CURRENT`.
- `testLibraryVersionRoundTrips` — `LibraryVersion::fromString('0.3.0-dev')` casts to `'0.3.0-dev'`; `fromString('1.2.3')` and `fromString('1.2.3-rc.1')` are accepted.
- `testLibraryVersionRejectsMalformedValues` — `#[DataProvider]` over `''`, `'0.3'`, `'v0.3.0'`, `'0.3.0-'`, `'0.3.0+build'`, `'0.3.0 '`, `"0.3.0-dev\n"`; each throws `ValidationException`.

Use `#[DataProvider('malformedVersionProvider')]` and a static provider, matching the existing `CalculationParametersTest` style.

- [ ] **Step 2: Run the tests to verify they fail**

```bash
vendor/bin/phpunit --no-coverage --filter SharedVocabularyTest
```

Expected: class-not-found failures for every new type.

- [ ] **Step 3: Implement the enums**

Each enum is a backed string enum with the cases from spec §6.2 and a class docblock summary. No methods, no `rank()` helper (status ranking belongs to `CalculationMetadata`, which is the single owner).

- [ ] **Step 4: Implement `LibraryVersion`**

```php
final readonly class LibraryVersion
{
    public const CURRENT = '0.3.0-dev';

    private function __construct(public string $value) {}

    public static function current(): self;
    public static function fromString(string $value): self;
    public function __toString(): string;
}
```

Validation regex: `\A\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?\z`. Use `\z`, not `$`, so a trailing newline is rejected. Throw `ValidationException` with a message naming the received value. `__toString()` returns `$this->value`.

- [ ] **Step 5: Add API-reference rows**

In `docs/user/api-reference.md`, under `## SharedKernel — the foundation`, add one table row per new enum and one for `LibraryVersion`. The exact-match gate reads only backticked names in the first table column, so each row must start with `` | `SharedKernel\Enums\CalculationContext` |``, etc.

- [ ] **Step 6: Verify**

```bash
vendor/bin/phpunit --no-coverage --filter SharedVocabularyTest
composer lint
composer stan
```

- [ ] **Step 7: Commit**

```bash
git add src/SharedKernel/Enums src/SharedKernel/ValueObjects/LibraryVersion.php tests/SharedKernel/Provenance docs/user/api-reference.md
git commit -m "feat(shared-kernel): add provenance enums and library version"
```

---

## Task 2: `CalculationWarning` and `CalculationSource`

**Files:**

- Create `src/SharedKernel/ValueObjects/CalculationWarning.php`
- Create `src/SharedKernel/ValueObjects/CalculationSource.php`
- Create `tests/SharedKernel/Provenance/CalculationVocabularyTest.php`
- Modify `docs/user/api-reference.md`

- [ ] **Step 1: Write the failing tests**

`tests/SharedKernel/Provenance/CalculationVocabularyTest.php`:

**`CalculationWarning`**

- `testWarningSerializesCodeMessageAndDetails` — `new CalculationWarning(WarningCode::POLAR_FALLBACK_APPLIED, 'message', ['z' => 1, 'a' => true])`; `toArray()` is exactly:
  ```php
  [
      'code' => 'polar_fallback_applied',
      'message' => 'message',
      'details' => ['a' => true, 'z' => 1],
  ]
  ```
  The detail keys are sorted by `toArray()`, not by the constructor.
- `testWarningAcceptsEveryScalarDetail` — int, float, string, bool, null values all round-trip.
- `testWarningRejectsBlankMessage` — `''`, `'   '`, `"\t\n"`.
- `testWarningRejectsEmptyDetailKey` — `['' => 1]`.
- `testWarningRejectsNestedDetailValue` — `['nested' => ['x' => 1]]` and `['object' => new \stdClass()]`.
- `testWarningRejectsNonFiniteDetail` — `NAN`, `INF`, `-INF`.

**`CalculationSource`**

- `testAlgorithmSourceHasTheFiveKeyShape`:
  ```php
  CalculationSource::algorithm('solar.mean-meeus', 'meeus-2ed')->toArray() === [
      'id' => 'solar.mean-meeus',
      'kind' => 'algorithm',
      'version' => 'meeus-2ed',
      'digest' => null,
      'range' => null,
  ]
  ```
- `testAlgorithmSourceAcceptsARange` — `algorithm('moon.delta-t.espenak-2005-2050', null, '2005..2050')` keeps `version = null`, `range = '2005..2050'`.
- `testSourceRejectsInvalidId` — provider over `''`, `'Upper'`, `'.leading'`, `'trailing '`, `'with space'`, `'slash/id'`, `"tab\tid"`.
- `testSourceAcceptsDotsUnderscoresAndHyphens` — `'prayer.method.muslim_world_league'` and `'custom.my-engine_v2'`.
- `testSourceRejectsEmptyVersionOrRange` — `algorithm('x', '')` and `algorithm('x', null, '')`.
- `testGeneratedDataRequiresAValidDigestAndRange` — missing/empty/uppercase/short digest and empty range each throw; a 64-char lowercase hex digest with `'1423-01..1500-12 AH'` is accepted and reports `kind = 'generated_data'`.
- `testOfficialDataReportsItsKind` — same validation, `kind = 'official_data'`.
- `testCallerInputCarriesOnlyAnId` — `CalculationSource::callerInput('caller.adjustments')->toArray()` has `kind = 'caller_input'`, `version = digest = range = null`.

For deliberate type violations that PHPStan can prove impossible, follow the repository pattern: a preceding `/** @phpstan-ignore-next-line deliberate contract violation */`.

- [ ] **Step 2: Run the tests to verify they fail**

```bash
vendor/bin/phpunit --no-coverage --filter CalculationVocabularyTest
```

- [ ] **Step 3: Implement `CalculationWarning`**

```php
final readonly class CalculationWarning
{
    /**
     * @param array<string, int|float|string|bool|null> $details
     */
    public function __construct(
        public WarningCode $code,
        public string $message,
        public array $details = [],
    ) {}

    public function toArray(): array;
}
```

Validation:

- message non-empty after `trim()`;
- every detail key is a non-empty string after `trim()`;
- every detail value is `int|float|string|bool|null`;
- float values must be finite.

Pass each value through a `private static function assertDetailValue(mixed $value): void` helper so the runtime checks are meaningful to PHPStan while the public type stays exact.

`toArray()` copies `$details`, `ksort($details, SORT_STRING)`, and returns the three keys in fixed order.

- [ ] **Step 4: Implement `CalculationSource`**

Private constructor over the five properties; three factories as in spec §6.3.

Validation:

- id matches `\A[a-z0-9][a-z0-9._-]*\z`;
- `version` and `range`, when present, are non-empty after `trim()`;
- `digest` matches `\A[a-f0-9]{64}\z`;
- `generatedData()` and `officialData()` require both digest and range;
- `callerInput()` sets version, digest, and range to `null`.

`toArray()` always returns all five keys in the documented order.

- [ ] **Step 5: Add API-reference rows**

One row each for `SharedKernel\ValueObjects\CalculationWarning` and `SharedKernel\ValueObjects\CalculationSource`, naming the factories and `toArray()`.

- [ ] **Step 6: Verify**

```bash
vendor/bin/phpunit --no-coverage --filter CalculationVocabularyTest
composer lint
composer stan
```

- [ ] **Step 7: Commit**

```bash
git add src/SharedKernel/ValueObjects/CalculationWarning.php src/SharedKernel/ValueObjects/CalculationSource.php tests/SharedKernel/Provenance/CalculationVocabularyTest.php docs/user/api-reference.md
git commit -m "feat(shared-kernel): add calculation warning and source value objects"
```

---

## Task 3: `CalculationMetadata` — ordering, de-duplication, and status derivation

**Files:**

- Create `src/SharedKernel/ValueObjects/CalculationMetadata.php`
- Create `tests/SharedKernel/Provenance/CalculationMetadataTest.php`
- Modify `docs/user/api-reference.md`

- [ ] **Step 1: Write the failing tests**

`tests/SharedKernel/Provenance/CalculationMetadataTest.php`:

- `testCreateDefaultsToCompleteAndCurrentLibraryVersion` — `CalculationMetadata::create(CalculationContext::PRAYER)` yields `status = complete`, `libraryVersion->value === LibraryVersion::CURRENT`, empty warnings/sources.
- `testCreateAcceptsAnExplicitLibraryVersion` — `fromString('1.2.3')` is preserved (the test/reconstruction seam).
- `testSchemaVersionIsOneZero` — `CalculationMetadata::SCHEMA_VERSION === '1.0'`.
- `testStatusDerivationForSingleWarnings` — `#[DataProvider]` over every `WarningCode` case:
  - `delta_t_extrapolated` → `extrapolated`
  - `polar_fallback_applied` → `fallback_applied`
  - `sunset_frame_maghrib_fallback` → `fallback_applied`
  - `moonset_indeterminate` → `indeterminate`
  - `crescent_criterion_is_not_observation` → `decision_required`
  - `generated_calendar_table` → `complete`
  - `tabular_calendar_estimate` → `complete`
  - `unidentified_calculation_source` → `complete`
- `testStatusUsesTheHighestRankAndKeepsEveryWarning` — a set containing `delta_t_extrapolated`, `polar_fallback_applied`, and `moonset_indeterminate` yields `indeterminate` and retains all three warnings. Add a second assertion with `crescent_criterion_is_not_observation` that wins at `decision_required`.
- `testWarningsSortByCodeThenMessageThenDetails` — construct in reverse order; assert the output order. Two warnings with the same code sort by message; two with the same code and message sort by the serialized details (detail keys are already sorted).
- `testSourcesSortByIdKindAndVersionWithNullLast` — use two sources with the same id but different kinds, and two with the same id+kind where one has `version = null`; assert the null-version entry sorts last.
- `testIdenticalWarningsCollapseToOneEntry` — two warnings with the same code, message, and details; count one.
- `testWarningsWithDifferentComponentsDoNotCollapse` — same code/message, different `component` detail; count two.
- `testIdenticalSourcesCollapseToOneEntry` — same five fields; count one.
- `testSourcesWithDifferentDigestsDoNotCollapse` — same id/kind/version/range, different digests; count two, in a deterministic order.
- `testRejectsNonListWarnings` — associative array keyed by string.
- `testRejectsNonListSources` — same.
- `testRejectsWrongElementTypes` — a `CalculationWarning` in the sources list and vice versa.
- `testToArrayHasTheFixedKeyOrder` — `array_keys($metadata->toArray()) === ['schema_version', 'context', 'status', 'library_version', 'warnings', 'sources']`; `json_encode(..., JSON_THROW_ON_ERROR)` succeeds.

Use the `/** @phpstan-ignore-next-line deliberate contract violation */` pattern for wrong-type inputs.

- [ ] **Step 2: Run the tests to verify they fail**

```bash
vendor/bin/phpunit --no-coverage --filter CalculationMetadataTest
```

- [ ] **Step 3: Implement `CalculationMetadata`**

```php
final readonly class CalculationMetadata
{
    public const SCHEMA_VERSION = '1.0';

    /**
     * @param list<CalculationWarning> $warnings
     * @param list<CalculationSource> $sources
     */
    private function __construct(
        public CalculationContext $context,
        public CalculationStatus $status,
        public LibraryVersion $libraryVersion,
        public array $warnings,
        public array $sources,
    ) {}

    /**
     * @param list<CalculationWarning> $warnings
     * @param list<CalculationSource> $sources
     */
    public static function create(
        CalculationContext $context,
        array $warnings = [],
        array $sources = [],
        ?LibraryVersion $libraryVersion = null,
    ): self;

    public function toArray(): array;
}
```

`create()` pipeline, in order:

1. Verify both arrays are lists of the expected types (throw `ValidationException` otherwise). Use private helpers accepting `mixed` so the checks survive PHPStan max.
2. Sort warnings with `usort` and the key `[code value, message, serialize(toArray()['details'])]`.
3. Sort sources with the key `[id, kind value, version-null-last, version, digest-null-last, digest, range-null-last, range]`. The extra digest/range tiebreakers are required for a total deterministic order when custom sources reuse an id; they do not change the documented primary order.
4. Collapse adjacent identical entries using `serialize($item->toArray())` as the identity key. Do not collapse warnings with different `component` details (they serialize differently).
5. Derive status from the *unreduced* warning set: map each code through the table in spec §7.1, keep the highest rank (`complete` 0, `extrapolated` 1, `fallback_applied` 2, `indeterminate` 3, `decision_required` 4).
6. Default `$libraryVersion` to `LibraryVersion::current()`.
7. Construct with the sorted, de-duplicated lists.

`toArray()` returns exactly:

```php
[
    'schema_version' => self::SCHEMA_VERSION,
    'context' => $this->context->value,
    'status' => $this->status->value,
    'library_version' => (string) $this->libraryVersion,
    'warnings' => array_map(static fn (CalculationWarning $w): array => $w->toArray(), $this->warnings),
    'sources' => array_map(static fn (CalculationSource $s): array => $s->toArray(), $this->sources),
]
```

The constructor is private so status cannot be supplied independently of the warnings.

- [ ] **Step 4: Add the API-reference row**

One row for `SharedKernel\ValueObjects\CalculationMetadata`, naming `SCHEMA_VERSION`, `create()`, and `toArray()`.

- [ ] **Step 5: Verify**

```bash
vendor/bin/phpunit --no-coverage --filter CalculationMetadataTest
composer lint
composer stan
```

- [ ] **Step 6: Commit**

```bash
git add src/SharedKernel/ValueObjects/CalculationMetadata.php tests/SharedKernel/Provenance/CalculationMetadataTest.php docs/user/api-reference.md
git commit -m "feat(shared-kernel): add calculation metadata envelope"
```

---

## Task 4: `IdentifiesCalculationSource`, built-in identity, and `CalculationMethod::SOURCE_VERSION`

**Files:**

- Create `src/SharedKernel/Contracts/IdentifiesCalculationSource.php`
- Create `tests/SharedKernel/Provenance/BuiltInSourceIdentityTest.php`
- Modify `tests/Prayer/CalculationMethodTest.php`
- Modify `src/Prayer/Enums/CalculationMethod.php`
- Modify `src/Astro/Services/MeanSolarCalculator.php`
- Modify `src/Prayer/Strategies/MiddleOfNightCorrection.php`
- Modify `src/Prayer/Strategies/SeventhOfNightCorrection.php`
- Modify `src/Prayer/Strategies/TwilightAngleCorrection.php`
- Modify `src/Prayer/Strategies/SeasonAdjustedTwilight.php`
- Modify `src/Prayer/Strategies/NearestLatitudeHandler.php`
- Modify `src/Prayer/Strategies/NearestDayHandler.php`
- Modify `src/Prayer/Strategies/MakkahHandler.php`
- Modify `src/Prayer/Strategies/ThrowHandler.php`
- Modify `src/Prayer/Strategies/AngleIsha.php`
- Modify `src/Prayer/Strategies/IntervalIsha.php`
- Modify `src/Prayer/Strategies/AngleMaghrib.php`
- Modify `src/Prayer/Strategies/MinutesAfterSunsetMaghrib.php`
- Modify `docs/user/api-reference.md`

- [ ] **Step 1: Write the failing tests**

`tests/SharedKernel/Provenance/BuiltInSourceIdentityTest.php`:

- `testBuiltInComponentsExposeTheRegistryId` — `#[DataProvider]` over `[class-string, expected id, expected version, expected range]`:

  | Class | Id | Version | Range |
  |---|---|---|---|
  | `MeanSolarCalculator` | `solar.mean-meeus` | `meeus-2ed` | `null` |
  | `MiddleOfNightCorrection` | `prayer.high-latitude.middle_of_night` | `null` | `null` |
  | `SeventhOfNightCorrection` | `prayer.high-latitude.seventh_of_night` | `null` | `null` |
  | `TwilightAngleCorrection` | `prayer.high-latitude.twilight_angle` | `null` | `null` |
  | `SeasonAdjustedTwilight` | `prayer.high-latitude.seasonal_twilight` | `null` | `null` |
  | `NearestLatitudeHandler` | `prayer.polar.nearest_latitude` | `null` | `null` |
  | `NearestDayHandler` | `prayer.polar.nearest_day` | `null` | `null` |
  | `MakkahHandler` | `prayer.polar.makkah` | `null` | `null` |
  | `ThrowHandler` | `prayer.polar.none` | `null` | `null` |
  | `AngleIsha` | `prayer.isha.angle` | `null` | `null` |
  | `IntervalIsha` | `prayer.isha.interval` | `null` | `null` |
  | `AngleMaghrib` | `prayer.maghrib.angle` | `null` | `null` |
  | `MinutesAfterSunsetMaghrib` | `prayer.maghrib.interval` | `null` | `null` |

  Instantiate through the public constructors (AngleIsha needs a float, IntervalIsha needs minutes, AngleMaghrib needs a float, `MinutesAfterSunsetMaghrib` needs minutes; the correction/handler/engine classes need no arguments).
- `testEachComponentDeclaresItsSourceIdConstant` — the class declares `SOURCE_ID` and `calculationSource()->id === constant`.
- `testEveryImplementationSatisfiesTheContract` — `assertInstanceOf(IdentifiesCalculationSource::class, $instance)`.

In `tests/Prayer/CalculationMethodTest.php`, add:

- `testSourceVersionFrozen` — `CalculationMethod::SOURCE_VERSION === 'method-defaults-v1'`.
- `testPresetSourceIdUsesTheCanonicalLowercaseValue` — for `SINGAPORE`, the derived id is `'prayer.method.singapore'`; for `MOON_SIGHTING_COMMITTEE`, `'prayer.method.moon_sighting_committee'`. Derive it in the test exactly as the service does (`'prayer.method.' . $method->value`); do not add a second public mapping method.

- [ ] **Step 2: Run the tests to verify they fail**

```bash
vendor/bin/phpunit --no-coverage --filter BuiltInSourceIdentityTest
vendor/bin/phpunit --no-coverage --filter CalculationMethodTest
```

Expected: missing interface/methods/constant.

- [ ] **Step 3: Implement the contract**

```php
namespace AlFalaq\AlFalaq\SharedKernel\Contracts;

interface IdentifiesCalculationSource
{
    public function calculationSource(): CalculationSource;
}
```

Driven capability, short name, no `Interface` suffix. Full docblock with `@return`.

- [ ] **Step 4: Implement the built-ins**

Each class gets:

```php
public const SOURCE_ID = '<registry id>';

public function calculationSource(): CalculationSource
{
    return CalculationSource::algorithm(self::SOURCE_ID); // MeanSolarCalculator adds 'meeus-2ed'
}
```

`MeanSolarCalculator` also declares `public const SOURCE_VERSION = 'meeus-2ed'` and returns `CalculationSource::algorithm(self::SOURCE_ID, self::SOURCE_VERSION)`.

`SeasonAdjustedTwilight` has one id only; it serves both the high-latitude seasonal mode and the twilight model. Do not add a second id.

`ThrowHandler` implements the interface with `prayer.polar.none`, but the calculator never emits it because that policy throws instead of returning a fallback. Document that in the class docblock.

Add `CalculationMethod::SOURCE_VERSION = 'method-defaults-v1'` with a docblock stating it is independent of the package version and is bumped whenever any preset default changes.

- [ ] **Step 5: Add the API-reference row**

One row for `SharedKernel\Contracts\IdentifiesCalculationSource` with `calculationSource(): CalculationSource` and the custom-component policy. Extend the existing strategy rows to mention `SOURCE_ID` (not gate-enforced, but keeps the map true).

- [ ] **Step 6: Verify**

```bash
vendor/bin/phpunit --no-coverage --filter BuiltInSourceIdentityTest
vendor/bin/phpunit --no-coverage --filter CalculationMethodTest
composer lint
composer stan
composer arch
```

`composer arch` must stay green: the new contract is interfaces-only, has no `Interface` suffix, and does not enter `SERVICE_CONTRACTS`.

- [ ] **Step 7: Commit**

```bash
git add src/SharedKernel/Contracts src/Astro/Services/MeanSolarCalculator.php src/Prayer/Strategies src/Prayer/Enums/CalculationMethod.php tests/SharedKernel/Provenance/BuiltInSourceIdentityTest.php tests/Prayer/CalculationMethodTest.php docs/user/api-reference.md
git commit -m "feat(prayer): identify built-in calculation sources"
```

---

## Task 5: Additive serializers on `CalculationParameters` and `PrayerAdjustments`

**Files:**

- Modify `src/Prayer/ValueObjects/PrayerAdjustments.php`
- Modify `src/Prayer/ValueObjects/CalculationParameters.php`
- Create `tests/Prayer/PrayerAdjustmentsTest.php`
- Modify `tests/Prayer/CalculationParametersTest.php`
- Modify `docs/user/api-reference.md`

- [ ] **Step 1: Write the failing tests**

`tests/Prayer/PrayerAdjustmentsTest.php`:

- `testEmptySerializesEveryPrayerKeyAsZero` — `array_keys(PrayerAdjustments::empty()->toArray())` equals the `Prayer::cases()` value list in enum order (`imsak`, `fajr`, `sunrise`, `syuruk`, `ishraq`, `dhuha`, `dhuhr`, `asr`, `maghrib`, `isha`); every value is `0`.
- `testSerializesSetOffsetsAndFillsMissingPrayers` — `empty()->with(Prayer::IMSAK, -5)->with(Prayer::ISHA, 7)` yields all ten keys with `imsak = -5`, `isha = 7`, and the other eight `0`.
- `testKeyOrderIsEnumOrderNotInsertionOrder` — insert `ISHA` first, then `IMSAK`; assert the output key order is still enum order.

Append to `tests/Prayer/CalculationParametersTest.php`:

- `testToArraySnapshotsAngleRulesInDocumentedOrder` — `CalculationMethod::SINGAPORE->defaultParameters()` with `AngleIsha(18.0)` and `MinutesAfterSunsetMaghrib(0)`; assert the exact array:
  - `method = 'singapore'`, `madhab = 'shafi'`, rules and offsets;
  - `isha_rule = ['type' => 'angle', 'angle_degrees' => 18.0, 'minutes' => null]`;
  - `maghrib_rule = ['type' => 'interval', 'angle_degrees' => null, 'minutes' => 0]`;
  - `method_adjustments` has `dhuhr = 1` and nine zeros;
  - `adjustments` is all zeros;
  - key order exactly `method`, `madhab`, `high_latitude_rule`, `polar_circle_rule`, `fajr_angle_degrees`, `isha_rule`, `maghrib_rule`, `dhuhr_offset_minutes`, `imsak_offset_minutes`, `ishraq_offset_minutes`, `dhuha_offset_minutes`, `elevation_m`, `is_ramadan`, `method_adjustments`, `adjustments`.
- `testToArraySnapshotsIntervalIshaAndAngleMaghrib` — `IntervalIsha(90)` + `AngleMaghrib(4.0)` yields `isha_rule = ['type' => 'interval', 'angle_degrees' => null, 'minutes' => 90]` and `maghrib_rule = ['type' => 'angle', 'angle_degrees' => 4.0, 'minutes' => null]`.
- `testToArrayReportsRamadanMode` — `CalculationMethod::UMM_AL_QURA->defaultParameters()->withRamadan(true)` yields `is_ramadan = true` and method `umm_al_qura`.

- [ ] **Step 2: Run the tests to verify they fail**

```bash
vendor/bin/phpunit --no-coverage --filter PrayerAdjustmentsTest
vendor/bin/phpunit --no-coverage --filter CalculationParametersTest
```

- [ ] **Step 3: Implement `PrayerAdjustments::toArray()`**

```php
/**
 * @return array<string, int> All ten prayer keys in enum order; unset reads 0.
 */
public function toArray(): array
{
    $out = [];

    foreach (Prayer::cases() as $prayer) {
        $out[$prayer->value] = $this->for($prayer);
    }

    return $out;
}
```

- [ ] **Step 4: Implement `CalculationParameters::toArray()`**

Use the exact field order and rule snapshot from spec §6.6. The unused pair in each rule snapshot is `null`. Delegate to `methodAdjustments->toArray()` and `adjustments->toArray()`.

- [ ] **Step 5: Update the API-reference rows**

Extend the `CalculationParameters` and `PrayerAdjustments` rows with `toArray()` and its shape promise.

- [ ] **Step 6: Verify**

```bash
vendor/bin/phpunit --no-coverage --filter PrayerAdjustmentsTest
vendor/bin/phpunit --no-coverage --filter CalculationParametersTest
composer lint
composer stan
```

- [ ] **Step 7: Commit**

```bash
git add src/Prayer/ValueObjects/PrayerAdjustments.php src/Prayer/ValueObjects/CalculationParameters.php tests/Prayer/PrayerAdjustmentsTest.php tests/Prayer/CalculationParametersTest.php docs/user/api-reference.md
git commit -m "feat(prayer): add policy and adjustment serializers"
```

---

## Task 6: `NightResolver` mode trace

**Files:**

- Modify `src/Prayer/Services/NightResolver.php`
- Create `tests/Prayer/NightResolverModeTest.php`

- [ ] **Step 1: Write the failing tests**

`tests/Prayer/NightResolverModeTest.php` uses the existing fixture style from `tests/Prayer/NightResolverTest.php` (real `MeanSolarCalculator`, `SolarTime` from the engine for a date/place, `NightResolver` wired with the matching correction/model).

`#[DataProvider]` over:

| Parameters | Expected mode |
|---|---|
| `CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters()` (MIDDLE_OF_NIGHT, portions) | `portions` |
| Custom `CalculationParameters::create(... HighLatitudeRule::NONE ...)` | `none` |
| `CalculationMethod::MOON_SIGHTING_COMMITTEE->defaultParameters()` (MSC method) | `seasonal` |
| `MUSLIM_WORLD_LEAGUE->defaultParameters()->withHighLatitudeRule(HighLatitudeRule::SEASONAL_TWILIGHT)` | `seasonal` |

Also `testModeIsReturnedBesideBothInstants` — the resolved array has exactly `fajr`, `isha`, and `mode` keys, with `fajr`/`isha` still `\DateTimeImmutable` in UTC.

Do not assert numeric values again here; the existing `NightResolverTest` and goldens own those.

- [ ] **Step 2: Run the test to verify it fails**

```bash
vendor/bin/phpunit --no-coverage --filter NightResolverModeTest
```

Expected: missing `mode` key.

- [ ] **Step 3: Implement the mode**

Compute the mode once, after `$useSeasonalSafe` is known and before returning:

```php
$mode = $useSeasonalSafe
    ? 'seasonal'
    : ($parameters->highLatitudeRule === HighLatitudeRule::NONE ? 'none' : 'portions');

return ['fajr' => $fajrTemp, 'isha' => $ishaTemp, 'mode' => $mode];
```

Update the `resolve()` docblock:

```php
@return array{fajr: \DateTimeImmutable, isha: \DateTimeImmutable, mode: 'seasonal'|'none'|'portions'}
```

No numeric expression changes. `PrayerTimesCalculator` will read `$temps['mode']` in Task 8.

- [ ] **Step 4: Verify the existing suite**

```bash
vendor/bin/phpunit --no-coverage --filter NightResolver
composer lint
composer stan
```

All existing `NightResolverTest` assertions must pass unchanged; only key-based reads are used, so the added key is safe.

- [ ] **Step 5: Commit**

```bash
git add src/Prayer/Services/NightResolver.php tests/Prayer/NightResolverModeTest.php
git commit -m "feat(prayer): report the selected night mode beside fajr and isha"
```

---

## Task 7: Prayer metadata value objects and contract

**Files:**

- Create `src/Prayer/ValueObjects/PrayerCalculationMetadata.php`
- Create `src/Prayer/ValueObjects/PrayerCalculationOutcome.php`
- Create `src/Prayer/Contracts/PrayerMetadataCalculatorInterface.php`
- Create `tests/Prayer/PrayerMetadataTest.php` (value-object section)
- Modify `tests/ArchShapeTest.php`
- Modify `docs/user/api-reference.md`

- [ ] **Step 1: Write the failing value-object tests**

`tests/Prayer/PrayerMetadataTest.php`:

- `testMetadataToArrayWrapsCommonAndInputs` — build `CalculationMetadata::create(CalculationContext::PRAYER)`, a "no fallback" `PrayerCalculationMetadata`, and assert:
  - key order `schema_version`, `context`, `status`, `library_version`, `warnings`, `sources`, `input`, `effective_input`, `policy`, `fallback`, `rounding`;
  - `input = ['coordinates' => ['latitude' => ..., 'longitude' => ...], 'date' => 'YYYY-MM-DD']`;
  - `effective_input` mirrors the requested pair;
  - `policy === $parameters->toArray()`;
  - `fallback === ['applied' => false, 'kind' => null, 'rule' => null]`;
  - `rounding === 'half_up_to_minute_utc'`.
- `testMetadataToArrayReportsAPolarFallback` — `fallbackRule = PolarCircleRule::NEAREST_LATITUDE` yields `applied = true`, `kind = 'polar'`, `rule = 'nearest_latitude'`.
- `testOutcomeWrapsResultAndMetadata` — `PrayerCalculationOutcome->toArray()` has exactly `result` and `metadata`; `result` equals `PrayerSchedule::toArray()`.
- `testMetadataInterfaceExtendsTheCalculatorContract` — `is_subclass_of(PrayerMetadataCalculatorInterface::class, PrayerTimesCalculatorInterface::class)` and the interface declares `calculateWithMetadata` with a `PrayerCalculationOutcome` return type.

- [ ] **Step 2: Run the tests to verify they fail**

```bash
vendor/bin/phpunit --no-coverage --filter PrayerMetadataTest
```

- [ ] **Step 3: Implement the two value objects**

```php
final readonly class PrayerCalculationMetadata
{
    public const ROUNDING = 'half_up_to_minute_utc';

    public function __construct(
        public CalculationMetadata $common,
        public Coordinates $requestedCoordinates,
        public CivilDate $requestedDate,
        public Coordinates $effectiveCoordinates,
        public CivilDate $effectiveDate,
        public CalculationParameters $parameters,
        public ?PolarCircleRule $fallbackRule,
    ) {}

    public function toArray(): array;
}
```

`toArray()` spreads `$this->common->toArray()` first (PHP 8.2 string-key spread preserves order), then the five context blocks in the order above. Coordinates use `Coordinates::toArray()`; dates use `CivilDate::isoformat()`.

```php
final readonly class PrayerCalculationOutcome
{
    public function __construct(
        public PrayerSchedule $result,
        public PrayerCalculationMetadata $metadata,
    ) {}

    public function toArray(): array;
}
```

`toArray()` returns `['result' => $this->result->toArray(), 'metadata' => $this->metadata->toArray()]`.

Both classes are `final readonly` with docblock summaries, `@param` on every constructor parameter, and `@return` on `toArray()`.

- [ ] **Step 4: Implement the contract**

```php
interface PrayerMetadataCalculatorInterface extends PrayerTimesCalculatorInterface
{
    public function calculateWithMetadata(
        Coordinates $coordinates,
        CivilDate $date,
        CalculationParameters $parameters,
    ): PrayerCalculationOutcome;
}
```

The docblock notes that `PrayerTimesCalculator` widens the parameter to `Coordinates|array` by contravariance, and that `PrayerTimesCalculatorInterface` is unchanged.

- [ ] **Step 5: Extend the arch allow-list**

In `tests/ArchShapeTest.php`, add `'AlFalaq\\AlFalaq\\Prayer\\Contracts\\PrayerMetadataCalculatorInterface'` to `SERVICE_CONTRACTS`. The list is exact in both directions; do not add `IdentifiesCalculationSource` (it has no suffix and is a driven port).

- [ ] **Step 6: Add API-reference rows**

Rows for `Prayer\ValueObjects\PrayerCalculationMetadata`, `Prayer\ValueObjects\PrayerCalculationOutcome`, and `Prayer\Contracts\PrayerMetadataCalculatorInterface`, including `ROUNDING`, the constructor fields, and `calculateWithMetadata()`.

- [ ] **Step 7: Verify**

```bash
vendor/bin/phpunit --no-coverage --filter PrayerMetadataTest
composer lint
composer stan
composer arch
```

- [ ] **Step 8: Commit**

```bash
git add src/Prayer/ValueObjects/PrayerCalculationMetadata.php src/Prayer/ValueObjects/PrayerCalculationOutcome.php src/Prayer/Contracts/PrayerMetadataCalculatorInterface.php tests/Prayer/PrayerMetadataTest.php tests/ArchShapeTest.php docs/user/api-reference.md
git commit -m "feat(prayer): add calculation metadata value objects and contract"
```

---

## Task 8: `PrayerTimesCalculator` trace and `calculateWithMetadata()`

**Files:**

- Modify `src/Prayer/Services/PrayerTimesCalculator.php`
- Modify `tests/Prayer/PrayerMetadataTest.php`
- Modify `docs/user/api-reference.md`

- [ ] **Step 1: Write the failing tests**

Append to `tests/Prayer/PrayerMetadataTest.php`:

**Single-path and legacy equality**

- `testLegacyResultEqualsMetadataResult` — over a small grid (`SINGAPORE`, `UMM_AL_QURA`, `MOON_SIGHTING_COMMITTEE`; Kuala Lumpur and Tromsø; two dates each), `calculate(...)->toArray() === calculateWithMetadata(...)->result->toArray()`.
- `testCalculateWithMetadataAcceptsACoordinatePair` — `[3.1390, 101.6869]` yields the same outcome as the `Coordinates` form.
- `testLegacyExceptionsAreUnchanged` — `PolarCircleRule::NONE` on a polar day still throws `UndefinedScheduleException` from both methods; a malformed array still throws `InvalidCoordinatesException`.

**Normal shape**

- `testNormalOutcomeIsCompleteWithTheRegistrySourceSet` — `SINGAPORE` on `2025-03-01`, Kuala Lumpur. Assert:
  - `context = 'prayer'`, `status = 'complete'`, `warnings = []`, `schema_version = '1.0'`, `library_version = LibraryVersion::CURRENT`;
  - `sources` is exactly the spec §9.3 set in sorted order:
    `prayer.high-latitude.middle_of_night`, `prayer.isha.angle`, `prayer.maghrib.interval`, `prayer.method.singapore` (version `method-defaults-v1`), `solar.mean-meeus` (version `meeus-2ed`);
  - `policy` equals `CalculationParameters::toArray()`;
  - `fallback` is `['applied' => false, 'kind' => null, 'rule' => null]`;
  - `rounding = 'half_up_to_minute_utc'`;
  - `json_encode($outcome->toArray(), JSON_THROW_ON_ERROR)` succeeds and the encoded string contains no `Astro\\` or `AlFalaq\\AlFalaq\\Astro` token.
- `testSeasonalModeListsTheTwilightModelOnly` — `MOON_SIGHTING_COMMITTEE` (MSC method, MIDDLE_OF_NIGHT rule) selects the seasonal mode: sources contain `prayer.high-latitude.seasonal_twilight`, and do **not** contain `prayer.high-latitude.middle_of_night`.
- `testNoneModeListsNeitherNightSource` — a `CalculationMethod::NONE` parameter set with `HighLatitudeRule::NONE`: sources contain `prayer.method.none`, `prayer.isha.angle`, `prayer.maghrib.interval`, `solar.mean-meeus`, and no high-latitude/twilight source.
- `testNonZeroCallerAdjustmentsAddTheCallerSource` — `withAdjustments(PrayerAdjustments::empty()->with(Prayer::FAJR, 3))` adds `caller.adjustments` with `kind = 'caller_input'`.
- `testEmptyCallerAdjustmentsOmitTheCallerSource` — default parameters do not list `caller.adjustments`.
- `testMethodAdjustmentsDoNotAddACallerSource` — `SINGAPORE` has a method `dhuhr +1` adjustment but no caller adjustment; `caller.adjustments` is absent.

**Polar fallback**

- `testPolarFallbackIsVisibleInStatusWarningAndInputs` — Tromsø `2025-11-28`, MWL + `PolarCircleRule::NEAREST_LATITUDE`:
  - `status = 'fallback_applied'`;
  - exactly one warning with `code = 'polar_fallback_applied'`; details include `rule = 'nearest_latitude'`, requested lat/lon/date, effective lat/lon/date, requested date `2025-11-28`, effective date `2025-11-28`, effective latitude lower than requested;
  - `input` keeps the requested pair; `effective_input` differs in latitude;
  - `fallback = ['applied' => true, 'kind' => 'polar', 'rule' => 'nearest_latitude']`;
  - sources include `prayer.polar.nearest_latitude`;
  - warning `message` is non-empty (do not pin its text).
- `testPolarFallbackKeepsLegacyEquality` — same inputs: `calculate()` and `calculateWithMetadata()->result` are equal.

**Custom components**

- `testUnidentifiedSolarEngineIsReportedAsCustomWithAWarning` — wire an anonymous `SolarDayEngine` (the zero-offset engine used by `tests/Wiring/CalculatorWiringTest.php`) that does **not** implement `IdentifiesCalculationSource`:
  - sources contain `custom.solar`;
  - exactly one warning `unidentified_calculation_source` with `details = ['component' => 'solar', 'source_id' => 'custom.solar']`;
  - status stays `complete`;
  - `calculate()` still runs and returns a schedule.
- `testIdentifiedCustomSolarEngineIsReportedWithoutAWarning` — an anonymous engine implementing `SolarDayEngine` and `IdentifiesCalculationSource`, returning `CalculationSource::algorithm('custom.test-engine', 'v1')`: source id `custom.test-engine`, no warnings.
- `testUnidentifiedCustomIshaRuleIsReported` — replace the Isha rule with an anonymous `IshaRule` (interval 90, no identity): source `custom.isha` and the matching warning; the schedule is still produced.

**Shape pinning helper**

Add a private static `normalizeMessages(array $metadata): array` that replaces every warning `message` with `'<message>'`; use it in full-array comparisons and assert non-emptiness separately. Do not pin `library_version` to a literal — compare to `LibraryVersion::CURRENT`.

- [ ] **Step 2: Run the tests to verify they fail**

```bash
vendor/bin/phpunit --no-coverage --filter PrayerMetadataTest
```

Expected: missing `calculateWithMetadata()`, missing class implementation.

- [ ] **Step 3: Refactor the pipeline into one private routine**

Change the class declaration to implement `PrayerMetadataCalculatorInterface` (which extends `PrayerTimesCalculatorInterface`).

`calculate()` becomes:

```php
public function calculate(Coordinates|array $coordinates, CivilDate $date, CalculationParameters $parameters): PrayerSchedule
{
    return $this->run(self::normalizeCoordinates($coordinates), $date, $parameters)['schedule'];
}
```

The entire current body of `calculate()` moves into:

```php
/**
 * @return array{
 *     schedule: PrayerSchedule,
 *     effectiveCoordinates: Coordinates,
 *     effectiveDate: CivilDate,
 *     polarFallbackRule: ?PolarCircleRule,
 *     nightMode: 'seasonal'|'none'|'portions',
 * }
 */
private function run(Coordinates $coords, CivilDate $date, CalculationParameters $parameters): array
```

Inside `run()`:

- initialize `$polarFallbackRule = null;`
- set `$polarFallbackRule = $parameters->polarCircleRule;` only after the polar handler has run and the post-fallback `isSolarDefined()` check has passed (the current code path that continues to `solarTime()`);
- keep every existing exception and comment intact;
- read `$temps['mode']` into `nightMode`;
- return the five-key trace instead of the `PrayerSchedule`.

`maghribAt()` is unchanged (it keeps its own safe narrowing and never calls `run()`).

- [ ] **Step 4: Implement `calculateWithMetadata()`**

```php
public function calculateWithMetadata(Coordinates|array $coordinates, CivilDate $date, CalculationParameters $parameters): PrayerCalculationOutcome
{
    $requested = self::normalizeCoordinates($coordinates);
    $trace = $this->run($requested, $date, $parameters);
    $warnings = [];
    $sources = $this->sourcesFor($parameters, $trace['nightMode'], $trace['polarFallbackRule'], $warnings);

    if ($trace['polarFallbackRule'] !== null) {
        $warnings[] = self::polarWarning(
            $requested,
            $date,
            $trace['effectiveCoordinates'],
            $trace['effectiveDate'],
            $trace['polarFallbackRule'],
        );
    }

    return new PrayerCalculationOutcome(
        $trace['schedule'],
        new PrayerCalculationMetadata(
            CalculationMetadata::create(CalculationContext::PRAYER, $warnings, $sources),
            $requested,
            $date,
            $trace['effectiveCoordinates'],
            $trace['effectiveDate'],
            $parameters,
            $trace['polarFallbackRule'],
        ),
    );
}
```

- [ ] **Step 5: Implement source collection and warning helpers**

```php
/**
 * @param list<CalculationWarning> $warnings
 * @return list<CalculationSource>
 */
private function sourcesFor(
    CalculationParameters $parameters,
    string $nightMode,
    ?PolarCircleRule $fallbackRule,
    array &$warnings,
): array
```

Order of construction (the envelope sorts later):

1. `componentSource($this->solar, 'solar', $warnings)`
2. method: `CalculationSource::algorithm('prayer.method.' . $parameters->method->value, CalculationMethod::SOURCE_VERSION)`
3. `componentSource($parameters->ishaRule, 'isha', $warnings)`
4. `componentSource($parameters->maghribRule, 'maghrib', $warnings)`
5. only when `$nightMode === 'portions'`: `componentSource($this->highLatitudeCorrection, 'high_latitude', $warnings)`
6. only when `$nightMode === 'seasonal'`: `componentSource($this->twilightModel, 'twilight', $warnings)`
7. only when `$fallbackRule !== null`: `componentSource($this->polarCircleHandler, 'polar', $warnings)`
8. only when `$parameters->adjustments->offsets !== []`: `CalculationSource::callerInput('caller.adjustments')`

`componentSource(object $component, string $name, array &$warnings): CalculationSource`:

- if `$component instanceof IdentifiesCalculationSource`, return `$component->calculationSource()`;
- otherwise build `$id = 'custom.' . $name`, append
  `new CalculationWarning(WarningCode::UNIDENTIFIED_CALCULATION_SOURCE, sprintf('The %s component does not implement IdentifiesCalculationSource.', $name), ['component' => $name, 'source_id' => $id])`,
  and return `CalculationSource::algorithm($id)`.

`polarWarning()` builds one `POLAR_FALLBACK_APPLIED` warning with the seven detail keys from spec §7.1. Use the spec's illustrative message ("Polar fallback replaced the requested coordinates because the solar day is undefined.") or equivalent; tests do not pin it.

Do **not** read `CalculationMethod::SOURCE_VERSION` for the package version; it is the preset-defaults contract version. Do not read `LibraryVersion::CURRENT` in the calculator; the envelope owns that.

- [ ] **Step 6: Update the API-reference row**

`Prayer\Services\PrayerTimesCalculator` gains `calculateWithMetadata(...): PrayerCalculationOutcome` and the note that it implements `PrayerMetadataCalculatorInterface`.

- [ ] **Step 7: Verify**

```bash
vendor/bin/phpunit --no-coverage --filter PrayerMetadataTest
vendor/bin/phpunit --no-coverage --filter PrayerTimesCalculatorTest
vendor/bin/phpunit --no-coverage --filter NightResolver
composer lint
composer stan
composer arch
composer bench
```

The legacy benchmark must stay far under the 50 ms / 30,000 ms tripwires. If the added trace array moves the single-day median by more than ~2x, stop and profile before continuing.

- [ ] **Step 8: Commit**

```bash
git add src/Prayer/Services/PrayerTimesCalculator.php tests/Prayer/PrayerMetadataTest.php docs/user/api-reference.md
git commit -m "feat(prayer): add opt-in calculation metadata path"
```

---

## Task 9: `TimetableService::monthlyWithMetadata()`

**Files:**

- Modify `src/Prayer/Services/TimetableService.php`
- Create `tests/Prayer/TimetableMetadataTest.php`
- Modify `docs/user/api-reference.md`

- [ ] **Step 1: Write the failing tests**

`tests/Prayer/TimetableMetadataTest.php`:

- `testFebruaryLeapYearReturnsOneOutcomePerDayInOrder` — Kuala Lumpur, February 2025, `monthlyWithMetadata()`: `count = 29`; each element is a `PrayerCalculationOutcome`; every metadata has `context = 'prayer'`; dates are `2025-02-01` … `2025-02-29` in order; legacy `monthly()` returns 29 schedules with equal `toArray()` values.
- `testDaySpecificPolarFallbackIsVisible` — Tromsø, November 2025, MWL + `PolarCircleRule::NEAREST_LATITUDE`, null injected calculator:
  - `count = 30`;
  - days that required the polar fallback carry `fallback.applied = true`; the others carry `false`;
  - every applied fallback has `status = 'fallback_applied'` and a `polar_fallback_applied` warning;
  - at least one day is a fallback (the month contains polar-night days);
  - legacy `monthly()` still returns 30 schedules and does not expose metadata.
- `testInjectedLegacyCalculatorThrowsConfigurationException` — a double implementing only `PrayerTimesCalculatorInterface`; `monthlyWithMetadata()` throws `ConfigurationException`, while `monthly()` on the same service still works.
- `testInjectedMetadataCalculatorIsUsed` — a double implementing `PrayerMetadataCalculatorInterface` returning canned outcomes; assert the exact outcome instances are returned in order and `calculateWithMetadata()` was called once per day.
- `testInvalidMonthStillThrows` — month `0` and `13` throw `ValidationException` from both methods.
- `testMonthlyIsUnchanged` — for a normal month, `monthly()` results equal `monthlyWithMetadata()` results element-by-element.

Double wiring: the metadata double returns a real `PrayerCalculationOutcome` built from `CalculationMetadata::create(CalculationContext::PRAYER)`, the passed date/coordinates, and a canned `PrayerSchedule` (copy the fixture style from `TimetableServiceTest`).

- [ ] **Step 2: Run the tests to verify they fail**

```bash
vendor/bin/phpunit --no-coverage --filter TimetableMetadataTest
```

- [ ] **Step 3: Extract the shared month guard**

Add:

```php
private static function daysInMonth(int $year, int $month): int
{
    if ($month < 1 || $month > 12) {
        throw new ValidationException(sprintf('Invalid month %d: expected 1–12.', $month));
    }

    return (int) CivilDate::fromYmd($year, $month, 1)->toUtcMidnight()->format('t');
}
```

`monthly()` delegates to it (its exception message and behaviour stay identical).

- [ ] **Step 4: Implement `monthlyWithMetadata()`**

```php
/**
 * @return list<PrayerCalculationOutcome>
 */
public function monthlyWithMetadata(
    Coordinates $coordinates,
    int $year,
    int $month,
    CalculationParameters $parameters,
): array
{
    $days = self::daysInMonth($year, $month);
    $calc = $this->calculator ?? PrayerTimesCalculator::defaultFor($parameters);

    if (! $calc instanceof PrayerMetadataCalculatorInterface) {
        throw new ConfigurationException(
            'The injected calculator does not implement PrayerMetadataCalculatorInterface; monthlyWithMetadata() cannot record per-day provenance.',
        );
    }

    $out = [];

    for ($d = 1; $d <= $days; ++$d) {
        $out[] = $calc->calculateWithMetadata($coordinates, CivilDate::fromYmd($year, $month, $d), $parameters);
    }

    return $out;
}
```

The new method is a concrete `TimetableService` capability; `TimetableServiceInterface` is unchanged. The docblock must state that a null calculator uses `PrayerTimesCalculator::defaultFor($parameters)` and that the legacy `monthly()` path is unchanged.

- [ ] **Step 5: Update the API-reference row**

`Prayer\Services\TimetableService` gains `monthlyWithMetadata(...): list<PrayerCalculationOutcome>` and the injected-calculator contract note.

- [ ] **Step 6: Verify**

```bash
vendor/bin/phpunit --no-coverage --filter TimetableMetadataTest
vendor/bin/phpunit --no-coverage --filter TimetableServiceTest
composer lint
composer stan
composer arch
```

- [ ] **Step 7: Commit**

```bash
git add src/Prayer/Services/TimetableService.php tests/Prayer/TimetableMetadataTest.php docs/user/api-reference.md
git commit -m "feat(prayer): add metadata-aware monthly timetable"
```

---

## Task 10: Documentation, integration snippet, changelog, and final gates

**Files:**

- Create `docs/provenance.md`
- Create `docs/user/calculation-metadata.md`
- Modify `docs/README.md`
- Modify `docs/integrations.md`
- Modify `docs/prayer.md`
- Modify `docs/user/api-reference.md` (method-level mentions only; class rows landed earlier)
- Modify `docs/user/integration.md`
- Modify `docs/user/migration.md`
- Modify `docs_site/astro.config.mjs`
- Modify `ARCHITECTURE.md`
- Modify `AGENTS.md`, `CONTEXT.md`
- Modify `CHANGELOG.md`
- Modify `docs/user/changelog.md` (generated, do not hand-edit)
- Modify `examples/bench.php`
- Modify `tests/Wiring/CalculatorWiringTest.php`

- [ ] **Step 1: Write the contributor page**

Create `docs/provenance.md` with these sections (spec §13.1):

1. Why the metadata path exists and what it does not claim.
2. Shared vocabulary and status precedence.
3. Warning-code table with detail keys (copy from spec §7.1; state that A1 emits only `polar_fallback_applied` and `unidentified_calculation_source`, A2/A3 add the rest).
4. Source-id registry and the custom-component policy.
5. Per-context metadata fields and JSON examples for A1 (prayer only; mark Qibla/Moon/Hijri as later phases).
6. Schema-version (`1.0`) and library-version policy.
7. One reproducibility recipe: requested inputs + `policy` + `result` + `warnings`.

Link to the spec for the full decision record; do not duplicate the A2/A3 schemas.

- [ ] **Step 2: Add the map rows**

In `docs/README.md`, add the page-map row:

`| [provenance.md](provenance.md) | provenance vocabulary, warning/status semantics, source registry | [user/calculation-metadata.md](user/calculation-metadata.md) |`

The plan itself is already linked from the design-spec row when this plan is committed; do not add a second link.

`tests/ArchDocsTest::testContributorDocsAreMapped` requires every top-level `docs/*.md` page to be linked.

- [ ] **Step 3: Write the published guide**

Create `docs/user/calculation-metadata.md` with Starlight frontmatter (`title`, `description`) and the spec §13.2 sections:

- when to use the metadata path vs the compact path;
- a complete prayer example with JSON;
- storing inputs + metadata + result for reproduction;
- handling warning codes at an application boundary;
- true-north Qibla vs caller-supplied magnetic declination (A2 will ship the method; state the policy now);
- Moon criterion vs observation/authority (A2);
- generated vs official Hijri tables (A3);
- schema-version compatibility.

Every JSON block must be valid JSON. Example values are illustrative; say so.

- [ ] **Step 4: Wire the docs site**

In `docs_site/astro.config.mjs`, add to the `sidebar` array after "Timetable & Next Prayer":

```js
{
  label: 'Calculation Metadata',
  link: '/calculation-metadata/',
},
```

Do not regenerate version snapshots; each released version has its own sidebar JSON.

- [ ] **Step 5: Add the executable integration recipe**

Add a `## Calculation metadata` section to `docs/integrations.md` with one fenced `php` block:

```php
use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Services\PrayerTimesCalculator;
use AlFalaq\AlFalaq\Prayer\Services\TimetableService;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

$calculator = PrayerTimesCalculator::default();
$params = CalculationMethod::SINGAPORE->defaultParameters();
$coordinates = Coordinates::fromDegrees(3.1390, 101.6869);

$outcome = $calculator->calculateWithMetadata(
    $coordinates,
    CivilDate::fromYmd(2025, 3, 1),
    $params,
);
$metadata = $outcome->metadata->toArray();
$json = json_encode($outcome->toArray(), JSON_THROW_ON_ERROR);

$monthOutcomes = TimetableService::default()->monthlyWithMetadata(
    $coordinates,
    2025,
    3,
    $params,
);
```

Then extend `tests/Wiring/CalculatorWiringTest.php`'s `SECTION_EXPECTATIONS` with:

```php
'Calculation metadata' => [
    'outcome' => PrayerCalculationOutcome::class,
    'metadata' => 'array',
    'json' => 'string',
    'monthOutcomes' => 'array',
],
```

Import `PrayerCalculationOutcome` in the test. Add a prose sentence that the JSON shape is documented at `calculation-metadata.md` and that the legacy methods are unchanged.

- [ ] **Step 6: Update the prayer appendix and architecture docs**

- `docs/prayer.md`: add a "Provenance" section listing the A1 source ids, the two emitted warning codes, the status derivation, and a link to `docs/provenance.md`. Do not restate the whole schema.
- `ARCHITECTURE.md`: add a provenance subsection under section 8 ("Integration seams and extension points") covering:
  - the shared vocabulary and `IdentifiesCalculationSource`;
  - metadata built at orchestration boundaries, never inside `Astro`;
  - the single-path rule (`run()` shared by `calculate()` and `calculateWithMetadata()`);
  - the additive-only compatibility promise.
- `docs/user/integration.md`: a two-sentence pointer to `calculation-metadata.md`.
- `docs/user/migration.md`: an "Additive, no migration required" note naming `calculateWithMetadata()` and `monthlyWithMetadata()`.
- `AGENTS.md` and `CONTEXT.md`: A1-scoped status update — shared vocabulary and prayer/timetable metadata are public; Qibla/Moon/Hijri metadata remain A2/A3. Do not claim the whole phase landed.
- `docs/user/citations.md`: no digest or source citation is added in A1; leave unchanged unless a new citation is actually introduced.

- [ ] **Step 7: Update the benchmark harness**

In `examples/bench.php`, add a metadata measurement **without** changing the enforced tripwires:

- measure `calculateWithMetadata()` over the same 21 single-day runs;
- measure `monthlyWithMetadata()` over the same 12 months;
- print a second line with the two medians and label them "not gated";
- keep the legacy numbers and `--budget` exit behaviour exactly as they are.

Record both pairs in the PR body. Metadata is expected to be slower (object construction); the gate remains on the legacy path.

- [ ] **Step 8: Changelog and generated page**

Add under `CHANGELOG.md` → `[Unreleased]` → `### Added`:

- opt-in calculation metadata (`calculateWithMetadata()`, `monthlyWithMetadata()`) with schema `1.0`, stable warning codes, and a stable source registry; legacy methods and outputs unchanged;
- `CalculationParameters::toArray()` and `PrayerAdjustments::toArray()`;
- `docs/provenance.md` and `docs/user/calculation-metadata.md`;
- `IdentifiesCalculationSource` and the built-in source ids.

Then:

```bash
composer changelog
composer changelog:check
```

Never hand-edit `docs/user/changelog.md`.

- [ ] **Step 9: Run the complete gate**

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

- [ ] **Step 10: Run the mutation and performance gates**

```bash
COMPOSER_PROCESS_TIMEOUT=0 composer mutation
composer bench
```

- Mutation: MSI ≥ 85 (the PR-only gate). If a new mutant escapes, add the missing assertion; do not lower the gate and do not add a baseline entry.
- Benchmark: legacy tripwires pass; record the legacy and metadata medians from the new output.

- [ ] **Step 11: Build the docs site**

```bash
cd docs_site
npm run build
cd ..
```

Expected: the new page renders, the sidebar link resolves, and no version snapshot is modified. If the build needs dependencies, run `npm ci` first; do not commit `node_modules/`.

- [ ] **Step 12: Verify the frozen surface**

```bash
git diff --stat main...HEAD
git status --short
```

Check:

- no changes under `docs/user/v*/`;
- no changes to `src/Prayer/Contracts/PrayerTimesCalculatorInterface.php`, `src/Prayer/Contracts/TimetableServiceInterface.php`, `src/Astro/Contracts/SolarDayEngine.php`, `src/Prayer/ValueObjects/PrayerSchedule.php`'s `toArray()`/constructor;
- no new runtime `require` in `composer.json`;
- no `DateTimeInterface` unions, no framework imports, no network/clock reads.

- [ ] **Step 13: Commit**

```bash
git add docs docs_site/astro.config.mjs ARCHITECTURE.md AGENTS.md CONTEXT.md CHANGELOG.md examples/bench.php tests/Wiring/CalculatorWiringTest.php
git commit -m "docs: document calculation metadata and wire the A1 release notes"
```

---

## Final acceptance checklist

- [ ] `composer validate --strict`, `composer ci`, `composer arch`, `composer changelog:check` green.
- [ ] `composer test` reports line coverage ≥ 95% (never lowered).
- [ ] `composer mutation` reports MSI ≥ 85.
- [ ] `composer bench` stays within 50 ms single-day / 30,000 ms year; legacy vs metadata medians recorded.
- [ ] 607 baseline tests plus the new provenance/metadata tests all pass; assertion count only grows.
- [ ] Legacy results are byte-for-byte equal to metadata outcome results across the compatibility grid.
- [ ] Every new public class-like appears in `docs/user/api-reference.md`; the reverse gate finds no stale entry.
- [ ] `docs/README.md` maps `docs/provenance.md` and links this plan from the spec.
- [ ] `docs_site` builds with the new page and sidebar entry; no version snapshot changed.
- [ ] No A2/A3 types, methods, or claims were introduced.
- [ ] `AGENTS.md` and `CONTEXT.md` describe A1 only, not the whole provenance phase.

## Rollback

Every task is one commit. If a gate fails late, revert the offending commit rather than weakening a gate:

```bash
git revert <commit>
```

The shared vocabulary (Tasks 1–4) has no dependency on the prayer path and can stand alone; Tasks 5–9 can be reverted independently; Task 10 is documentation-only except for the benchmark and wiring test.
