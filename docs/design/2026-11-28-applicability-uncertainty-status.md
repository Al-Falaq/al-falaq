# Applicability, Uncertainty and Status — Design Spec

- **Status:** Proposed for review. This document freezes the public vocabulary and
  compatibility shape before implementation; it is not yet an implementation
  plan.
- **Date:** 2026-11-28.
- **Source:** `docs/artifacts/2026-11-11-project-improvement-report.md`, P0.2
  (applicability, uncertainty and status), decisions 3 and 6, and the A3 plan's
  explicit hand-off (`docs/plans/2026-11-24-provenance-a3-hijri.md` §After A3:
  "A1–A3 can ship as `0.3.0`, and P0.2 can become `0.4.0`").
- **Builds on:** `docs/design/2026-11-15-provenance-explainability-foundation.md`
  (A1–A3, shipped as `v0.3.0`).
- **Release target:** completion of Release A — Trust foundation, as `0.4.0`.
- **First executable unit:** B1 — shared applicability vocabulary and the
  schema `1.1` envelope. B2 adds per-context facts; B3 publishes the matrix and
  prepares the release.

## 1. Summary

A1–A3 made every public result auditable: requested and effective inputs,
policy snapshot, stable source ids, warning codes, and a derived status. What
they deliberately left for P0.2 is the *applicability* half of the report's
Release A exit gate: published, per-model validity ranges, extrapolation state,
assumptions, and the distinction between a computed answer and a decision that
requires local observation or an authority announcement.

This slice adds a single optional `applicability` block to the shared
`CalculationMetadata` envelope. It does **not** change status derivation, add
or repurpose warning codes, change any numeric output, or add a second
serializer. The block is built from the same service traces that already feed
the metadata siblings, so it cannot drift from the calculation it describes.

The envelope schema moves from `1.0` to `1.1` by one additive key; the package
moves from `0.3.0` to `0.4.0` as a minor release. No existing method signature,
return type, result object, warning code or status meaning changes.

## 2. Why this leg is next

The report's Release A exit gate is explicit:

> every existing preset and calendar has provenance, applicability status, and
> reproducible serialized inputs; no existing output changes without an
> explicit golden and changelog entry.

A1–A3 delivered provenance and the status taxonomy, and the `v0.3.0` release
was cut on that basis. The delivery audit
(`docs/artifacts/2026-11-28-provenance-a1-a3-delivery-audit.md`) classified the
remaining gap precisely: full per-context applicability intervals and the
per-model matrix are still outstanding, so Release A is not complete by its own
definition. P0.2 is the only workstream that closes that gap without waiting
for the much larger P1/P2/P3 programs.

Doing P0.2 before P1.2 (civil-time context) and P1.6 (JSON Schema) matters:
those later slices will consume the applicability facts, and inventing their
own range/assumption vocabulary would multiply the exact ambiguity this
programme exists to remove.

## 3. Scope

### In scope

- A public, immutable applicability vocabulary in `SharedKernel`:
  `ApplicabilityState`, `DecisionKind`, `ModelApplicability`, `Applicability`.
- One additive key on `CalculationMetadata`: `applicability`, serialized as
  `null` when no service supplies it. Schema bump `1.0` → `1.1`.
- Per-context applicability facts built from the existing private traces:
  - Prayer: solar model span, refraction/elevation/horizon assumptions, polar
    fallback, rounding;
  - Qibla: geodesy model, true-north reference, explicit absence of a magnetic
    model;
  - Moon: ephemeris, conjunction search, ΔT calibrated span with the checked
    decimal year, and the criterion-versus-observation decision;
  - Hijri: calendar family/coverage/span from `CalendarProvenance`, offset
    store, sunset-frame probe, and the computed-table-versus-announcement
    decision.
- A published per-context applicability matrix: supported/validated range,
  calculable-but-extrapolated range, unavailable range, assumptions, and two
  legitimate policies where the report requires them.
- Tests pinning the new wire shape, state transitions, determinism, and legacy
  equality; no numeric golden is re-pinned.
- Documentation: contributor appendix, published user page, API reference,
  migration note, changelog; release preparation for `0.4.0`.

### Out of scope

