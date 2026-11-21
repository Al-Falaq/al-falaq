# Provenance A2 — Qibla and Moon Metadata Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Land A2 of the provenance/explainability programme: reuse the frozen `SharedKernel` vocabulary for the Qibla and crescent paths, exposing true-north geodesy provenance, Moon ephemeris/ΔT provenance, no-moonset state, and criterion-versus-observation warnings without changing any legacy result, method signature, or numeric output.

**Architecture:** A2 is four stacked layers delivered as individually revertable commits:

1. **Shared source resolution** — the `custom.<component>` + `unidentified_calculation_source` policy moves out of `PrayerTimesCalculator` into one stateless `SharedKernel\Traits\ResolvesCalculationSources` helper, so Qibla, Moon, and (later) Hijri cannot drift from the frozen warning semantics.
2. **Qibla identity and metadata** — both geodesies declare `SOURCE_ID`/`SOURCE_VERSION`; `QiblaResult::toArray()` becomes the payload serializer; `QiblaCalculator::calculateWithMetadata()` shares one private `solve()` with `calculate()` and reports true north with an explicit null magnetic declination.
3. **Moon identity and serializers** — the ephemeris, conjunction search, ΔT provider, and four criteria expose stable registry ids; `CrescentGeometry::toArray()` and `CrescentVerdict::toArray()` become the payload serializers.
4. **Moon trace and metadata** — `CrescentCalculator` extracts its existing sunset computation into one private routine that returns the geometry plus ΔT identity/sources. The three metadata siblings build their envelope from that trace. ΔT extrapolation, no-moonset, caller-supplied geometry, and criterion evaluation are reported through the frozen warning/status vocabulary.

Legacy behaviour is frozen: exceptions, return values, `QiblaResult` properties, `CrescentGeometry` properties, and `CrescentVerdict` properties keep their current contract. A3 (Hijri) is out of scope and must reuse the same vocabulary unchanged.

**Tech Stack:** PHP 8.2+ (no runtime dependencies), PHPUnit 11, PHPStan max + strict-rules, php-cs-fixer `@PER`, Infection (MSI ≥ 85), Astro Starlight docs site, plain-PHP tooling under `tools/`.

**Design authority:** [`docs/design/2026-11-15-provenance-explainability-foundation.md`](../design/2026-11-15-provenance-explainability-foundation.md), especially §6.4 (Qibla/Moon), §6.5–6.6 (service methods and serializers), §7 (warnings/status), §8 (source registry), §9.4–9.5 (wire shapes), §10.2–10.3 (single-path rules), §12 (test matrix), and §14 A2. If implementation contradicts the spec, stop and fix the spec first; do not silently diverge.

**Plan location note:** the `writing-plans` skill defaults to `docs/superpowers/plans/`, which this repository gitignores. The plan is committed with the work and follows the `docs/plans/` precedent.

**Branch:** `feat/provenance-a2-qibla-moon`, split from `main` at the A2 plan commit (record the exact commit in the PR).

**Baseline (verified on `main` at `9407178`):** 747 tests / 51,117 assertions (`composer test-unit`), 32 arch tests / 428 assertions, 98.8% line coverage at the A1 merge, 0 PHPStan errors, 0 php-cs-fixer findings, MSI gate 85, benchmark tripwires 50 ms single-day / 30,000 ms year.

**Frozen artefacts:** released user-docs snapshots under `docs/user/vX.Y.Z/` are never edited. Only current `docs/user/*.md` files change.

---

## Scope

### In scope

- `SharedKernel\Traits\ResolvesCalculationSources` (single owner for custom-source fallback).
- Qibla source identity on `SphericalCalculator` and `Wgs84KarneyCalculator`.
- `QiblaResult::toArray()`.
- `QiblaCalculationMetadata`, `QiblaCalculationOutcome`.
- `QiblaCalculator::calculateWithMetadata()` via one shared private numeric routine.
- Moon source identity on `CrescentEphemeris`, `EphemerisConjunctionSearch`, `DeltaTProvider`, and the four evaluators.
- ΔT model constants: `MODEL_ID`, `MODEL_RANGE`, `VALID_FROM_YEAR`, `VALID_TO_YEAR`.
- `CrescentGeometry::toArray()`, `CrescentVerdict::toArray()`.
- `MoonCalculationMetadata`, `CrescentGeometryOutcome`, `CrescentEvaluationOutcome`.
- `CrescentCalculator::atSunsetWithMetadata()`, `evaluateWithMetadata()`, `evaluateGeometryWithMetadata()` via one shared private sunset routine.
- ΔT extrapolation, no-moonset, criterion-versus-observation warnings.
- Caller-supplied geometry with explicitly absent provenance.
- API reference, contributor provenance page, published metadata guide, integration snippets, architecture/changelog/context updates.
- Tests for every new public type and every new branch.

### Out of scope (do not implement here)

- Hijri metadata (A3): `convertWithMetadata()`, `CalendarProvenance`, `DescribesCalendar`, `HijriDateFrame`, `HijriCalculationMetadata`, `HijriCalculationOutcome`.
- A magnetic-declination model; Qibla metadata remains true-north only, with a null declination field.
- Changing `GeodesyCalculator`, `ConjunctionSearch`, or `CrescentEvaluator` contracts.
- Changing `QiblaCalculator`'s public factories, `QiblaResult` properties, `CrescentGeometry` properties, or `CrescentVerdict` properties.
- Changing `atSunset()`, `evaluate()`, `evaluateGeometry()`, or `calculate()` behavior.
- A global warning mode, `set_error_handler`, logging, or exception promotion inside the library.
- Package metadata reads, clock reads, network calls, or Composer runtime dependencies.
- Regenerating or editing released docs snapshots.

### Non-negotiable invariants

1. Every legacy test keeps passing unchanged; no numeric golden is re-pinned.
2. `calculate()` and `calculateWithMetadata()` share one Qibla numeric routine; `atSunset()`/`evaluate()` and their metadata siblings share one sunset routine. Metadata never recomputes astronomy or parses exception strings.
3. `CalculationMetadata::create()` is the only envelope constructor. Status is derived from warnings; callers cannot supply it.
4. Warning codes are stable; warning messages are explanatory and are not pinned by tests.
5. Source ids are stable within schema major version 1; a custom component that does not implement `IdentifiesCalculationSource` gets `custom.<component>` plus `unidentified_calculation_source`.
6. Metadata `toArray()` shapes are key-ordered, contain all nullable keys with `null`, and contain all optional lists with `[]`.
7. `src/` stays `strict_types=1`, final/readonly where required, docblock-complete, and free of framework/Astro imports outside the documented wiring exception.
8. New public class-likes are added to `docs/user/api-reference.md` in the same commit that creates them; `tests/ArchApiReferenceTest.php` is exact in both directions.
9. `Moon\ValueObjects` must not import `Astro\`; ΔT calibrated bounds cross that boundary through the provider source's documented `range`, not through a direct engine import.
10. A caller-supplied `CrescentGeometry` carries no coordinates, date, ΔT, or geometry source. The metadata path serializes those as `null`/`caller_supplied` rather than inventing them.

---

## Pre-flight

**Files:** none.

- [ ] **Step 1: Create the branch**

```bash
git switch main
git pull --ff-only
git switch -c feat/provenance-a2-qibla-moon
```

- [ ] **Step 2: Re-run the baseline**

```bash
composer validate --strict
composer ci
composer arch
```

Expected: everything green, 747 tests in the unit suite, 32 arch tests. If `main` has moved, record the new commit and test counts in the PR description; do not lower any gate.

- [ ] **Step 3: Read the frozen spec sections**

Read spec §6.4 (Qibla/Moon metadata and outcomes), §6.5–6.6, §7, §8, §9.4–9.5, §10.2–10.3, §12, and §14 A2. Keep the spec open beside the implementation; it is the contract.

- [ ] **Step 4: Confirm the internal trace contract**

The numeric routines stay private and return exactly the data the metadata needs:

```php
// QiblaCalculator
private function solve(Coordinates $observer): QiblaResult;

// CrescentCalculator
/**
 * @return array{
 *     geometry: CrescentGeometry,
 *     deltaTSeconds: float,
 *     deltaTSource: CalculationSource,
 *     sources: list<CalculationSource>,
 * }
 */