- Changing any existing method signature, return type, exception, numeric
  output, warning code, or status meaning.
- New warning codes or changes to `CalculationMetadata::deriveStatus()`.
  P0.2 is expressed through the applicability block and documentation, not by
  repurposing the frozen eight-code warning table.
- Turning a hard failure into a soft warning. `HijriOutOfRangeException`,
  `ValidationException` and `UndefinedScheduleException` keep their conditions.
- Widening `CivilDate`'s 1900–2100 range or any shipped calendar table span.
- JSON Schema files and cross-language contract tests (P1.6).
- Civil-date/time-zone and DST gap/fold policy (P1.2), locale formatting
  (P1.1), profile registry (P1.4), facade (P1.5), magnetic model (P2.3),
  configurable atmospheric models (P2.1), observation reports (P2.2),
  caches and batch APIs (P2.5).
- Official calendar datasets (P1.3) and the independent reference-validation
  ledger (P0.3).

## 4. Current seams

| Area | Delivered in A1–A3 | P0.2 gap |
|---|---|---|
| `CalculationMetadata` | Six-key envelope, status derived from eight warning codes, deterministic sort/de-dup, schema `1.0` | No per-model range, assumption or decision facts |
| Prayer | `calculateWithMetadata()`, `monthlyWithMetadata()`, polar fallback in warning details and `effective_input`; `CivilDate` 1900–2100 | No published refraction/elevation/horizon assumptions; no applicability block |
| Qibla | `heading_reference = true_north`, `magnetic_declination_degrees = null`, one geodesy source | No statement of geodetic model limits or the absence of a magnetic model |
| Moon | ΔT origin/range and `delta_t_extrapolated`, `moonset_indeterminate`, criterion warning and `decision_required` | No ephemeris/conjunction-search span, refraction assumption, or explicit decision kind |
| Hijri | `CalendarProvenance` key/family/coverage/authority/range/digest/source/license, maghrib fallback, offset source | No computed-table-versus-authority-announcement decision; no summary applicability block |
| Docs | `docs/provenance.md`, `docs/user/calculation-metadata.md`, warning table, source registry | No per-context validity matrix or two-policy examples |

Relevant existing contracts and constants (verified on the current tree):

- `CalculationMetadata::SCHEMA_VERSION = '1.0'` and `create(context, warnings,
  sources, ?libraryVersion)`; status is derived only from `WarningCode` cases.
- `CivilDate::MIN_YEAR = 1900`, `MAX_YEAR = 2100`; out-of-range input throws.
- `DeltaTProvider::MODEL_ID = 'moon.delta-t.espenak-2005-2050'`,
  `MODEL_RANGE = '2005..2050'`, `VALID_FROM_YEAR = 2005.0`,
  `VALID_TO_YEAR = 2050.0`. The provider itself is internal; its identity
  crosses the boundary through `CalculationSource`.
- `CalendarProvenance::toArray()` already carries span, digest, authority,
  source and license for every shipped calendar; `OFFICIAL_TABLE` is unused.
- `WarningCode` has eight cases; none is repurposed by this slice.

## 5. Fixed constraints

These constraints come from the improvement report, the provenance spec §5,
`AGENTS.md` and `ARCHITECTURE.md`. They are not open for revision in this slice.

1. Existing results and method signatures stay backward compatible. Adding a
   constructor parameter with a default at the end is allowed; changing an
   existing parameter is not.
2. Applicability facts come from the same calculation run. A sibling method
   must not recompute astronomy, re-read a clock, or parse exception strings.
3. Public metadata must not expose `Astro/` objects, internal engine classes,
   `DateTimeInterface` unions, or class names. Model identity is a stable string.
4. The core remains offline and dependency-free. No Composer runtime
   dependency, no network, no clock reads, no I/O.
5. Value objects stay `final readonly` and validate at construction. Public
   classes and methods need docblocks that satisfy `ArchDocsTest`.
6. `Contracts/` stays interfaces-only; service contracts keep the `Interface`
   suffix; driven ports keep short names.
7. Status remains derived from warnings by `CalculationMetadata::create()`.
   Applicability state and decision kind are separate facts and never override
   status.
8. Warning codes are stable machine identifiers; no case is added, removed or
   repurposed in this slice.
9. Serialized schema versions are independent of package versions. The new key
   is additive (`1.0` → `1.1`); consumers must ignore unknown keys. Removing or
   renaming a key requires a major schema bump.
10. `Astro/` remains `@internal`; the `SolarDayEngine` + `MeanSolarCalculator`
    wiring exception is unchanged.

## 6. Public API design

### 6.1 Namespaces and naming

| Type | Namespace |
|---|---|
| `ApplicabilityState`, `DecisionKind` | `AlFalaq\AlFalaq\SharedKernel\Enums` |
| `ModelApplicability`, `Applicability` | `AlFalaq\AlFalaq\SharedKernel\ValueObjects` |

No new context-level metadata or outcome class is created. The applicability
block lives on the shared envelope so all four contexts serialize it in one
place.

Naming rules continue from the provenance spec: `snake_case` wire keys, enum
string values, nullable facts present with `null`, empty lists present as `[]`,
numeric keys carry units (`_degrees`, `_years`, `_minutes`, `_m`).

### 6.2 Shared enums

```php
enum ApplicabilityState: string
{
    case SUPPORTED = 'supported';
    case EXTRAPOLATED = 'extrapolated';
    case UNAVAILABLE = 'unavailable';
}
```

The three states implement report decision 6:

| State | Meaning | Outcome |
|---|---|---|
| `SUPPORTED` | Inside the documented and validated range | Result returned, not qualified by an extrapolation warning |
| `EXTRAPOLATED` | Deterministic computation exists outside the calibrated range | Result returned with the appropriate warning and status; never described as validated |
| `UNAVAILABLE` | No defined computation | Typed exception, no outcome and no applicability block |

`UNAVAILABLE` is published in the contributor matrix and is available to value
objects, but a successful outcome never contains it: the service throws before
building metadata.

```php
enum DecisionKind: string
{
    case NONE = 'none';
    case OBSERVATION = 'observation';
    case AUTHORITY = 'authority';
}
```

- `NONE`: the numeric answer is the whole answer (prayer times, Qibla bearing).
- `OBSERVATION`: a Moon criterion score is a decision aid; a local sighting or
  an authority announcement is required before calling it a sighting.
- `AUTHORITY`: a computed Hijri date is an estimate; an authority announcement
  can legitimately differ.

`DecisionKind` does **not** derive or override `CalculationStatus`. Moon
evaluation already sets `decision_required` through
`crescent_criterion_is_not_observation`; Hijri stays `complete` despite an
`AUTHORITY` decision because a generated calendar is a usable result, not a
failure.

### 6.3 Shared value objects

```php
final readonly class ModelApplicability
{
    public function __construct(
        public string $id,
        public ApplicabilityState $state,
        public string $range,
        public ?float $checkedValue = null,
        public ?string $unit = null,
        public ?string $detail = null,
    ) {}

    public function toArray(): array;
}
```

Validation:

- `id` matches `^[a-z0-9][a-z0-9._-]*$` and is either a registered
  `CalculationSource` id or a calendar key documented in `CalendarProvenance`.
  It is never a PHP class name.
- `range` is non-empty after trimming and is a human-readable span such as
  `1900-01-01..2100-12-31 CE`, `2005..2050` or `1423-01..1500-12 AH`.
- `checkedValue` is finite when present; `unit` is one of the documented
  unit strings (`decimal_year`, `degree`, `date`) when present; `detail` is a
  non-empty explanatory string when present and is not an identifier.

`toArray()` returns the six keys in constructor order:
`id`, `state`, `range`, `checked_value`, `unit`, `detail`.

```php
final readonly class Applicability
{
    /**
     * @param list<ModelApplicability> $models
     * @param array<string, int|float|string|bool|null> $assumptions
     */
    public function __construct(
        public ApplicabilityState $state,
        public array $models,
        public array $assumptions,
        public DecisionKind $decision,
    ) {}

    public function toArray(): array;
}
```

- `models` is a validated list; duplicates of `(id, state, range)` collapse in
  insertion order so a repeated model is one fact.
- `assumptions` keys are non-empty strings and values are finite scalars or
  `null`. Keys are stable snake_case names published in §6.5; consumers must
  ignore unknown keys.