private function sunset(
    Coordinates $coordinates,
    CivilDate $date,
    ?float $deltaTOverride,
    array &$warnings,
): array;
```

The Moon trace's four keys are the spec §10.3 shape. The warning sink is an internal by-reference parameter, not a fifth trace key; it lets the same routine report unidentified components and ΔT extrapolation without a second pass. `atSunset()` passes a discarded sink and returns only `['geometry']`.

---

## Task 1: Shared calculation-source resolution

**Files:**

- Create `src/SharedKernel/Traits/ResolvesCalculationSources.php`
- Modify `src/Prayer/Services/PrayerTimesCalculator.php`
- Modify `docs/user/api-reference.md`

- [ ] **Step 1: Extract the helper**

Create a stateless trait with one private static method:

```php
trait ResolvesCalculationSources
{
    /**
     * Identity for a wired component, or `custom.<name>` plus a warning.
     *
     * @param object $component Wired strategy or engine.
     * @param string $name Registry slot name (solar, isha, geodesy, …).
     * @param list<CalculationWarning> $warnings Warning sink, appended in place.
     * @return CalculationSource Identified source or the custom fallback.
     */
    private static function componentSource(object $component, string $name, array &$warnings): CalculationSource
    {
        if ($component instanceof IdentifiesCalculationSource) {
            return $component->calculationSource();
        }

        $id = 'custom.' . $name;

        $warnings[] = new CalculationWarning(
            WarningCode::UNIDENTIFIED_CALCULATION_SOURCE,
            sprintf('The %s component does not implement IdentifiesCalculationSource.', $name),
            ['component' => $name, 'source_id' => $id],
        );

        return CalculationSource::algorithm($id);
    }
}
```

The trait must have a class docblock summary. It declares no properties and no public method (the arch trait gate requires private-only helpers).

- [ ] **Step 2: Make Prayer use the shared owner**

In `PrayerTimesCalculator`, import the trait and add `use ResolvesCalculationSources;` beside the class opening. Delete the private `componentSource()` method; keep `sourcesFor()` and every call site unchanged.

- [ ] **Step 3: Add the API-reference mention**

Extend the existing row:

```markdown
| `SharedKernel\Traits\HasWithersGuard`, `SharedKernel\Traits\ValidatesFinite`, `SharedKernel\Traits\ResolvesCalculationSources` | Stateless helpers used by the value objects and services; `ResolvesCalculationSources` owns the `custom.<component>` + `unidentified_calculation_source` fallback; their members are `private` by design |
```

- [ ] **Step 4: Verify the extraction is behavior-neutral**

```bash
vendor/bin/phpunit --no-coverage --filter PrayerMetadataTest
composer stan
composer arch
```

The existing custom-component tests (`custom.solar`, `custom.isha`, identified custom engine) are the regression proof; do not weaken them.

- [ ] **Step 5: Commit**

```bash
git add src/SharedKernel/Traits/ResolvesCalculationSources.php src/Prayer/Services/PrayerTimesCalculator.php docs/user/api-reference.md
git commit -m "feat(shared-kernel): add calculation-source resolution helper"
```

---

## Task 2: Qibla source identity and result serializer

**Files:**

- Modify `src/Qibla/Strategies/SphericalCalculator.php`
- Modify `src/Qibla/Strategies/Wgs84KarneyCalculator.php`
- Modify `src/Qibla/ValueObjects/QiblaResult.php`
- Modify `tests/SharedKernel/Provenance/BuiltInSourceIdentityTest.php`
- Modify `tests/Qibla/QiblaResultTest.php`
- Modify `docs/user/api-reference.md`

- [ ] **Step 1: Write the failing identity and serializer tests**

In `BuiltInSourceIdentityTest`, add imports for both Qibla strategies and extend `componentProvider()`:

```php
yield 'qibla spherical' => [new SphericalCalculator(), 'qibla.spherical', 'todhunter-haversine'];
yield 'qibla wgs84' => [new Wgs84KarneyCalculator(), 'qibla.wgs84-karney', 'geographiclib-2.1'];
```

Raise `testProviderCoversEveryBuiltInComponent()` from 13 to 15.

In `QiblaResultTest`, add:

- `testToArrayHasTheWireShape` — `new QiblaResult(292.4, 7070.0)->toArray()` is exactly `['direction_degrees' => 292.4, 'distance_km' => 7070.0]`.

- [ ] **Step 2: Run the tests to verify they fail**

```bash
vendor/bin/phpunit --no-coverage --filter 'BuiltInSourceIdentityTest|QiblaResultTest'
```

Expected: both strategies fail `IdentifiesCalculationSource`; `toArray()` is missing.

- [ ] **Step 3: Implement the strategy identity**

Both classes implement `GeodesyCalculator, IdentifiesCalculationSource` and declare:

```php
// SphericalCalculator
public const SOURCE_ID = 'qibla.spherical';
public const SOURCE_VERSION = 'todhunter-haversine';

// Wgs84KarneyCalculator
public const SOURCE_ID = 'qibla.wgs84-karney';
public const SOURCE_VERSION = 'geographiclib-2.1';
```

Each adds:

```php
/**
 * Stable provenance identity for this geodesy.
 *
 * @return CalculationSource Algorithm source carrying SOURCE_ID/SOURCE_VERSION.
 */
public function calculationSource(): CalculationSource
{
    return CalculationSource::algorithm(self::SOURCE_ID, self::SOURCE_VERSION);
}
```

Do not add a version to `GeodesyCalculator`; identity stays an additive capability.

- [ ] **Step 4: Implement `QiblaResult::toArray()`**

```php
/**
 * Wire shape: direction then distance.
 *
 * @return array{direction_degrees: float, distance_km: float}
 */