- `state` is the highest-ranked state of `models` (`SUPPORTED` <
  `EXTRAPOLATED` < `UNAVAILABLE`), supplied by the constructing service from
  the same facts. It is not recomputed from warnings.
- `toArray()` returns `state`, `models`, `assumptions`, `decision` in that order.

### 6.4 Integration with `CalculationMetadata`

```php
final readonly class CalculationMetadata
{
    public const SCHEMA_VERSION = '1.1';

    /** @param list<CalculationWarning> $warnings */
    /** @param list<CalculationSource> $sources */
    public static function create(
        CalculationContext $context,
        array $warnings = [],
        array $sources = [],
        ?LibraryVersion $libraryVersion = null,
        ?Applicability $applicability = null,
    ): self;

    public function toArray(): array;
}
```

- The private constructor gains `public ?Applicability $applicability = null`
  as its last property.
- `create()` keeps the existing warning/source sorting, de-duplication and
  status derivation byte-for-byte. The new parameter only populates the new
  block.
- `toArray()` keeps the six existing keys in the same order and appends the
  seventh key: `'applicability' => $this->applicability?->toArray()`. When no
  service supplies facts it is `null`, never omitted.
- Existing tests that assert the six-key shape are updated to expect seven
  keys with `applicability: null`; that is the only wire change.
- `LibraryVersion::CURRENT` becomes `0.4.0-dev` during implementation and
  `0.4.0` at the release tag, following the established release process. The
  runtime version is still a checked-in constant with no Composer metadata read.

### 6.5 Per-context facts built from the existing traces

All facts come from values the service already holds in its private trace. No
context recomputes astronomy and no context imports `Astro` or another
context's value objects beyond the stable `Prayer\ValueObjects\CalculationParameters`
exception already documented in `AGENTS.md`.

#### Prayer and timetable

- Model id `solar.mean-meeus`, state `SUPPORTED`, range
  `1900-01-01..2100-12-31 CE` (from `CivilDate::MIN_YEAR`/`MAX_YEAR`).
- Assumptions: `refraction_model = 'standard'`, `pressure_hpa = 1010`,
  `temperature_c = 10`, `elevation_m` (from `CalculationParameters`),
  `rounding = PrayerCalculationMetadata::ROUNDING`.
- When a polar handler ran, add assumption `polar_fallback_rule` with the
  configured `PolarCircleRule` value; the effective input is still recorded in
  `effective_input`.
- `decision = NONE`. A timetable carries the same block per day; only
  `polar_fallback_rule` may differ by day.

#### Qibla

- One model: `qibla.spherical` or `qibla.wgs84-karney` (from the injected
  strategy's `IdentifiesCalculationSource` id), state `SUPPORTED`, range
  `global` with `detail` noting that near-antipodal diagnostics are P2.3.
- Assumptions: `heading_reference = 'true_north'`,
  `magnetic_declination_degrees = null`.
- `decision = NONE`. The block does not claim a magnetic model.

#### Moon

- Models:
  - `moon.ephemeris.meeus-2ed`, state `SUPPORTED`, range as published in
    `docs/moon.md` (Meeus 2ed solar/lunar theory over the supported `CivilDate`
    span);
  - `moon.conjunction-search.meeus-hourly`, state `SUPPORTED`, same span;
  - `moon.delta-t.espenak-2005-2050`, range `2005..2050`, `checked_value` the
    same decimal year used by the provider, `unit = 'decimal_year'`, state
    `SUPPORTED` at or inside the interval and `EXTRAPOLATED` outside it. A
    caller override uses `caller.delta-t` with state `SUPPORTED` and no
    calibrated range in the warning.
- Assumptions: `refraction_model = 'standard'`, `delta_t_origin` from the
  existing metadata (`provider` or `override`), `geometry_source` from the
  existing metadata.
- `decision = OBSERVATION` for evaluation outcomes, `NONE` for geometry-only
  outcomes. The existing `crescent_criterion_is_not_observation` warning is
  unchanged.

#### Hijri

- One model keyed by `CalendarProvenance::key` (`tabular`, `uqu`, or
  `mabims`), state `SUPPORTED`, range from `CalendarProvenance::range`, with
  `detail` naming the family and authority when present. A custom calendar
  uses its own key and its documented span.
- Assumptions: `frame` (`civil_date` or `sunset_rollover`),
  `offset_store` (`null` when none supplied, `caller` otherwise), and
  `coverage` from `CalendarProvenance`.
- `decision = AUTHORITY`: a computed table is an estimate and an authority
  announcement can differ. `OFFICIAL_TABLE` remains unused by shipped
  calendars; if a future dataset claims it, the decision kind stays
  `AUTHORITY` because an announced month is still an authority fact.
- The calendar provenance itself remains in the `calendar` block, never as a
  `CalculationSource`, and is not duplicated into applicability.

### 6.6 Warning codes and status

No new `WarningCode` case is introduced. The eight codes from A1–A3 keep their
names, detail keys and status effects. P0.2 makes applicability visible without
inventing a parallel warning vocabulary; the status transition table already
frozen in the provenance spec §7.3 remains the single source of truth:

- normal result → `complete`;
- polar fallback or maghrib-only probe → `fallback_applied`;
- provider ΔT outside 2005–2050 → `extrapolated`;
- no-moonset geometry → `indeterminate` (ranked above `extrapolated`);
- Moon evaluation → `decision_required` (ranked above both).

Applicability `state = EXTRAPOLATED` must be accompanied by the applicable
extrapolation warning on the same outcome; services satisfy this by deriving
both from the same checked value. Applicability `decision` imposes no status
requirement.

## 7. Serialization contract

1. `toArray()` stays canonical; no separate serializer is added.
2. The common envelope gains exactly one key, `applicability`, after
   `sources`. Key order for the six existing keys is unchanged.
3. Enums serialize to string values; no PHP object, `Astro` namespace or class
   name enters the wire shape.
4. Nullable facts are present with `null`; `models` and `assumptions` are
   present with `[]` when empty.
5. `schema_version` becomes `"1.1"` for every metadata object in this release.
   Consumers must ignore unknown keys.
6. Context-specific blocks stay nested under `metadata`; the outcome wrapper
   stays `['result' => ..., 'metadata' => ...]`.
7. No metadata includes a calculation timestamp, random value, host name or
   clock read.

Illustrative envelope:

```json
{
  "schema_version": "1.1",
  "context": "moon",
  "status": "decision_required",
  "library_version": "0.4.0-dev",
  "warnings": [],
  "sources": [],
  "applicability": {
    "state": "supported",
    "models": [
      {"id": "moon.delta-t.espenak-2005-2050", "state": "supported", "range": "2005..2050", "checked_value": 2026.9, "unit": "decimal_year", "detail": null}
    ],
    "assumptions": {"refraction_model": "standard", "delta_t_origin": "provider", "geometry_source": "calculated"},
    "decision": "observation"
  }
}
```

## 8. Source identity

The A1–A3 source registry is unchanged. `ModelApplicability::$id` reuses a
registered source id or a `CalendarProvenance` key; no second registry is
created. Assumption keys are not source ids and are documented in §6.5 only.
The core's generated tables continue to report their identity through
`CalendarProvenance` and its SHA-256 constants, not through a second
`CalculationSource`.

The contributor matrix must state that `UNAVAILABLE` is a documentation state
for rejected inputs: a rejected call raises its typed exception and produces no
outcome, so no applicability block is serialized for it.

## 9. Internal trace and single-path rules

The applicability block is built from the same private routine as the existing
metadata sibling in each service:

- `PrayerTimesCalculator::run()` already returns requested/effective values and
  `nightMode`; the applicability block is assembled from those values and the
  `CalculationParameters` snapshot. `calculate()` keeps ignoring metadata.
- `QiblaCalculator::solve()` already returns the `QiblaResult`; the injected
  geodesy strategy supplies the source id.
- `CrescentCalculator::sunset()` already returns geometry, ΔT seconds/source
  and sources; the decimal year checked there is reused for `checked_value`.
- `GregorianToHijriConverter::trace()` already returns the resolved civil date,
  offset days and fallback flag; `calendarProvenance()` is read once by the
  metadata path and its facts feed the block.

No service calls a second calculator, re-derives a range, or reads a clock to
populate applicability. The compact methods remain provenance- and
applicability-free.

## 10. Compatibility and release policy

| Change | Classification | Compatibility note |
|---|---|---|
| New `SharedKernel` enums/value objects | Minor | Additive; no existing type changes |
| New `applicability` key on `CalculationMetadata` | Minor schema bump `1.0` → `1.1` | Additive; consumers ignore unknown keys |
| New optional trailing constructor parameters | Minor | Existing positional and named calls remain valid |
| New contributor matrix and published page | Documentation only | No wire change |
| `LibraryVersion::CURRENT` bump | Minor | `0.4.0-dev` during work, `0.4.0` at tag |

No public API is removed, deprecated or repurposed. Numeric outputs, exception
conditions and `PrayerSchedule::toArray()` are unchanged. The release process
follows `v0.3.0`: version bump, exact dated CHANGELOG header, regenerated
published changelog, plugin-generated docs snapshot, switcher entry, Windows
gate, then tag.

## 11. Testing and evidence

### 11.1 Baseline (v0.3.0)

- 932 tests, 51,694 assertions (`composer test-unit`);
- 32 architecture tests, 474 assertions;
- coverage 98.9% vs 95% floor;
- MSI 93% vs 85 gate;
- benchmark 3.719 ms single-day / 1256 ms year vs 50 ms / 30,000 ms tripwires.

New code must not lower the coverage floor or weaken an architecture gate.

### 11.2 Test matrix

| Area | Required tests |
|---|---|
| Shared vocabulary | Enum values, `ModelApplicability` id/range/unit/detail validation, `Applicability` list de-dup and scalar assumption validation, six/four-key wire order |
| Envelope | Schema `1.1`, seven-key order, `applicability: null` default, status derivation unchanged for all eight warning codes, warning/source sort and de-dup unchanged |
| Prayer | Solar range, refraction/elevation assumptions, per-day polar applicability in timetable, legacy equality |
| Qibla | Geodesy id, true-north assumptions, no magnetic claim, legacy equality |
| Moon | ΔT checked decimal year, `SUPPORTED` at 2050-01-01 and `EXTRAPOLATED` at 2050-01-02, override state, decision `OBSERVATION` for evaluation and `NONE` for geometry, legacy equality |
| Hijri | Calendar key/range/coverage from provenance, `frame` and `offset_store` assumptions, decision `AUTHORITY`, custom calendar key, legacy equality |
| Serialization | `json_encode(..., JSON_THROW_ON_ERROR)`, no objects, no `Astro\\` strings, nullable keys present, empty lists present |
| Status transitions | Existing five transitions pinned; applicability `EXTRAPOLATED` accompanies the extrapolation warning; `decision` never changes status |
| Architecture | New types in `docs/user/api-reference.md`, docblocks complete, layering clean, final/readonly, contracts interfaces-only |
| Wiring | New `docs/integrations.md` applicability snippet executes verbatim |
| Performance | Metadata median and peak memory recorded; legacy tripwires still pass |

### 11.3 Snapshot policy

Pin exact keys, order, nullability, enum values and source ids. Do not pin human
`detail` prose as an identifier; assert it is non-empty. Assert
`library_version` equals `LibraryVersion::CURRENT`, never a dev literal.
Numeric astronomy goldens remain untouched; applicability tests are shape and
behavior tests, not new parity vectors.

### 11.4 Commands

Narrow checks first:

```bash
vendor/bin/phpunit --no-coverage --filter Applicability
vendor/bin/phpunit --no-coverage --filter CalculationMetadata
vendor/bin/phpunit --group arch
```

Then the repository gates:

```bash
composer lint
composer cs
composer stan
composer test-unit
composer test
composer arch
composer changelog:check
composer ci
composer mutation
composer bench
```

## 12. Documentation and release work

### 12.1 Contributor documentation

- **Create `docs/applicability.md`** — canonical contributor matrix:
  - the three states and which inputs produce them;
  - per-context ranges, calibrated intervals and assumptions from §6.5;
  - the three decision kinds with two legitimate-policy examples;
  - why `UNAVAILABLE` means a typed exception and no outcome;
  - one reproducibility recipe using applicability + metadata + result.
- Add the page to `docs/README.md` and its counterpart column.
- **Update `docs/provenance.md`** with a pointer to the matrix and the new
  seventh envelope key; do not duplicate the matrix there.
- **Update `ARCHITECTURE.md`** — applicability is a shared-envelope fact built
  at orchestration boundaries; `Astro` stays internal.
- **Update `docs/integrations.md`** with an executable applicability recipe;
  `tests/Wiring/CalculatorWiringTest.php` runs every fenced PHP block.
- **Update `docs/prayer.md`, `docs/qibla.md`, `docs/moon.md`, `docs/hijri.md`**
  with their applicability facts and the two-policy examples where relevant.
- **Update `AGENTS.md` and `CONTEXT.md`** when the leg lands.

### 12.2 User documentation

- **Create `docs/user/applicability.md`** — published guide: what the block
  means, how to read `state`/`decision`, the per-context matrix in user terms,
  and examples where two authorities legitimately differ.
- Add the page to `docs_site/astro.config.mjs` and the docs map counterpart.
- **Update `docs/user/api-reference.md`** with every new class, enum and the
  new envelope key; `ArchApiReferenceTest` gates both directions.
- **Update `docs/user/calculation-metadata.md`** with the seventh key and a
  pointer to the applicability guide; do not duplicate the matrix.
- **Update `docs/user/migration.md`** with a schema `1.1` additive note.
- **Update `docs/user/citations.md`** if the matrix introduces new named
  references; no new source ids are expected.
- Bump `CHANGELOG.md` and run `composer changelog`; never hand-edit the
  generated page.

### 12.3 Release work

Prepare `0.4.0` only after the leg is green: bump `LibraryVersion::CURRENT` to
the exact version, close the dated CHANGELOG header, regenerate the published
changelog, freeze the `v0.4.0` docs snapshot via the versioning plugin,
register the switcher entry, then tag and let `pre-release.yml` run the Windows
gate before publishing. Do not snapshot or tag in an implementation PR.

## 13. Implementation sequence

Three independently reviewable units. Each leaves `composer ci` green and does
not change legacy numeric output.

### B1 — Shared vocabulary and envelope

**Goal.** Land the applicability vocabulary and the schema `1.1` envelope.

**Creates**

- `src/SharedKernel/Enums/ApplicabilityState.php`
- `src/SharedKernel/Enums/DecisionKind.php`
- `src/SharedKernel/ValueObjects/ModelApplicability.php`
- `src/SharedKernel/ValueObjects/Applicability.php`

**Modifies**

- `src/SharedKernel/ValueObjects/CalculationMetadata.php` — schema `1.1`,
  optional trailing parameter and property, seventh key.
- `docs/user/api-reference.md`, `docs/provenance.md`, `CHANGELOG.md`,
  `docs/user/changelog.md` (generated).
- Tests under `tests/SharedKernel/Provenance/`.

**Exit gate.** Shared applicability types are validated and pinned; the
envelope still derives status identically for all eight warning codes; legacy
wire consumers see one additive key with `null`.

### B2 — Per-context facts

**Goal.** Populate applicability from each existing private trace.

**Modifies**

- `src/Prayer/Services/PrayerTimesCalculator.php`, `TimetableService.php`;
- `src/Qibla/Services/QiblaCalculator.php`;
- `src/Moon/Services/CrescentCalculator.php`;
- `src/Hijri/Services/GregorianToHijriConverter.php`;
- context metadata tests, wiring test, benchmark line.

**Exit gate.** Every metadata path emits the correct applicability block;
compact paths remain unchanged; no service imports `Astro` or recomputes
astronomy.

### B3 — Matrix, docs and release preparation

**Goal.** Publish the applicability matrix and prepare `0.4.0`.

**Creates/modifies**

- `docs/applicability.md` (new), `docs/user/applicability.md` (new);
- `docs_site/astro.config.mjs`, `docs/README.md`, `ARCHITECTURE.md`,
  `AGENTS.md`, `CONTEXT.md`;
- context appendices, user metadata guide, migration and citations pages;
- `CHANGELOG.md` and generated changelog;
- `examples/bench.php` applicability line if the metadata median moves.

**Exit gate.** Docs build, API-reference gate, changelog check, full CI matrix,
mutation and benchmark all green; release prep follows §12.3.

### Why not one big-bang unit

The envelope is the risky part: if the seventh key or its nullability is wrong,
every context is wrong. B1 proves it with no context changes. B2 then adds
context facts without reopening the common shape, and B3 freezes the matrix
before a release consumes it.

## 14. Acceptance criteria mapping

| Report requirement (P0.2) | Where this spec satisfies it | Unit |
|---|---|---|
| Publish supported, calibrated and extrapolated ranges | `ModelApplicability` state/range and the contributor matrix (§6.3, §12.1) | B1/B3 |
| Whether numeric output is returned, returned with status, or rejected | Three-state table (§6.2) and unchanged exception conditions (§5.7) | B1/B3 |
| Assumptions for refraction, pressure, temperature, elevation, horizon and ΔT | Per-context `assumptions` maps (§6.5) and matrix | B2/B3 |
| Meaning of polar fallback and original/effective inputs | Prayer assumptions plus the existing `effective_input` block | B2 |
| Criterion vs probability vs observation vs authority announcement | `DecisionKind` and the unchanged criterion warning (§6.2, §6.6) | B1/B3 |
| Table-backed output vs astronomical estimate | Hijri `decision = AUTHORITY` and `CalendarProvenance` coverage (§6.5) | B2/B3 |
| Deterministic status codes pinned at boundaries | Existing five transitions plus applicability/extrapolation coherence (§6.6, §11.2) | B1/B2 |
| Examples where two legitimate policies differ | Contributor matrix and published guide (§12) | B3 |
| Do not widen `CivilDate` or table ranges | Out of scope (§3); constraints (§5.9) | All |
| Release A exit gate: provenance + applicability status + reproducible inputs, no unreviewed output change | A1–A3 provenance plus this block; goldens untouched, changelog entries | B3 |

## 15. Risks and trade-offs

| Risk | Mitigation |
|---|---|
| A second truth alongside status | Status stays derived only from warnings; applicability state and decision are separate facts with explicit coherence rules |
| Applicability drifts from the calculation | Built from the same private traces; no recomputation, no exception parsing |
| Consumers treat `decision = AUTHORITY` as a failure | Document that Hijri stays `complete` and that an authority announcement can legitimately differ |
| Schema `1.1` breaks strict consumers | One additive key with `null` default; migration note states consumers must ignore unknown keys |
| Matrix becomes marketing or false precision | State exact ranges from checked-in constants; mark P1/P2 gaps as deferred, not solved |
| New warning codes would repurpose the frozen vocabulary | No new codes in this slice; applicability carries the facts |
| Moon range claims exceed the model | Use only ranges already published in `docs/moon.md`; no new validity claim |
| Release slips back into "Release A complete" ambiguity | Call `0.4.0` the completion of Release A and record what remains deferred (P0.3 etc.) in release notes |

## 16. Deferred decisions

Intentionally not decided here; none blocks B1.

- Independent reference-validation ledger (P0.3) — separate workstream.
- Generated public API inventory and full SemVer/deprecation policy page (P2.6).
- JSON Schema documents and cross-language tests (P1.6).
- Locale and civil-time/DST context (P1.1/P1.2).
- Official calendar datasets and calendar manifest/adapter (P1.3).
- Profile registry and convenience facade (P1.4/P1.5).
- Magnetic declination provider and near-antipodal diagnostics (P2.3).
- Configurable refraction/pressure/temperature models (P2.1).
- Observation reports and rich Moon series (P2.2).
- Caches, batch APIs and partial-failure policy (P2.5).
- Whether `Astro` symbols can be promoted to public API (P2.6).

## 17. Next step

Write the B1 implementation plan from this spec using the repository's
`docs/plans/` convention. It must start with failing tests for the four new
shared types and the seven-key envelope, then the minimal implementation, then
the docs/API/changelog updates, and finish with `composer ci`, `composer arch`,
`composer mutation` and `composer bench` evidence. B2 and B3 plans follow after
B1 proves the shape.

<!-- END -->