public function toArray(): array
{
    return [
        'direction_degrees' => $this->direction,
        'distance_km' => $this->distanceKm,
    ];
}
```

- [ ] **Step 5: Add the API-reference rows**

- Update the `Qibla\Strategies\SphericalCalculator`, `Qibla\Strategies\Wgs84KarneyCalculator` row to mention `IdentifiesCalculationSource` and the stable ids/versions.
- Extend the `Qibla\ValueObjects\QiblaResult` row with `toArray()`.

- [ ] **Step 6: Verify**

```bash
vendor/bin/phpunit --no-coverage --filter 'BuiltInSourceIdentityTest|QiblaResultTest'
composer lint
composer stan
composer arch
```

- [ ] **Step 7: Commit**

```bash
git add src/Qibla/Strategies/SphericalCalculator.php src/Qibla/Strategies/Wgs84KarneyCalculator.php src/Qibla/ValueObjects/QiblaResult.php tests/SharedKernel/Provenance/BuiltInSourceIdentityTest.php tests/Qibla/QiblaResultTest.php docs/user/api-reference.md
git commit -m "feat(qibla): identify geodesy sources and serialize results"
```

---

## Task 3: Qibla metadata and `calculateWithMetadata()`

**Files:**

- Create `src/Qibla/ValueObjects/QiblaCalculationMetadata.php`
- Create `src/Qibla/ValueObjects/QiblaCalculationOutcome.php`
- Modify `src/Qibla/Services/QiblaCalculator.php`
- Create `tests/Qibla/QiblaMetadataTest.php`
- Modify `docs/user/api-reference.md`

- [ ] **Step 1: Write the failing tests**

`tests/Qibla/QiblaMetadataTest.php`:

**Normal shape**

- `testSphericalOutcomeIsCompleteWithTheRegistrySource` — Kuala Lumpur `(3.1390, 101.6869)` through `QiblaCalculator::default()->calculateWithMetadata(...)`:
  - `context = 'qibla'`, `status = 'complete'`, `warnings = []`, `schema_version = '1.0'`, `library_version = LibraryVersion::CURRENT`;
  - `sources` is exactly one source: `qibla.spherical`, kind `algorithm`, version `todhunter-haversine`, digest/range null;
  - `input.coordinates` is the requested pair;
  - `heading_reference = 'true_north'`;
  - `magnetic_declination_degrees === null`;
  - key order is the common six keys, then `input`, `heading_reference`, `magnetic_declination_degrees`;
  - `json_encode($outcome->toArray(), JSON_THROW_ON_ERROR)` succeeds and contains no `Astro\\` token.
- `testWgs84OutcomeReportsKarneySource` — `QiblaCalculator::wgs84()` reports `qibla.wgs84-karney` with version `geographiclib-2.1`.
- `testOutcomeToArrayWrapsResultAndMetadata` — exactly `result` and `metadata`; `result` equals `QiblaResult::toArray()`.

**Legacy equality and input forms**

- `testLegacyResultEqualsMetadataResult` — over `default()` and `wgs84()`, Kuala Lumpur/Makkah/Raleigh/Tromsø: `calculate($x)->toArray() === calculateWithMetadata($x)->result->toArray()`.
- `testArrayAndValueObjectInputsAgree` — `[3.1390, 101.6869]` and the `Coordinates` form produce the same outcome `toArray()`.
- `testMalformedArrayStillThrows` — `[3.1390]` still throws `InvalidCoordinatesException` from both methods.

**Custom components**

- `testUnidentifiedGeodesyIsReportedAsCustomWithAWarning` — anonymous `GeodesyCalculator` returning `['direction' => 12.0, 'distanceKm' => 34.0]` and not implementing identity:
  - source `custom.geodesy`;
  - exactly one warning `unidentified_calculation_source` with `details = ['component' => 'geodesy', 'source_id' => 'custom.geodesy']`;
  - status stays `complete`;
  - `calculate()` still returns the same result.
- `testIdentifiedCustomGeodesyIsReportedWithoutAWarning` — anonymous class implementing both contracts, returning `CalculationSource::algorithm('custom.test-geodesy', 'v1')`: source id `custom.test-geodesy`, no warnings.

- [ ] **Step 2: Run the tests to verify they fail**

```bash
vendor/bin/phpunit --no-coverage --filter QiblaMetadataTest
```

Expected: missing classes and method.

- [ ] **Step 3: Implement the metadata value objects**

```php
final readonly class QiblaCalculationMetadata
{
    public const HEADING_REFERENCE = 'true_north';

    /**
     * @param CalculationMetadata $common Shared envelope.
     * @param Coordinates $coordinates Coordinates the caller supplied.
     */
    public function __construct(
        public CalculationMetadata $common,
        public Coordinates $coordinates,
    ) {}

    /**
     * @return array{
     *     schema_version: string,
     *     context: string,
     *     status: string,
     *     library_version: string,
     *     warnings: list<array{code: string, message: string, details: array<string, int|float|string|bool|null>}>,
     *     sources: list<array{id: string, kind: string, version: string|null, digest: string|null, range: string|null}>,
     *     input: array{coordinates: array{latitude: float, longitude: float}},
     *     heading_reference: string,
     *     magnetic_declination_degrees: null,
     * }
     */
    public function toArray(): array
    {
        return [
            ...$this->common->toArray(),
            'input' => ['coordinates' => $this->coordinates->toArray()],
            'heading_reference' => self::HEADING_REFERENCE,
            'magnetic_declination_degrees' => null,
        ];
    }
}
```

```php
final readonly class QiblaCalculationOutcome
{
    /**
     * @param QiblaResult $result Computed result (same value the legacy path returns).
     * @param QiblaCalculationMetadata $metadata Provenance for this calculation.
     */
    public function __construct(
        public QiblaResult $result,
        public QiblaCalculationMetadata $metadata,
    ) {}

    /**
     * @return array{result: array{direction_degrees: float, distance_km: float}, metadata: array<string, mixed>}
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

Both classes are `final readonly` with complete docblocks.

- [ ] **Step 4: Refactor `QiblaCalculator`**

Add `use ResolvesCalculationSources;` and split the numeric path:

```php
/**
 * Compute Qibla direction + distance for one location (M2).
 *
 * @param Coordinates|array{0: mixed, 1: mixed} $coordinates VO or [lat, lon] pair.
 * @return QiblaResult Bearing [0,360) and distance in km.
 */
public function calculate(Coordinates|array $coordinates): QiblaResult
{
    return $this->solve(self::normalize($coordinates));
}

/**
 * Compute the result plus the provenance that explains which geodesy ran.
 *
 * Shares solve() with calculate(); the numeric path cannot drift.
 *
 * @param Coordinates|array{0: mixed, 1: mixed} $coordinates VO or [lat, lon] pair.
 * @return QiblaCalculationOutcome Result plus provenance.
 */
public function calculateWithMetadata(Coordinates|array $coordinates): QiblaCalculationOutcome
{
    $observer = self::normalize($coordinates);
    $warnings = [];
    $sources = [self::componentSource($this->geodesy, 'geodesy', $warnings)];

    return new QiblaCalculationOutcome(
        $this->solve($observer),
        new QiblaCalculationMetadata(
            CalculationMetadata::create(CalculationContext::QIBLA, $warnings, $sources),
            $observer,
        ),
    );
}

private function solve(Coordinates $observer): QiblaResult
{
    $solved = $this->geodesy->inverse($observer);

    return new QiblaResult($solved['direction'], $solved['distanceKm']);
}

private static function normalize(Coordinates|array $coordinates): Coordinates
{
    return $coordinates instanceof Coordinates ? $coordinates : Coordinates::fromArray($coordinates);
}
```

The existing docblock/parameter type on `calculate()` is unchanged. `geodesy()` stays.

- [ ] **Step 5: Add the API-reference rows**

Add rows for `Qibla\ValueObjects\QiblaCalculationMetadata` (`HEADING_REFERENCE`, constructor fields, `toArray()`) and `Qibla\ValueObjects\QiblaCalculationOutcome` (`result` + `metadata`, `toArray()`). Extend `Qibla\Services\QiblaCalculator` with `calculateWithMetadata()`.

- [ ] **Step 6: Verify**

```bash
vendor/bin/phpunit --no-coverage --filter QiblaMetadataTest
composer lint
composer stan
composer arch
```

- [ ] **Step 7: Commit**

```bash
git add src/Qibla/ValueObjects/QiblaCalculationMetadata.php src/Qibla/ValueObjects/QiblaCalculationOutcome.php src/Qibla/Services/QiblaCalculator.php tests/Qibla/QiblaMetadataTest.php docs/user/api-reference.md
git commit -m "feat(qibla): add opt-in calculation metadata"
```

---

## Task 4: Moon source identity and result serializers

**Files:**

- Modify `src/Astro/Services/CrescentEphemeris.php`
- Modify `src/Astro/Services/DeltaTProvider.php`
- Modify `src/Moon/Services/EphemerisConjunctionSearch.php`
- Modify `src/Moon/Strategies/YallopEvaluator.php`
- Modify `src/Moon/Strategies/OdehEvaluator.php`
- Modify `src/Moon/Strategies/Mabims1992Evaluator.php`
- Modify `src/Moon/Strategies/NeoMabims2021Evaluator.php`
- Modify `src/Moon/ValueObjects/CrescentGeometry.php`
- Modify `src/Moon/ValueObjects/CrescentVerdict.php`
- Modify `tests/SharedKernel/Provenance/BuiltInSourceIdentityTest.php`
- Modify `tests/Moon/CrescentGeometryTest.php`
- Create `tests/Moon/CrescentVerdictTest.php`
- Modify `docs/user/api-reference.md`

- [ ] **Step 1: Write the failing identity, constant, and serializer tests**

Extend `BuiltInSourceIdentityTest` with imports and provider rows:

```php
yield 'moon ephemeris' => [new CrescentEphemeris(), 'moon.ephemeris.meeus-2ed', 'meeus-2ed'];
yield 'moon conjunction search' => [new EphemerisConjunctionSearch(new CrescentEphemeris()), 'moon.conjunction-search.meeus-hourly', 'meeus-hourly'];
yield 'yallop' => [new YallopEvaluator(), 'moon.criterion.yallop'];
yield 'odeh' => [new OdehEvaluator(), 'moon.criterion.odeh'];
yield 'mabims 1992' => [new Mabims1992Evaluator(), 'moon.criterion.mabims-1992'];
yield 'neo mabims 2021' => [new NeoMabims2021Evaluator(), 'moon.criterion.neo-mabims-2021'];
```

Raise the provider count from 15 to 21.

Add a test for the ΔT model constants:

- `testDeltaTProviderPublishesItsModelIdentity` — `MODEL_ID === 'moon.delta-t.espenak-2005-2050'`, `MODEL_RANGE === '2005..2050'`, `VALID_FROM_YEAR === 2005.0`, `VALID_TO_YEAR === 2050.0`, and the range agrees with the numeric bounds.

In `CrescentGeometryTest`, add:

- `testToArrayHasTheWireShape` — build the standard geometry and assert exactly:
  ```php
  [
      'sun_altitude_degrees' => -0.8325,
      'moon_altitude_degrees' => 4.2545,
      'elongation_degrees' => 6.1740,
      'moon_age_hours' => 10.0,
      'lag_minutes' => 24.7257,
      'delta_azimuth_degrees' => 1.0279,
      'illuminated_fraction' => 0.0029,
      'arcv_geocentric_degrees' => 6.0880,
      'width_arcminutes' => 0.0953,
      'age_at_moonset_hours' => 10.4121,
  ]
  ```

Create `tests/Moon/CrescentVerdictTest.php` with:

- `testToArrayHasTheWireShape` — `new CrescentVerdict('yallop', true, 1.2, 'A')->toArray()` is exactly `['criterion' => 'yallop', 'visible' => true, 'score' => 1.2, 'zone' => 'A']`.

- [ ] **Step 2: Run the tests to verify they fail**

```bash
vendor/bin/phpunit --no-coverage --filter 'BuiltInSourceIdentityTest|CrescentGeometryTest|CrescentVerdictTest'
```

- [ ] **Step 3: Implement Moon source identity**

- `CrescentEphemeris` implements `IdentifiesCalculationSource` with `SOURCE_ID = 'moon.ephemeris.meeus-2ed'` and `SOURCE_VERSION = 'meeus-2ed'`.
- `EphemerisConjunctionSearch` implements `IdentifiesCalculationSource` with `SOURCE_ID = 'moon.conjunction-search.meeus-hourly'` and `SOURCE_VERSION = 'meeus-hourly'`.
- Each evaluator implements `IdentifiesCalculationSource` and declares only `SOURCE_ID`:
  - `YallopEvaluator` → `moon.criterion.yallop`
  - `OdehEvaluator` → `moon.criterion.odeh`
  - `Mabims1992Evaluator` → `moon.criterion.mabims-1992`
  - `NeoMabims2021Evaluator` → `moon.criterion.neo-mabims-2021`
- Every implementation returns `CalculationSource::algorithm(self::SOURCE_ID, self::SOURCE_VERSION)` where a version exists, otherwise `CalculationSource::algorithm(self::SOURCE_ID)`.

`DeltaTProvider` stays a static-only internal provider (it is not a wired object), but owns its model metadata:

```php
public const MODEL_ID = 'moon.delta-t.espenak-2005-2050';
public const MODEL_RANGE = '2005..2050';
public const VALID_FROM_YEAR = 2005.0;
public const VALID_TO_YEAR = 2050.0;
```

Update its `forYear()` docblock: the provider still returns extrapolated values; the metadata path reports the extrapolation with `delta_t_extrapolated`.

- [ ] **Step 4: Implement the serializers**

`CrescentGeometry::toArray()` uses the spec §6.6 order and returns floats/null as stored:

```php
return [
    'sun_altitude_degrees' => $this->sunAltitude,
    'moon_altitude_degrees' => $this->moonAltitude,
    'elongation_degrees' => $this->elongationDeg,
    'moon_age_hours' => $this->moonAgeHours,
    'lag_minutes' => $this->lagMinutes,
    'delta_azimuth_degrees' => $this->deltaAzimuthDeg,
    'illuminated_fraction' => $this->illuminatedFraction,
    'arcv_geocentric_degrees' => $this->arcvGeoDeg,
    'width_arcminutes' => $this->widthArcmin,
    'age_at_moonset_hours' => $this->ageAtMoonsetHours,
];
```

`CrescentVerdict::toArray()`:

```php
return [
    'criterion' => $this->criterion,
    'visible' => $this->visible,
    'score' => $this->score,
    'zone' => $this->zone,
];
```

- [ ] **Step 5: Add the API-reference mentions**

- `Moon\Services\EphemerisConjunctionSearch`: note `IdentifiesCalculationSource` + `SOURCE_ID`.
- `Moon\ValueObjects\CrescentGeometry`: add `toArray()`.
- `Moon\ValueObjects\CrescentVerdict`: add `toArray()`.
- The four-evaluator row: note `IdentifiesCalculationSource` and the stable ids.

`CrescentEphemeris`/`DeltaTProvider` are `Astro/` internals and stay out of the gate-enforced reference page.

- [ ] **Step 6: Verify**

```bash
vendor/bin/phpunit --no-coverage --filter 'BuiltInSourceIdentityTest|CrescentGeometryTest|CrescentVerdictTest'
composer lint
composer stan
composer arch
```

- [ ] **Step 7: Commit**

```bash
git add src/Astro/Services/CrescentEphemeris.php src/Astro/Services/DeltaTProvider.php src/Moon/Services/EphemerisConjunctionSearch.php src/Moon/Strategies src/Moon/ValueObjects/CrescentGeometry.php src/Moon/ValueObjects/CrescentVerdict.php tests/SharedKernel/Provenance/BuiltInSourceIdentityTest.php tests/Moon/CrescentGeometryTest.php tests/Moon/CrescentVerdictTest.php docs/user/api-reference.md
git commit -m "feat(moon): identify ephemeris and criterion sources"
```

---

## Task 5: Moon metadata and outcome value objects

**Files:**

- Create `src/Moon/ValueObjects/MoonCalculationMetadata.php`
- Create `src/Moon/ValueObjects/CrescentGeometryOutcome.php`
- Create `src/Moon/ValueObjects/CrescentEvaluationOutcome.php`
- Create `tests/Moon/MoonMetadataValueObjectsTest.php`
- Modify `docs/user/api-reference.md`

- [ ] **Step 1: Write the failing value-object tests**

`tests/Moon/MoonMetadataValueObjectsTest.php`:

**Provider metadata shape**

- `testCalculatedGeometryMetadataHasTheWireShape` — build:
  ```php
  $common = CalculationMetadata::create(CalculationContext::MOON, [], [
      CalculationSource::algorithm('moon.ephemeris.meeus-2ed', 'meeus-2ed'),
  ]);
  $metadata = new MoonCalculationMetadata(
      $common,
      Coordinates::fromDegrees(3.1390, 101.6869),
      CivilDate::fromYmd(2025, 2, 28),
      74.56,
      CalculationSource::algorithm(DeltaTProvider::MODEL_ID, range: DeltaTProvider::MODEL_RANGE),
      MoonCalculationMetadata::GEOMETRY_CALCULATED,
      [],
  );
  ```
  Assert key order is the common six keys, then `input`, `delta_t`, `geometry_source`, `criteria`; `input = ['coordinates' => [...], 'date' => '2025-02-28']`; `delta_t = ['seconds' => 74.56, 'origin' => 'provider', 'calibrated_from_year' => 2005.0, 'calibrated_to_year' => 2050.0]`; `geometry_source = 'calculated'`; `criteria = []`.
- `testOverrideMetadataReportsOverrideOriginAndNoCalibratedBounds` — `CalculationSource::callerInput('caller.delta-t')`, `69.0`; `delta_t = ['seconds' => 69.0, 'origin' => 'override', 'calibrated_from_year' => null, 'calibrated_to_year' => null]`.
- `testCallerSuppliedGeometryMetadataHasNoInputsOrDeltaT` — `coordinates = date = deltaTSeconds = deltaTSource = null`, `geometrySource = GEOMETRY_CALLER_SUPPLIED`, `criteria = []`; `input = ['coordinates' => null, 'date' => null]`; `delta_t === null`.
- `testEvaluationMetadataCarriesTheFixedCriteriaOrder` — `criteria = MoonCalculationMetadata::CRITERIA`; assert the array is exactly `['yallop', 'odeh', 'mabims1992', 'neomabims2021']`.
- `testCriteriaOrderCannotBeReorderedOrTruncated` — `#[DataProvider]` over `['odeh', 'yallop', 'mabims1992', 'neomabims2021']`, `['yallop']`, `['yallop', 'odeh', 'mabims1992', 'neomabims2021', 'extra']`; each throws `ValidationException`.

**Validation**

- `testUnknownGeometrySourceIsRejected`.
- `testCalculatedGeometryRequiresCoordinatesDateDeltaTAndSource` — provider over each null in turn; each throws.
- `testCallerSuppliedGeometryRejectsProvenanceFields` — provider over non-null coordinates, date, deltaTSeconds, deltaTSource; each throws.
- `testNonFiniteDeltaTSecondsIsRejected` — `NAN`, `INF`, `-INF`.
- `testDeltaTSourceKindMustBeAlgorithmOrCallerInput` — `CalculationSource::generatedData('moon.delta-t.x', str_repeat('a', 64), '2005..2050')` throws.
- `testProviderSourceRequiresAParseableRange` — `algorithm('moon.delta-t.x')`, `algorithm('moon.delta-t.x', range: '2005')`, `algorithm('moon.delta-t.x', range: '2050..2005')` each throw.

**Outcomes**

- `testGeometryOutcomeToArrayWrapsResultAndMetadata` — exactly `result` and `metadata`; `result` equals `CrescentGeometry::toArray()`.
- `testEvaluationOutcomeToArrayUsesTheFixedVerdictOrder` — build four verdicts in a shuffled array; `toArray()['result']['verdicts']` has keys in `CRITERIA` order and each value equals `CrescentVerdict::toArray()`.
- `testEvaluationOutcomeRejectsMissingExtraOrNonVerdictEntries` — provider over `[]`, four valid + one extra, and one criterion replaced with `new \stdClass()`; each throws `ValidationException`.

- [ ] **Step 2: Run the tests to verify they fail**

```bash
vendor/bin/phpunit --no-coverage --filter MoonMetadataValueObjectsTest
```

- [ ] **Step 3: Implement `MoonCalculationMetadata`**

```php
final readonly class MoonCalculationMetadata
{
    public const GEOMETRY_CALCULATED = 'calculated';
    public const GEOMETRY_CALLER_SUPPLIED = 'caller_supplied';
    public const DELTA_T_PROVIDER = 'provider';
    public const DELTA_T_OVERRIDE = 'override';

    /**
     * Fixed criterion evaluation order.
     *
     * @var list<string>
     */
    public const CRITERIA = ['yallop', 'odeh', 'mabims1992', 'neomabims2021'];

    /**
     * @param CalculationMetadata $common Shared envelope.
     * @param Coordinates|null $coordinates Requested coordinates; null for caller-supplied geometry.
     * @param CivilDate|null $date Requested civil date; null for caller-supplied geometry.
     * @param float|null $deltaTSeconds ΔT used; null for caller-supplied geometry.
     * @param CalculationSource|null $deltaTSource Provider or caller override; null for caller-supplied geometry.
     * @param string $geometrySource One of the GEOMETRY_* constants.
     * @param list<string> $criteria Empty or the fixed CRITERIA order.
     */
    public function __construct(
        public CalculationMetadata $common,
        public ?Coordinates $coordinates,
        public ?CivilDate $date,
        public ?float $deltaTSeconds,
        public ?CalculationSource $deltaTSource,
        public string $geometrySource,
        public array $criteria,
    ) {
        // Validate geometrySource, criteria, and the calculated/caller-supplied
        // field pairing; reject non-finite deltaTSeconds.
    }

    /**
     * @return array{
     *     schema_version: string,
     *     context: string,
     *     status: string,
     *     library_version: string,
     *     warnings: list<array{code: string, message: string, details: array<string, int|float|string|bool|null>}>,
     *     sources: list<array{id: string, kind: string, version: string|null, digest: string|null, range: string|null}>,
     *     input: array{coordinates: array{latitude: float, longitude: float}|null, date: string|null},
     *     delta_t: array{seconds: float, origin: string, calibrated_from_year: float|null, calibrated_to_year: float|null}|null,
     *     geometry_source: string,
     *     criteria: list<string>,
     * }
     */
    public function toArray(): array;
}
```

Validation rules:

- `geometrySource` must be exactly `GEOMETRY_CALCULATED` or `GEOMETRY_CALLER_SUPPLIED`.
- `criteria` must be exactly `[]` or `CRITERIA` (same order, same count).
- calculated geometry: `coordinates !== null`, `date !== null`, `deltaTSeconds !== null` and finite, `deltaTSource !== null`.
- caller-supplied geometry: all four provenance fields must be `null`.
- `deltaTSource` kind:
  - `ALGORITHM` → provider origin; `range` must be present and parse as `from..to` with finite `from <= to`; `calibrated_from_year`/`calibrated_to_year` come from that range.
  - `CALLER_INPUT` → override origin; calibrated bounds are `null` (the `callerInput()` factory already guarantees a null range/version/digest).
  - any other kind → `ValidationException`.

Deriving the calibrated years from `deltaTSource->range` is deliberate: `Moon\ValueObjects` cannot import `Astro\Services\DeltaTProvider` (the layering gate), and the source range is already the documented carrier of the model interval.

`toArray()` spreads `$this->common->toArray()` first, then:

```php
'input' => [
    'coordinates' => $this->coordinates?->toArray(),
    'date' => $this->date?->isoformat(),
],
'delta_t' => $this->deltaTBlock(),
'geometry_source' => $this->geometrySource,
'criteria' => $this->criteria,
```

- [ ] **Step 4: Implement the outcomes**

`CrescentGeometryOutcome` is the simple wrapper:

```php
/**
 * @return array{result: array<string, float|null>, metadata: array<string, mixed>}
 */
public function toArray(): array
{
    return [
        'result' => $this->result->toArray(),
        'metadata' => $this->metadata->toArray(),
    ];
}
```

`CrescentEvaluationOutcome` validates that `$verdicts` contains exactly the four `CRITERIA` keys with `CrescentVerdict` values, then serializes:

```php
$verdicts = [];

foreach (MoonCalculationMetadata::CRITERIA as $criterion) {
    $verdicts[$criterion] = $this->verdicts[$criterion]->toArray();
}

return [
    'result' => [
        'geometry' => $this->result->toArray(),
        'verdicts' => $verdicts,
    ],
    'metadata' => $this->metadata->toArray(),
];
```

The constructor PHPDoc keeps the exact array shape from spec §6.4.

- [ ] **Step 5: Add the API-reference rows**

Rows for `Moon\ValueObjects\MoonCalculationMetadata` (constants, constructor fields, `CRITERIA`, `toArray()`), `Moon\ValueObjects\CrescentGeometryOutcome`, and `Moon\ValueObjects\CrescentEvaluationOutcome`.

- [ ] **Step 6: Verify**

```bash
vendor/bin/phpunit --no-coverage --filter MoonMetadataValueObjectsTest
composer lint
composer stan
composer arch
```

- [ ] **Step 7: Commit**

```bash
git add src/Moon/ValueObjects/MoonCalculationMetadata.php src/Moon/ValueObjects/CrescentGeometryOutcome.php src/Moon/ValueObjects/CrescentEvaluationOutcome.php tests/Moon/MoonMetadataValueObjectsTest.php docs/user/api-reference.md
git commit -m "feat(moon): add calculation metadata value objects"
```

---

## Task 6: `CrescentCalculator` trace and `atSunsetWithMetadata()`

**Files:**

- Modify `src/Moon/Services/CrescentCalculator.php`
- Create `tests/Moon/MoonMetadataTest.php`
- Modify `docs/user/api-reference.md`

- [ ] **Step 1: Write the failing geometry-metadata tests**

`tests/Moon/MoonMetadataTest.php`:

**Normal shape**

- `testCalculatedGeometryIsCompleteWithTheRegistrySourceSet` — Kuala Lumpur `2025-02-28`:
  - `context = 'moon'`, `status = 'complete'`, `warnings = []`, `schema_version = '1.0'`, `library_version = LibraryVersion::CURRENT`;
  - sources are exactly, in sorted order:
    `moon.conjunction-search.meeus-hourly` (version `meeus-hourly`),
    `moon.delta-t.espenak-2005-2050` (range `2005..2050`),
    `moon.ephemeris.meeus-2ed` (version `meeus-2ed`),
    `solar.mean-meeus` (version `meeus-2ed`);
  - `input.coordinates` and `input.date` are the requested pair;
  - `delta_t.origin = 'provider'`, `delta_t.seconds` is finite and close to the committed `used_delta_t_s` 74.56;
  - `delta_t.calibrated_from_year = 2005.0`, `calibrated_to_year = 2050.0`;
  - `geometry_source = 'calculated'`; `criteria = []`;
  - `json_encode(...)` succeeds and contains no `Astro\\` token.
- `testGeometryResultEqualsTheLegacySunset` — `atSunsetWithMetadata(...)->result->toArray() === atSunset(...)->toArray()`.
- `testGeometryMetadataAcceptsACallerDeltaTOverride` — `73.0`:
  - `delta_t = ['seconds' => 73.0, 'origin' => 'override', 'calibrated_from_year' => null, 'calibrated_to_year' => null]`;
  - sources contain `caller.delta-t` with kind `caller_input`, not the provider id;
  - no `delta_t_extrapolated` warning; status `complete`.
- `testNonFiniteDeltaTOverrideStillThrows` — `NAN`, `INF` from both methods; `ValidationException` preserved.

**ΔT extrapolation**

- `testProviderOutsideTheCalibratedRangeIsExtrapolated` — data provider over `2051-01-01` (after) and `2004-12-31` (before), Kuala Lumpur:
  - `status = 'extrapolated'`;
  - exactly one warning `delta_t_extrapolated` with details `year` (float), `calibrated_from_year = 2005.0`, `calibrated_to_year = 2050.0`;
  - sources still list the provider id/range.
- `testFractionalYearBoundaryIsExplicit` — `2050-01-01` is complete (decimal year exactly 2050.0); `2050-01-02` is extrapolated (decimal year > 2050.0). This locks the spec's "same decimal year the provider receives" rule; document it in the guide.
- `testOverrideSuppressesTheExtrapolationWarning` — `2051-01-01` with a caller override is `complete` and lists `caller.delta-t`.

**No moonset**

- `testNoMoonsetIsIndeterminateWithAWarning` — Tromsø `2025-01-15`:
  - `lag_minutes === null` and `age_at_moonset_hours === null` in the result;
  - `status = 'indeterminate'`;
  - exactly one warning `moonset_indeterminate` with `details = ['date' => '2025-01-15', 'reason' => 'no_moonset_on_civil_date']`;
  - the geometry is still returned (no exception).

**Custom components**

- `testUnidentifiedSolarEngineIsReportedAsCustomWithAWarning` — wire a delegating `SolarDayEngine` that does not implement identity; source `custom.solar` + warning.
- `testUnidentifiedConjunctionSearchIsReportedAsCustomWithAWarning` — wire a delegating `ConjunctionSearch` that does not implement identity; source `custom.conjunction_search` + warning.
- `testIdentifiedCustomConjunctionSearchIsReportedWithoutAWarning` — same delegating search implementing `IdentifiesCalculationSource` and returning `CalculationSource::algorithm('custom.test-search', 'v1')`; no warning.

- [ ] **Step 2: Run the tests to verify they fail**

```bash
vendor/bin/phpunit --no-coverage --filter MoonMetadataTest
```

- [ ] **Step 3: Extract the sunset routine**

Change `atSunset()` to:

```php
public function atSunset(
    Coordinates $coordinates,
    CivilDate $date,
    ?float $deltaTOverride = null,
): CrescentGeometry {
    $warnings = [];

    return $this->sunset($coordinates, $date, $deltaTOverride, $warnings)['geometry'];
}
```

Move the current body into:

```php
/**
 * @param list<CalculationWarning> $warnings Warning sink, appended in place.
 * @return array{
 *     geometry: CrescentGeometry,
 *     deltaTSeconds: float,
 *     deltaTSource: CalculationSource,
 *     sources: list<CalculationSource>,
 * }
 */
private function sunset(
    Coordinates $coordinates,
    CivilDate $date,
    ?float $deltaTOverride,
    array &$warnings,
): array
```

Inside `sunset()`:

- Keep the existing override validation first, so `atSunset()` keeps its exception type/message.
- Keep every numeric statement and comment unchanged.
- Compute `$decimalYear = (float) $date->year + ($date->dayOfYear() - 1) / 365.25;` once.
- Build ΔT:
  - override non-null → `$deltaT = $deltaTOverride;` and `$deltaTSource = CalculationSource::callerInput('caller.delta-t');`
  - otherwise → `$deltaT = DeltaTProvider::forYear($decimalYear);` and `$deltaTSource = CalculationSource::algorithm(DeltaTProvider::MODEL_ID, range: DeltaTProvider::MODEL_RANGE);`
  - provider outside `[VALID_FROM_YEAR, VALID_TO_YEAR]` → append one `DELTA_T_EXTRAPOLATED` warning with details `year`, `calibrated_from_year`, `calibrated_to_year`.
- Build the source list:

```php
$sources = [
    self::componentSource($this->solar, 'solar', $warnings),
    self::componentSource($this->ephemeris, 'ephemeris', $warnings),
    self::componentSource($this->conjunctionSearch, 'conjunction_search', $warnings),
    $deltaTSource,
];
```

- Return the four-key trace. Do not call `atSunset()` from the trace or from any metadata method.

- [ ] **Step 4: Implement `atSunsetWithMetadata()`**

```php
public function atSunsetWithMetadata(
    Coordinates $coordinates,
    CivilDate $date,
    ?float $deltaTOverride = null,
): CrescentGeometryOutcome {
    $warnings = [];
    $trace = $this->sunset($coordinates, $date, $deltaTOverride, $warnings);

    if ($trace['geometry']->lagMinutes === null) {
        $warnings[] = self::moonsetWarning($date);
    }

    return new CrescentGeometryOutcome(
        $trace['geometry'],
        new MoonCalculationMetadata(
            CalculationMetadata::create(CalculationContext::MOON, $warnings, $trace['sources']),
            $coordinates,
            $date,
            $trace['deltaTSeconds'],
            $trace['deltaTSource'],
            MoonCalculationMetadata::GEOMETRY_CALCULATED,
            [],
        ),
    );
}
```

Add the warning builder:

```php
private static function moonsetWarning(?CivilDate $date): CalculationWarning
{
    return new CalculationWarning(
        WarningCode::MOONSET_INDETERMINATE,
        'No moonset occurs on the requested civil date; moonset-dependent fields are null.',
        [
            'date' => $date?->isoformat(),
            'reason' => 'no_moonset_on_civil_date',
        ],
    );
}
```

The null `date` is only reachable from caller-supplied geometry; it is deliberate and documented.

- [ ] **Step 5: Add the API-reference method mention**

Extend the `Moon\Services\CrescentCalculator` row with `atSunsetWithMetadata(...): CrescentGeometryOutcome`.

- [ ] **Step 6: Verify**

```bash
vendor/bin/phpunit --no-coverage --filter MoonMetadataTest
composer lint
composer stan
composer arch
```

- [ ] **Step 7: Commit**

```bash
git add src/Moon/Services/CrescentCalculator.php tests/Moon/MoonMetadataTest.php docs/user/api-reference.md
git commit -m "feat(moon): add metadata-aware sunset geometry"
```

---

## Task 7: `evaluateWithMetadata()` and `evaluateGeometryWithMetadata()`

**Files:**

- Modify `src/Moon/Services/CrescentCalculator.php`
- Modify `tests/Moon/MoonMetadataTest.php`
- Modify `docs/user/api-reference.md`

- [ ] **Step 1: Write the failing evaluation-metadata tests**

Append to `tests/Moon/MoonMetadataTest.php`:

**Calculated evaluation**

- `testEvaluationOutcomeIsDecisionRequiredWithCriteriaSources` — Kuala Lumpur `2025-02-28`:
  - `context = 'moon'`, `status = 'decision_required'`;
  - `criteria = ['yallop', 'odeh', 'mabims1992', 'neomabims2021']`;
  - warnings contain exactly one `crescent_criterion_is_not_observation` with `details = ['criteria' => 'yallop,odeh,mabims1992,neomabims2021']`;
  - sources include the geometry's four sources **and** `moon.criterion.yallop`, `moon.criterion.odeh`, `moon.criterion.mabims-1992`, `moon.criterion.neo-mabims-2021`, all kind `algorithm`;
  - `result.geometry` equals `atSunset(...)->toArray()`;
  - `result.verdicts` equals `evaluate(...)` in key order and values;
  - JSON encodes and contains no `Astro\\`.
- `testEvaluationResultEqualsTheLegacyEvaluation` — `evaluateWithMetadata(...)->result` and `->verdicts` match `atSunset(...)` and `evaluate(...)`.
- `testEvaluationPropagatesDeltaTAndNoMoonsetWarnings` — `2051-01-01` yields both `delta_t_extrapolated` and `crescent_criterion_is_not_observation`, with status `decision_required` (highest rank wins) and both warnings present; Tromsø `2025-01-15` yields both `moonset_indeterminate` and the criterion warning, with status `decision_required`.

**Caller-supplied geometry**

- `testCallerSuppliedGeometryDoesNotInventProvenance` — `evaluateGeometryWithMetadata($geometry)`:
  - `input = ['coordinates' => null, 'date' => null]`;
  - `delta_t === null`;
  - `geometry_source = 'caller_supplied'`;
  - `criteria` fixed;
  - sources are exactly the four criterion ids (no solar/ephemeris/conjunction/ΔT source);
  - status `decision_required`; criterion warning present.
- `testCallerSuppliedNoMoonsetStillReportsIndeterminate` — synthetic `CrescentGeometry` with null lag/age: warnings include `moonset_indeterminate` with `details = ['date' => null, 'reason' => 'no_moonset_on_civil_date']` and the criterion warning; status `decision_required`.
- `testCallerSuppliedEvaluationEqualsTheLegacyFanOut` — `verdicts` equal `evaluateGeometry($geometry)`.

**Custom criteria**

- `testUnidentifiedEvaluatorIsReportedAsCustomCriterionWithAWarning` — replace Yallop with a delegating `CrescentEvaluator` that does not implement identity:
  - source `custom.criterion`;
  - exactly one `unidentified_calculation_source` warning with `details = ['component' => 'criterion', 'source_id' => 'custom.criterion']`;
  - the other three criterion sources remain;
  - the verdicts are still produced.
- `testIdentifiedCustomEvaluatorIsReportedWithoutAWarning` — same delegate implementing `IdentifiesCalculationSource` and returning `CalculationSource::algorithm('custom.test-criterion', 'v1')`; no unidentified warning.

- [ ] **Step 2: Run the tests to verify they fail**

```bash
vendor/bin/phpunit --no-coverage --filter MoonMetadataTest
```

- [ ] **Step 3: Implement the evaluation methods**

```php
public function evaluateWithMetadata(
    Coordinates $coordinates,
    CivilDate $date,
    ?float $deltaTOverride = null,
): CrescentEvaluationOutcome {
    $warnings = [];
    $trace = $this->sunset($coordinates, $date, $deltaTOverride, $warnings);
    $verdicts = $this->evaluateGeometry($trace['geometry']);
    $sources = $trace['sources'];
    $sources = [...$sources, ...$this->criterionSources($warnings)];

    if ($trace['geometry']->lagMinutes === null) {
        $warnings[] = self::moonsetWarning($date);
    }

    $warnings[] = self::criterionWarning();

    return new CrescentEvaluationOutcome(
        $trace['geometry'],
        $verdicts,
        new MoonCalculationMetadata(
            CalculationMetadata::create(CalculationContext::MOON, $warnings, $sources),
            $coordinates,
            $date,
            $trace['deltaTSeconds'],
            $trace['deltaTSource'],
            MoonCalculationMetadata::GEOMETRY_CALCULATED,
            MoonCalculationMetadata::CRITERIA,
        ),
    );
}

public function evaluateGeometryWithMetadata(CrescentGeometry $geometry): CrescentEvaluationOutcome
{
    $warnings = [];
    $sources = $this->criterionSources($warnings);

    if ($geometry->lagMinutes === null) {
        $warnings[] = self::moonsetWarning(null);
    }

    $warnings[] = self::criterionWarning();

    return new CrescentEvaluationOutcome(
        $geometry,
        $this->evaluateGeometry($geometry),
        new MoonCalculationMetadata(
            CalculationMetadata::create(CalculationContext::MOON, $warnings, $sources),
            null,
            null,
            null,
            null,
            MoonCalculationMetadata::GEOMETRY_CALLER_SUPPLIED,
            MoonCalculationMetadata::CRITERIA,
        ),
    );
}
```

Add:

```php
/**
 * @param list<CalculationWarning> $warnings Warning sink, appended in place.
 * @return list<CalculationSource> Criterion sources in evaluation order.
 */
private function criterionSources(array &$warnings): array
{
    return [
        self::componentSource($this->yallop, 'criterion', $warnings),
        self::componentSource($this->odeh, 'criterion', $warnings),
        self::componentSource($this->mabims1992, 'criterion', $warnings),
        self::componentSource($this->neoMabims2021, 'criterion', $warnings),
    ];
}

private static function criterionWarning(): CalculationWarning
{
    return new CalculationWarning(
        WarningCode::CRESCENT_CRITERION_IS_NOT_OBSERVATION,
        'Crescent criteria are computed verdicts, not observations.',
        ['criteria' => implode(',', MoonCalculationMetadata::CRITERIA)],
    );
}
```

Do **not** change `evaluate()`/`evaluateGeometry()`; they stay the legacy fan-out.

- [ ] **Step 4: Add the API-reference method mentions**

Extend the `Moon\Services\CrescentCalculator` row with `evaluateWithMetadata(...)` and `evaluateGeometryWithMetadata(...)`.

- [ ] **Step 5: Verify**

```bash
vendor/bin/phpunit --no-coverage --filter MoonMetadataTest
composer lint
composer stan
composer arch
```

- [ ] **Step 6: Commit**

```bash
git add src/Moon/Services/CrescentCalculator.php tests/Moon/MoonMetadataTest.php docs/user/api-reference.md
git commit -m "feat(moon): add metadata-aware crescent evaluation"
```

---

## Task 8: Documentation, integration snippets, benchmark, changelog, and final gates

**Files:**

- Modify `docs/provenance.md`
- Modify `docs/user/calculation-metadata.md`
- Modify `docs/qibla.md`
- Modify `docs/moon.md`
- Modify `docs/user/qibla.md`
- Modify `docs/user/moon-sighting.md`
- Modify `docs/user/integration.md`
- Modify `docs/user/migration.md`
- Modify `docs/integrations.md`
- Modify `docs/README.md` (verify the A2 plan is linked from the design-spec row)
- Modify `ARCHITECTURE.md`
- Modify `AGENTS.md`, `CONTEXT.md`
- Modify `CHANGELOG.md`
- Modify `docs/user/changelog.md` (generated, do not hand-edit)
- Modify `examples/bench.php`
- Modify `tests/Wiring/CalculatorWiringTest.php`

- [ ] **Step 1: Update the contributor page**

In `docs/provenance.md`:

- Intro: A1 and A2 shipped; Hijri remains A3.
- Warning table: state that A1/A2 emit `delta_t_extrapolated`, `polar_fallback_applied`, `moonset_indeterminate`, `crescent_criterion_is_not_observation`, and `unidentified_calculation_source`; the three Hijri codes remain reserved.
- Source registry: move the Qibla and Moon ids out of "reserved" into a shipped Qibla registry and a shipped Moon registry. Keep `hijri.maghrib-probe` and `hijri.offset.caller` reserved.
- Qibla dependency rule: exactly one geodesy source; custom geodesy becomes `custom.geodesy` + warning. No fallback state.
- Moon dependency rules:
  - geometry outcomes list solar, ephemeris, conjunction search, and ΔT provider (or `caller.delta-t`);
  - evaluation outcomes add the four criterion sources;
  - caller-supplied geometry lists only the four criteria;
  - ΔT extrapolation uses the same decimal year the provider receives, so late 2050 is outside the calibrated `2050.0` endpoint;
  - no-moonset adds `moonset_indeterminate`; caller-supplied geometry serializes `date = null` because the bundle carries no date;
  - every evaluation adds `crescent_criterion_is_not_observation` and `decision_required`.
- Add "Qibla metadata" and "Moon metadata" field tables; link the published examples instead of duplicating the full payloads.
- Implementation notes: Qibla `solve()` and Moon `sunset()` are the single numeric routines; `Moon\ValueObjects` derives calibrated bounds from `delta_t.range` because it cannot import `Astro`.
- Reproducibility recipe: Qibla metadata needs the same geodesy wiring; Moon metadata needs the same ephemeris/search/evaluator wiring and the same ΔT source (provider vs override).

- [ ] **Step 2: Update the published guide**

In `docs/user/calculation-metadata.md`:

- Title/description: prayer, Qibla, and crescent results (not prayer only).
- Intro: name the three shipped contexts; Hijri remains later.
- Keep the prayer sections unchanged except for tense and cross-links.
- Add `## Qibla` with:
  - `QiblaCalculator::wgs84()->calculateWithMetadata(Coordinates::fromDegrees(3.1390, 101.6869))`;
  - a full JSON example with `context: qibla`, one geodesy source, `input`, `heading_reference: true_north`, `magnetic_declination_degrees: null`.
- Add `## Crescent geometry` with:
  - `CrescentCalculator::default()->atSunsetWithMetadata(...)`;
  - a full JSON example showing the geometry payload, the four geometry sources, `delta_t` provider block, `geometry_source`, and `criteria: []`.
- Add `## Crescent evaluation` with:
  - `evaluateWithMetadata(...)` and `evaluateGeometryWithMetadata(...)`;
  - a full JSON example showing `result.geometry`, `result.verdicts`, the four criterion sources, and `criteria` populated;
  - the caller-supplied variant showing null `input` fields, null `delta_t`, `geometry_source: caller_supplied`, and only criterion sources.
- Add `## ΔT and no-moonset`:
  - provider vs override origin;
  - the fractional-year boundary (`2050-01-01` complete, `2050-01-02` extrapolated);
  - `moonset_indeterminate`, null lag/age, and the null `date` detail for caller-supplied geometry.
- Update `## Handling warnings` with the new codes and statuses.
- Update the true-north and Moon-criteria sections to present tense and point to the new sections.
- Every JSON block must be valid JSON; generate the examples from the implementation, then re-indent them for readability.

- [ ] **Step 3: Update the context appendices**

- `docs/qibla.md`: add a "Provenance" section with `qibla.spherical`/`qibla.wgs84-karney`, the true-north policy, and `calculateWithMetadata()`.
- `docs/moon.md`: add a "Provenance" section with the three methods, the Moon source ids, ΔT range semantics, no-moonset, and criterion warning.
- `docs/user/qibla.md` and `docs/user/moon-sighting.md`: one-sentence pointers to `calculation-metadata.md`.
- `docs/user/integration.md`: extend the metadata pointer to mention Qibla and crescent results.
- `docs/user/migration.md`: rename the note to cover the whole additive provenance surface and name `calculateWithMetadata()` for Qibla and the three Moon methods.

- [ ] **Step 4: Add executable integration snippets**

In `docs/integrations.md`, add two sections after the existing `## Calculation metadata` section.

```markdown
## Qibla metadata

```php
use AlFalaq\AlFalaq\Qibla\Services\QiblaCalculator;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

$qiblaOutcome = QiblaCalculator::wgs84()->calculateWithMetadata(
    Coordinates::fromDegrees(3.1390, 101.6869),
);
$qiblaMetadata = $qiblaOutcome->metadata->toArray();
```

The result stays a `QiblaResult`; `heading_reference` is always `true_north`,
and `magnetic_declination_degrees` is always `null` until a sourced magnetic
model ships.

## Moon metadata

```php
use AlFalaq\AlFalaq\Moon\Services\CrescentCalculator;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

$moon = CrescentCalculator::default();
$coordinates = Coordinates::fromDegrees(3.1390, 101.6869);
$date = CivilDate::fromYmd(2025, 2, 28);

$moonOutcome = $moon->atSunsetWithMetadata($coordinates, $date);
$moonMetadata = $moonOutcome->metadata->toArray();

$moonEvaluation = $moon->evaluateWithMetadata($coordinates, $date);
$moonEvaluationMetadata = $moonEvaluation->metadata->toArray();
```

The geometry outcome carries ephemeris, conjunction-search, ΔT, and solar
sources. The evaluation outcome adds the four criterion sources and the
`crescent_criterion_is_not_observation` warning; a verdict is not a sighting.
```

Extend `tests/Wiring/CalculatorWiringTest.php`:

- import `Moon\ValueObjects\CrescentEvaluationOutcome`, `Moon\ValueObjects\CrescentGeometryOutcome`, `Qibla\ValueObjects\QiblaCalculationOutcome`;
- add `SECTION_EXPECTATIONS` entries:

```php
'Qibla metadata' => [
    'qiblaOutcome' => QiblaCalculationOutcome::class,
    'qiblaMetadata' => 'array',
],
'Moon metadata' => [
    'moonOutcome' => CrescentGeometryOutcome::class,
    'moonMetadata' => 'array',
    'moonEvaluation' => CrescentEvaluationOutcome::class,
    'moonEvaluationMetadata' => 'array',
],
```

The `'array'`/`'string'` scalar handling already exists from A1. Add assertions after the existing section pulls:

```php
$qiblaMetadataOutcome = $bySection['Qibla metadata']['qiblaOutcome'] ?? null;
self::assertInstanceOf(QiblaCalculationOutcome::class, $qiblaMetadataOutcome);
self::assertSame('qibla', $qiblaMetadataOutcome->metadata->common->context->value);

$moonMetadataOutcome = $bySection['Moon metadata']['moonOutcome'] ?? null;
self::assertInstanceOf(CrescentGeometryOutcome::class, $moonMetadataOutcome);
self::assertSame('moon', $moonMetadataOutcome->metadata->common->context->value);
```

Do not pin warning text or numeric values in the wiring test.

- [ ] **Step 5: Update the architecture and status docs**

- `ARCHITECTURE.md` §8 provenance subsection: extend with:
  - Qibla `solve()` and Moon `sunset()` single-path rules;
  - the Qibla true-north/null-declination policy;
  - the Moon source set and criterion warning;
  - the layering rule that `Moon\ValueObjects` does not import `Astro`, so calibrated ΔT bounds travel through `delta_t.range`.
- `AGENTS.md`: status says provenance A2 landed (prayer, timetable, Qibla, Moon) and Hijri metadata remains A3. Add a gotcha: `Moon\ValueObjects` must not import `Astro`; provider identity/range crosses the boundary through `CalculationSource`. Update the public-surface sentence if needed.
- `CONTEXT.md`: add A2 to the current-status section; do not claim Hijri metadata.
- `docs/README.md`: change the design-spec row to link both plans.

- [ ] **Step 6: Update the benchmark harness**

In `examples/bench.php`, keep every existing tripwire and legacy measurement unchanged. Add an A2 provenance line:

- Qibla: median of 101 `QiblaCalculator::default()->calculateWithMetadata($qiblaCoordinates)` runs;
- Moon geometry: median of 5 `CrescentCalculator::default()->atSunsetWithMetadata($moonCoordinates, $moonDate)` runs;
- Moon evaluation: median of 5 `evaluateWithMetadata(...)` runs;
- print one line labeled `A2 metadata (NOT gated)`.

Use the KL coordinates and `2025-02-28` so the Moon measurement includes the age search. Do not fold these into the legacy tripwires.

- [ ] **Step 7: Changelog and generated page**

Add under `CHANGELOG.md` → `[Unreleased]` → `### Added`:

- opt-in Qibla metadata (`QiblaCalculator::calculateWithMetadata()`, `QiblaCalculationOutcome`, `QiblaCalculationMetadata`) with true-north heading reference and a null magnetic-declination field;
- opt-in Moon metadata (`atSunsetWithMetadata()`, `evaluateWithMetadata()`, `evaluateGeometryWithMetadata()`, `MoonCalculationMetadata`, `CrescentGeometryOutcome`, `CrescentEvaluationOutcome`) with ΔT, no-moonset, and criterion-observation warnings;
- `QiblaResult::toArray()`, `CrescentGeometry::toArray()`, `CrescentVerdict::toArray()`;
- built-in Qibla/Moon source identities (`qibla.spherical`, `qibla.wgs84-karney`, `moon.ephemeris.meeus-2ed`, `moon.conjunction-search.meeus-hourly`, `moon.delta-t.espenak-2005-2050`, the four `moon.criterion.*` ids);
- `SharedKernel\Traits\ResolvesCalculationSources`;
- docs updates for Qibla/Moon metadata.

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

Expected: 0 lint errors, 0 CS findings, 0 PHPStan errors, coverage ≥ 95%, all tests green, 32 arch tests green.

- [ ] **Step 9: Run the mutation and performance gates**

```bash
COMPOSER_PROCESS_TIMEOUT=0 composer mutation
composer bench
```

- Mutation: MSI ≥ 85. If a new mutant escapes, add the missing assertion; do not lower the gate and do not add a baseline entry.
- Benchmark: legacy tripwires pass; record the legacy, A1 metadata, and A2 metadata medians from the output.

- [ ] **Step 10: Build the docs site**

```bash
cd docs_site
npm run build
cd ..
```

Expected: the updated metadata page renders, the sidebar entry resolves, and no version snapshot is modified. If the build needs dependencies, run `npm ci` first; do not commit `node_modules/`.

- [ ] **Step 11: Verify the frozen surface**

```bash
git diff --stat main...HEAD
git status --short
```

Check:

- no changes under `docs/user/v*/`;
- no changes to `src/Qibla/Contracts/GeodesyCalculator.php`, `src/Moon/Contracts/ConjunctionSearch.php`, `src/Moon/Contracts/CrescentEvaluator.php`, `src/Qibla/ValueObjects/QiblaResult.php`'s properties/constructor, `src/Moon/ValueObjects/CrescentGeometry.php`'s properties/constructor, or `src/Moon/ValueObjects/CrescentVerdict.php`'s properties/constructor;
- no new runtime `require` in `composer.json`;
- no `DateTimeInterface` unions, no framework imports, no network/clock reads;
- no `Astro` import under `src/Moon/ValueObjects/` or `src/Moon/Contracts/`.

- [ ] **Step 12: Commit**

```bash
git add docs ARCHITECTURE.md AGENTS.md CONTEXT.md CHANGELOG.md examples/bench.php tests/Wiring/CalculatorWiringTest.php
git commit -m "docs: document Qibla and Moon calculation metadata"
```

- [ ] **Step 13: Push and open the PR**

```bash
git push -u origin feat/provenance-a2-qibla-moon
gh pr create --base main --head feat/provenance-a2-qibla-moon \
  --title "feat: add Qibla and Moon calculation provenance (A2)" \
  --body-file /tmp/opencode/pr-a2-body.md
```

The PR body records: the A2 scope, the legacy-compatibility grid, the exact source ids, the gate evidence (tests/coverage/arch/mutation/bench/docs build), and the ΔT fractional-boundary decision. Watch `gh pr checks <n>` until green; do not merge without the user's instruction.

---

## Final acceptance checklist

- [ ] `composer validate --strict`, `composer ci`, `composer arch`, `composer changelog:check` green.
- [ ] `composer test` reports line coverage ≥ 95% (never lowered).
- [ ] `composer mutation` reports MSI ≥ 85.
- [ ] `composer bench` stays within 50 ms single-day / 30,000 ms year; legacy, A1 metadata, and A2 metadata medians recorded.
- [ ] 747 baseline tests plus the new provenance/metadata tests all pass; assertion count only grows.
- [ ] Qibla and Moon legacy results are byte-for-byte equal to their metadata outcome results across the compatibility grids.
- [ ] Every new public class-like appears in `docs/user/api-reference.md`; the reverse gate finds no stale entry.
- [ ] `docs/README.md` links the A2 plan; `docs/provenance.md` and the published guide describe A1+A2, not A3.
- [ ] `docs_site` builds with the updated page; no version snapshot changed.
- [ ] No A3 types, methods, or claims were introduced.
- [ ] `Moon\ValueObjects` and `Moon\Contracts` remain free of `Astro` imports.
- [ ] `AGENTS.md` and `CONTEXT.md` describe A1+A2 only, not the whole provenance phase.

## Rollback

Every task is one commit. If a gate fails late, revert the offending commit rather than weakening a gate:

```bash
git revert <commit>
```

Tasks 1–2 are shared/Qibla foundations with no dependency on Moon; Task 3 can be reverted independently; Tasks 4–7 stack Moon identity → value objects → sunset → evaluation and must be reverted newest-first; Task 8 is documentation/release-note work except for the benchmark and wiring test.
