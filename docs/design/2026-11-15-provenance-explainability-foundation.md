# Provenance and Explainability Foundation — Design Spec

- **Status:** Proposed for review. This document freezes the public vocabulary and
  compatibility shape before implementation; it is not yet an implementation
  plan.
- **Date:** 2026-11-15.
- **Source:** `docs/artifacts/2026-11-11-project-improvement-report.md`, P0.1
  (provenance and explainability), the six decisions in that report, and the
  suggested first implementation slice.
- **Release target:** Release A — Trust foundation.
- **First executable unit:** A1 — shared vocabulary plus prayer/timetable. A2
  (Qibla and Moon) and A3 (Hijri) reuse the vocabulary after A1 proves it.

## 1. Summary

Add an opt-in provenance path beside every existing calculation method. The
existing methods and return types stay unchanged. The new path returns the same
domain result plus a typed, immutable metadata envelope that can be serialized
to a documented JSON shape.

The shared vocabulary lives in `SharedKernel`:

- `CalculationContext` — which public context produced the result;
- `CalculationStatus` — the aggregate outcome state;
- `WarningCode` — stable machine codes for assumptions and limitations;
- `CalculationWarning` — a code, a human-readable explanation, and scalar
  details;
- `SourceKind` — algorithm, generated data, official data, or caller input;
- `CalculationSource` — a stable source identifier plus optional version,
  digest, and range;
- `CalculationMetadata` — schema version, context, status, library version,
  ordered warnings, and ordered sources;
- `LibraryVersion` — the checked-in runtime version string;
- `IdentifiesCalculationSource` — the opt-in capability for injected
  strategies that can describe themselves.

Each context adds a typed metadata object and an outcome envelope. The
envelope is the only new return type; it never replaces an existing result.

The design deliberately keeps the first executable unit small: the shared
vocabulary plus the prayer/timetable path. Qibla, Moon, and Hijri are designed
here, but they are implemented in A2 and A3. This follows the improvement
report's own decision 1: prove the common shape across contexts before
replicating it. It also keeps the first review focused on the most-used
pipeline and its polar-fallback trace.

## 2. Why this slice is first

Every later workstream depends on being able to answer, reproducibly:

- which preset and policy produced this value;
- which inputs were requested and which inputs were actually used after a
  fallback;
- which algorithm or data table was involved, and at which version or digest;
- whether the result is complete, extrapolated, fallback-derived, or still
  requires an authority/observation decision;
- how to serialize those facts without exposing internal engine objects.

Without that foundation, later locale, calendar, profile, and export work would
multiply ambiguous outputs. The report's exit gate for Release A is explicit:
every existing preset and calendar has provenance, applicability status, and
reproducible serialized inputs; no existing output changes without a golden and
changelog entry.

## 3. Scope

### In scope

- A public, immutable provenance vocabulary in `SharedKernel`.
- One opt-in metadata operation per calculation entry point:
  - Prayer: `PrayerTimesCalculator::calculateWithMetadata()`;
  - Timetable: `TimetableService::monthlyWithMetadata()`;
  - Qibla: `QiblaCalculator::calculateWithMetadata()`;
  - Moon: `CrescentCalculator::atSunsetWithMetadata()`,
    `evaluateWithMetadata()`, and `evaluateGeometryWithMetadata()`;
  - Hijri: `GregorianToHijriConverter::convertWithMetadata()`.
- Typed metadata and outcome objects for each context.
- JSON-safe `toArray()` output with a schema version and stable key naming.
- Effective-input and fallback recording:
  - polar coordinate/date substitution in prayer;
  - ΔT extrapolation outside 2005–2050 and no-moonset geometry in Moon;
  - maghrib-only probe fallback in the Hijri sunset frame.
- Stable source identifiers for built-in algorithms and generated tables.
- Library-version reporting without a runtime Composer dependency.
- Additive `toArray()` methods on public result value objects where the outcome
  envelope needs them.
- Documentation, API reference, architecture notes, and changelog entries for
  each implemented phase.

### Out of scope

- Changing any existing method signature, return type, exception, or output.
- A global warning mode or promoting warnings to exceptions. That belongs to
  P0.2; callers can promote warnings at their own boundary.
- Full applicability intervals and validity-range documentation for every
  model. This slice records the current status/warning evidence; P0.2 publishes
  the complete per-model matrix.
- JSON Schema files and cross-language contract tests. This slice defines the
  canonical `toArray()` shape; P1.6 adds checked-in schemas.
- Locale or translated messages. Metadata messages are English and are not
  identifiers.
- Official calendar datasets, profile registries, magnetic-declination
  providers, facades, CLIs, ICS/CSV exporters, caches, and batch APIs.
- Sunnah and NextPrayer metadata. They consume `PrayerSchedule`; callers can
  pair them with the schedule's metadata. A later slice can add convenience
  envelopes if needed.
- Display-zone provenance. Prayer calculation has no zone input; `forZone()`
  remains a representation-only edge operation. Civil-time context belongs to
  P1.2.

## 4. Current seams

| Context | Entry point | Current result | Provenance gap |
|---|---|---|---|
| Prayer | `PrayerTimesCalculator::calculate()` in `src/Prayer/Services/PrayerTimesCalculator.php` | `PrayerSchedule` | Effective polar coordinates/date are local variables; the selected rule is not returned; the choices are only recoverable from the caller's `CalculationParameters`. |
| Timetable | `TimetableService::monthly()` in `src/Prayer/Services/TimetableService.php` | `list<PrayerSchedule>` | A day-specific polar fallback is lost once the schedule is returned. |
| Qibla | `QiblaCalculator::calculate()` in `src/Qibla/Services/QiblaCalculator.php` | `QiblaResult` | The geodesy variant is not returned; the caller must remember which factory it used. |
| Moon | `CrescentCalculator::atSunset()`, `evaluate()`, `evaluateGeometry()` in `src/Moon/Services/CrescentCalculator.php` | `CrescentGeometry`, verdict map | ΔT value/origin, ephemeris identity, conjunction-search identity, and the criterion set are not returned. |
| Hijri | `GregorianToHijriConverter::convert()` in `src/Hijri/Services/GregorianToHijriConverter.php` | `HijriDate` | Calendar span/digest, country, offset source, sunset frame, and the maghrib-only fallback are not returned. |

Relevant existing contracts and value objects:

- `CalculationParameters` already carries the method, Madhab, high-latitude,
  polar, Fajr/Isha/Maghrib rules, offsets, elevation, Ramadan flag, and both
  adjustment layers. Metadata should snapshot it rather than replace it.
- `PrayerSchedule::toArray()` already has a documented stable shape. It must not
  change.
- `QiblaResult` already separates true-north bearing from caller-supplied
  magnetic declination. The metadata path reports the true-north reference; a
  separately sourced magnetic model remains P2.3.
- `CrescentGeometry` already has every geometry field evaluators need.
  Metadata must not duplicate geometry values; it records how they were
  produced.
- `HijriCalendar::name()` is a stable key but not a manifest. The generated
  tables already embed SHA-256 digests in `UmmAlQuraMonthStarts` and
  `MabimsMonthStarts`.
- `DeltaTProvider` is internal, calibrated for 2005–2050, and currently
  extrapolates silently. This slice makes that visible in Moon metadata.

## 5. Fixed constraints

These constraints come from `AGENTS.md`, `ARCHITECTURE.md`, and the improvement
report. They are not open for revision in this slice.

1. Existing results and method signatures stay backward compatible. No
   `calculate()` union return and no metadata properties on existing result
   constructors.
2. Metadata is built from the same calculation run. A sibling method must not
   calculate twice or reconstruct fallback decisions from exception strings.
3. Public metadata must not expose `Astro/` objects, internal engine classes, or
   `DateTimeInterface` unions. Source identity is reported with stable strings,
   versions, ranges, and digests.
4. The core remains offline and dependency-free. No Composer runtime
   dependency, no network, no clock reads, no I/O.
5. Value objects stay `final readonly` and validate at construction. Public
   classes and methods receive docblocks that satisfy `ArchDocsTest`.
6. `Contracts/` stays interfaces-only; service contracts keep the `Interface`
   suffix; driven ports keep short names.
7. Warnings do not make an undefined schedule look valid. Invalid inputs and
   no-result cases keep their typed exceptions.
8. Warning codes are stable machine identifiers. Human messages are
   explanatory and may change in a minor release.
9. Serialized schema versions are independent of package versions. Adding a
   field or context is additive; removing or renaming a field requires a major
   schema version.
10. `Astro/` remains `@internal`; the one stable wiring exception
    (`SolarDayEngine` + `MeanSolarCalculator`) is unchanged.

## 6. Public API design

### 6.1 Namespaces and naming

Shared vocabulary:

| Type | Namespace |
|---|---|
| `CalculationContext`, `CalculationStatus`, `WarningCode`, `SourceKind` | `AlFalaq\AlFalaq\SharedKernel\Enums` |
| `CalculationMetadata`, `CalculationWarning`, `CalculationSource`, `LibraryVersion` | `AlFalaq\AlFalaq\SharedKernel\ValueObjects` |
| `IdentifiesCalculationSource` | `AlFalaq\AlFalaq\SharedKernel\Contracts` |

Context types:

| Context | Metadata | Outcome | Contracts/enums |
|---|---|---|---|
| Prayer | `Prayer\ValueObjects\PrayerCalculationMetadata` | `Prayer\ValueObjects\PrayerCalculationOutcome` | `Prayer\Contracts\PrayerMetadataCalculatorInterface` |
| Qibla | `Qibla\ValueObjects\QiblaCalculationMetadata` | `Qibla\ValueObjects\QiblaCalculationOutcome` | — |
| Moon | `Moon\ValueObjects\MoonCalculationMetadata` | `Moon\ValueObjects\CrescentGeometryOutcome`, `Moon\ValueObjects\CrescentEvaluationOutcome` | — |
| Hijri | `Hijri\ValueObjects\HijriCalculationMetadata` | `Hijri\ValueObjects\HijriCalculationOutcome` | `Hijri\Enums\HijriDateFrame`, `Hijri\Enums\CalendarFamily`, `Hijri\Enums\CalendarCoverage`, `Hijri\ValueObjects\CalendarProvenance`, `Hijri\Contracts\DescribesCalendar` |

Naming rules:

- Outcome envelopes end in `Outcome`; their payload property is `result` (the
  existing domain value object) except `CrescentEvaluationOutcome`, which has
  `result` (geometry) and `verdicts` because both are first-class outputs.
- Metadata types end in `CalculationMetadata`.
- Wire keys are `snake_case`. Existing keys in `PrayerSchedule::toArray()` are
  unchanged.
- Numeric keys include units: `_degrees`, `_minutes`, `_hours`, `_km`, `_m`,
  `_seconds`.
- Two conventional exceptions: `latitude`/`longitude` are WGS84 degrees by
  contract, and adjustment maps are minute offsets keyed by prayer name.
- Enums serialize to their string value, never to a PHP enum object.
- Nullable facts are present with `null`; they are not omitted. Empty lists are
  present as `[]`.

### 6.2 Shared enums

#### `CalculationContext`

```php
enum CalculationContext: string
{
    case PRAYER = 'prayer';
    case QIBLA = 'qibla';
    case MOON = 'moon';
    case HIJRI = 'hijri';
}
```

A timetable is a sequence of prayer outcomes; there is no separate `TIMETABLE`
context. Each day carries `context = 'prayer'`.

#### `CalculationStatus`

```php
enum CalculationStatus: string
{
    case COMPLETE = 'complete';
    case EXTRAPOLATED = 'extrapolated';
    case FALLBACK_APPLIED = 'fallback_applied';
    case INDETERMINATE = 'indeterminate';
    case DECISION_REQUIRED = 'decision_required';
}
```

Meaning and precedence:

| Status | Meaning | Rank |
|---|---|---:|
| `COMPLETE` | The calculation completed within its documented model. Warnings may still be present (for example a generated-table notice). | 0 |
| `EXTRAPOLATED` | A model produced a deterministic value outside its calibrated interval. The value is returned and is not described as validated. | 1 |
| `FALLBACK_APPLIED` | A defined fallback replaced part of the requested input or probe path. Effective inputs are recorded. | 2 |
| `INDETERMINATE` | At least one optional output is unavailable (for example no moonset on the civil date). Available outputs are still returned. | 3 |
| `DECISION_REQUIRED` | The numerical result is complete, but the policy answer is not: a local authority or observation decision is required before treating it as an official calendar/sighting answer. | 4 |

`CalculationMetadata::create()` derives the status from the warnings and keeps
the highest rank. The warnings list always carries every applicable condition,
so a result with both a fallback and an extrapolation reports
`fallback_applied` and both warning codes.

`DECISION_REQUIRED` is used by Moon evaluation outcomes: a criterion score is
not an observation and is not an authority announcement. It is not a failure
status. Prayer, Qibla, and Hijri metadata in this slice emit `COMPLETE`,
`EXTRAPOLATED`, or `FALLBACK_APPLIED` only.

#### `WarningCode`

```php
enum WarningCode: string
{
    case DELTA_T_EXTRAPOLATED = 'delta_t_extrapolated';
    case POLAR_FALLBACK_APPLIED = 'polar_fallback_applied';
    case SUNSET_FRAME_MAGHRIB_FALLBACK = 'sunset_frame_maghrib_fallback';
    case MOONSET_INDETERMINATE = 'moonset_indeterminate';
    case CRESCENT_CRITERION_IS_NOT_OBSERVATION = 'crescent_criterion_is_not_observation';
    case GENERATED_CALENDAR_TABLE = 'generated_calendar_table';
    case TABULAR_CALENDAR_ESTIMATE = 'tabular_calendar_estimate';
    case UNIDENTIFIED_CALCULATION_SOURCE = 'unidentified_calculation_source';
}
```

The full semantics, status effect, and detail keys are in section 7. A1
implements the enum and emits only `POLAR_FALLBACK_APPLIED` and
`UNIDENTIFIED_CALCULATION_SOURCE`; A2 and A3 add the remaining emissions as
their paths land. The cases are defined now so the shared status/warning
mapping does not change shape between phases.

#### `SourceKind`

```php
enum SourceKind: string
{
    case ALGORITHM = 'algorithm';
    case GENERATED_DATA = 'generated_data';
    case OFFICIAL_DATA = 'official_data';
    case CALLER_INPUT = 'caller_input';
}
```

`GENERATED_DATA` and `OFFICIAL_DATA` require a digest and a range.
`ALGORITHM` may carry a version and a model range. `CALLER_INPUT` carries only
an identifier; the caller owns the value's provenance.

The two data kinds are reserved for the separately versioned data packages in
the report's package-boundary decision. The core's own calendars report their
data identity through `CalendarProvenance` (which also carries family,
authority, range, digest, source, and license), not through a second
`CalculationSource`. The data kinds are defined now so a future data package
can reuse the source shape without a schema break.

### 6.3 Shared value objects

#### `LibraryVersion`

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

`CURRENT` is the checked-in runtime version. It is bumped with the changelog
when a release is prepared; the release tag becomes exact (`0.3.0`), and the
development value uses a `-dev` suffix. `0.3.0-dev` is the assumed next minor
for this spec; release preparation may change the exact number, and `CURRENT`
must follow the changelog and tag. No Composer runtime dependency and no
package-metadata read at calculation time. Validation accepts
`^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$`.

`CalculationMetadata::create()` defaults to `LibraryVersion::current()`. The
optional `$libraryVersion` parameter exists for tests and for callers that
reconstruct historical metadata; normal service paths use the current value.

#### `CalculationWarning`

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

- `message` is non-empty after trimming.
- `details` keys are non-empty strings; values are scalar or `null`. No nested
  arrays or objects. Float details are finite, so `json_encode()` cannot fail
  on `NAN` or `INF`.
- The constructor does not sort; ordering is owned by `CalculationMetadata`.

`toArray()`:

```php
$details = $this->details;
ksort($details);

return [
    'code' => $this->code->value,
    'message' => $this->message,
    'details' => $details,
];
```

Sorting the detail keys here makes the warning sort in section 7.2
deterministic even when two callers build the same details in different
orders.

#### `CalculationSource`

```php
final readonly class CalculationSource
{
    private function __construct(
        public string $id,
        public SourceKind $kind,
        public ?string $version,
        public ?string $digest,
        public ?string $range,
    ) {}

    public static function algorithm(
        string $id,
        ?string $version = null,
        ?string $range = null,
    ): self;

    public static function generatedData(
        string $id,
        string $digest,
        string $range,
        ?string $version = null,
    ): self;

    public static function officialData(
        string $id,
        string $digest,
        string $range,
        ?string $version = null,
    ): self;

    public static function callerInput(string $id): self;

    public function toArray(): array;
}
```

Validation:

- `id` matches `^[a-z0-9][a-z0-9._-]*$`. Identifiers are lowercase and stable
  within a schema major version. Method-preset ids may contain the enum's
  canonical underscores (for example
  `prayer.method.muslim_world_league`).
- `version`, `range`, and `digest` are non-empty when present.
- `digest` matches `^[a-f0-9]{64}$`.
- `generatedData()` and `officialData()` reject a missing or malformed digest
  and a missing range.
- `callerInput()` carries no version, digest, or range.

`toArray()` always contains all five keys:

```php
[
    'id' => $this->id,
    'kind' => $this->kind->value,
    'version' => $this->version,
    'digest' => $this->digest,
    'range' => $this->range,
]
```

#### `CalculationMetadata`

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

Behavior:

- The constructor validates that `warnings` and `sources` are lists of the
  expected types.
- Warnings are sorted by `code` value, then `message`, then the serialized
  details from `CalculationWarning::toArray()` (sorted detail keys). Sources
  are sorted by `id`, then `kind` value, then `version` (null last).
  Sorting makes output deterministic regardless of construction order.
- After sorting, identical warnings (same code, message, and details) and
  identical sources (all five fields) collapse to one entry. A repeated
  condition is one fact, not two; warnings from different components stay
  distinct because `component` is part of the details.
- `create()` derives the status from the warning set using section 7 and uses
  `LibraryVersion::current()` when no version is supplied.
- Service paths must use `create()`. The constructor is private so the status
  cannot be supplied independently of the warnings; this keeps the derivation
  in section 7 as the single source of truth. A future deserializer, if added,
  must validate a stored status against the warning set before restoring it.
- The class is `final readonly`; large arrays are immutable by convention and
  by the readonly property. Callers must not mutate them.

`toArray()`:

```php
[
    'schema_version' => self::SCHEMA_VERSION,
    'context' => $this->context->value,
    'status' => $this->status->value,
    'library_version' => (string) $this->libraryVersion,
    'warnings' => array_map(...),
    'sources' => array_map(...),
]
```

Key order is fixed as above. `warnings` and `sources` are always present, even
when empty.

#### `IdentifiesCalculationSource`

```php
interface IdentifiesCalculationSource
{
    public function calculationSource(): CalculationSource;
}
```

This is a driven capability, so it keeps the short name (no `Interface`
suffix). It is additive: existing interfaces are not changed. Built-in
strategies and engines implement it; custom implementations may opt in.

When a service receives a component that does not implement the interface, it
must not guess. It records `CalculationSource::algorithm('custom.' . $component)`
and adds an `UNIDENTIFIED_CALCULATION_SOURCE` warning with the component name.
The result remains usable, but the audit trail says the source is unknown.

### 6.4 Context metadata and outcomes

#### Prayer and timetable

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

- `effectiveCoordinates`/`effectiveDate` always equal the requested pair unless
  the polar handler replaced them.
- `fallbackRule` is `null` when no polar fallback was applied; otherwise it is
  the configured `PolarCircleRule` that was used. `common->status` is
  `FALLBACK_APPLIED` when this is non-null.
- `parameters` is the exact immutable configuration passed to the calculation,
  including both adjustment layers. It is a public value object, not an
  internal engine object.
- `ROUNDING` names the existing behavior: effective offsets are added, then the
  instant is rounded half-up to the minute in UTC. No new rounding policy is
  introduced. Minute is the only precision mode in this slice; there is no
  sub-minute or seconds-preserving mode to report.

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

`PrayerTimesCalculator` implements `PrayerMetadataCalculatorInterface` with the
same `Coordinates|array` widening as `calculate()`. The existing
`PrayerTimesCalculatorInterface` is unchanged.

`TimetableService::monthlyWithMetadata()` returns one
`PrayerCalculationOutcome` per calendar day, in order. The per-day metadata is
what makes a day-specific polar fallback visible. A null injected calculator
uses `PrayerTimesCalculator::defaultFor($parameters)`, which implements the
metadata contract. If an injected calculator does not implement
`PrayerMetadataCalculatorInterface`, the method throws `ConfigurationException`;
the legacy `monthly()` path is unchanged. The new method is a concrete
`TimetableService` capability, like `PrayerTimesCalculator::maghribAt()`; the
existing `TimetableServiceInterface` is unchanged.

#### Qibla

```php
final readonly class QiblaCalculationMetadata
{
    public const HEADING_REFERENCE = 'true_north';

    public function __construct(
        public CalculationMetadata $common,
        public Coordinates $coordinates,
    ) {}

    public function toArray(): array;
}
```

```php
final readonly class QiblaCalculationOutcome
{
    public function __construct(
        public QiblaResult $result,
        public QiblaCalculationMetadata $metadata,
    ) {}

    public function toArray(): array;
}
```

The metadata path reports the geodetic true-north answer. It does not apply a
magnetic declination and does not claim to. `QiblaResult::magneticHeading()`
remains a caller-supplied edge operation; a sourced magnetic model with epoch
and uncertainty is P2.3.

`toArray()` includes:

```php
'heading_reference' => 'true_north',
'magnetic_declination_degrees' => null,
```

The null field is deliberate: it keeps the shape stable and makes the absence
of a model explicit.

#### Moon

```php
final readonly class MoonCalculationMetadata
{
    public const GEOMETRY_CALCULATED = 'calculated';
    public const GEOMETRY_CALLER_SUPPLIED = 'caller_supplied';
    public const DELTA_T_PROVIDER = 'provider';
    public const DELTA_T_OVERRIDE = 'override';

    /**
     * @param list<string> $criteria
     */
    public function __construct(
        public CalculationMetadata $common,
        public ?Coordinates $coordinates,
        public ?CivilDate $date,
        public ?float $deltaTSeconds,
        public ?CalculationSource $deltaTSource,
        public string $geometrySource,
        public array $criteria,
    ) {}

    public function toArray(): array;
}
```

- `deltaTSeconds` and `deltaTSource` are `null` only for caller-supplied
  geometry. For a calculated geometry, `deltaTSource` is either the provider
  source or `CalculationSource::callerInput('caller.delta-t')`. The provider
  source is `CalculationSource::algorithm(DeltaTProvider::MODEL_ID, range:
  '2005..2050')`; it has no digest because it is an algorithm, not a table.
- `coordinates` and `date` are `null` only for caller-supplied geometry; the
  geometry bundle does not carry where or when it was computed. The serialized
  input block therefore contains `{"coordinates": null, "date": null}` rather
  than inventing them.
- `deltaTSource->kind` distinguishes provider (`ALGORITHM`) from override
  (`CALLER_INPUT`); `toArray()` maps that to `origin = 'provider'` or
  `'override'`.
- `geometrySource` is one of the two constants.
- `criteria` is empty for geometry-only outcomes and is the fixed evaluation
  order for evaluation outcomes:
  `['yallop', 'odeh', 'mabims1992', 'neomabims2021']`.

```php
final readonly class CrescentGeometryOutcome
{
    public function __construct(
        public CrescentGeometry $result,
        public MoonCalculationMetadata $metadata,
    ) {}

    public function toArray(): array;
}
```

```php
final readonly class CrescentEvaluationOutcome
{
    /**
     * @param array{yallop: CrescentVerdict, odeh: CrescentVerdict, mabims1992: CrescentVerdict, neomabims2021: CrescentVerdict} $verdicts
     */
    public function __construct(
        public CrescentGeometry $result,
        public array $verdicts,
        public MoonCalculationMetadata $metadata,
    ) {}

    public function toArray(): array;
}
```

`CrescentCalculator` keeps `atSunset()`, `evaluate()`, and `evaluateGeometry()`
unchanged. The new methods are:

```php
public function atSunsetWithMetadata(
    Coordinates $coordinates,
    CivilDate $date,
    ?float $deltaTOverride = null,
): CrescentGeometryOutcome;

public function evaluateWithMetadata(
    Coordinates $coordinates,
    CivilDate $date,
    ?float $deltaTOverride = null,
): CrescentEvaluationOutcome;

public function evaluateGeometryWithMetadata(
    CrescentGeometry $geometry,
): CrescentEvaluationOutcome;
```

`evaluateWithMetadata()` computes geometry once through the metadata path, then
runs the same four evaluators. `evaluateGeometryWithMetadata()` records
`geometrySource = caller_supplied`, `delta_t = null`, and only the evaluator
sources; it does not pretend it knows how the geometry was produced.

Delta-T model constants live on the internal provider and are exposed only as
source ids/ranges in metadata:

```php
DeltaTProvider::MODEL_ID = 'moon.delta-t.espenak-2005-2050';
DeltaTProvider::VALID_FROM_YEAR = 2005.0;
DeltaTProvider::VALID_TO_YEAR = 2050.0;
```

A provider year outside the interval adds `DELTA_T_EXTRAPOLATED` and sets
status `EXTRAPOLATED`. A caller override never adds that warning: the caller
owns the value. A null `lagMinutes`/`ageAtMoonsetHours` adds
`MOONSET_INDETERMINATE` and sets status `INDETERMINATE`. An evaluation outcome
adds `CRESCENT_CRITERION_IS_NOT_OBSERVATION` and sets status
`DECISION_REQUIRED`.

#### Hijri

```php
enum HijriDateFrame: string
{
    case CIVIL_DATE = 'civil_date';
    case SUNSET_ROLLOVER = 'sunset_rollover';
}

enum CalendarFamily: string
{
    case TABULAR = 'tabular';
    case UMM_AL_QURA = 'umm_al_qura';
    case MABIMS = 'mabims';
}

enum CalendarCoverage: string
{
    case ARITHMETIC_RULE = 'arithmetic_rule';
    case GENERATED_TABLE = 'generated_table';
    case OFFICIAL_TABLE = 'official_table';
}
```

```php
final readonly class CalendarProvenance
{
    public function __construct(
        public string $key,
        public ?CalendarFamily $family,
        public ?CalendarCoverage $coverage,
        public ?string $authority = null,
        public ?string $range = null,
        public ?string $digest = null,
        public ?string $source = null,
        public ?string $license = null,
    ) {}

    public function toArray(): array;
}
```

`key` is the calendar's stable `name()` value. `family` and `coverage` are
nullable only for a custom calendar that does not implement
`DescribesCalendar`. `authority` is the member-state code for MABIMS and
`null` for the current generated Umm al-Qura table, which is not an official
Saudi dataset. `source` is a human-readable generator/pin reference, not a
runtime URL. `digest` is lowercase SHA-256 when the calendar is table-backed.
`license` records the redistribution status for the data; the current generated
tables follow the repository license.

Validation:

- `key` is non-empty after trimming. It is the calendar's own `name()` value,
  not a `CalculationSource` id; a custom calendar may use a name that does not
  match the source-id grammar. Built-in keys are the lowercase values in the
  table above.
- `family` and `coverage` are both `null` or both non-null. A half-described
  custom calendar is not a valid provenance object.
- `GENERATED_TABLE` and `OFFICIAL_TABLE` require a lowercase SHA-256 `digest`
  and a non-empty `range`. `OFFICIAL_TABLE` also requires `authority`, `source`,
  and `license`; a table cannot claim official status without naming the
  authority, the dataset, and its redistribution terms.
- `ARITHMETIC_RULE` may have a null digest; `TabularCalendar` is the only
  shipped calendar in that state.

```php
interface DescribesCalendar
{
    public function calendarProvenance(): CalendarProvenance;
}
```

`DescribesCalendar` is a driven capability, so it keeps the short name. The
three shipped calendars implement it:

| Calendar | key | family | coverage | authority | range | digest |
|---|---|---|---|---|---|---|
| `TabularCalendar` | `tabular` | `TABULAR` | `ARITHMETIC_RULE` | `null` | `1900-01-01..2100-12-31 CE` | `null` |
| `UmmAlQuraCalendar` | `uqu` | `UMM_AL_QURA` | `GENERATED_TABLE` | `null` | `1423-01..1500-12 AH` | `UmmAlQuraMonthStarts::SHA256` |
| `MabimsCalendar` | `mabims` | `MABIMS` | `GENERATED_TABLE` | country code | `1445-01..1500-12 AH` | `MabimsMonthStarts::digestFor($country)` |

The `uqu` key stays `uqu`. The generated table is not renamed or presented as
official. `OFFICIAL_TABLE` exists for a future separately sourced dataset and
is not used by any shipped calendar in this slice.

```php
final readonly class HijriCalculationMetadata
{
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
    ) {}

    public function toArray(): array;
}
```

- `requestedInstant` is the caller's instant; `toArray()` renders it in UTC and
  also records the original zone name.
- `resolvedCivilDate` is the Gregorian date actually converted after the frame
  and rollover decision.
- `offsetDays` is `null` when no `OffsetStore` was supplied; `0` is a real
  answer from a store and is distinguishable from "no store".
- `maghribFallbackApplied` is true when the sunset frame used
  `maghribAt()` because the full schedule was undefined but maghrib itself was
  defined. The fallback must be visible even when the resulting date is
  unchanged.
- `coordinates`/`parameters` are populated only for the sunset frame; for the
  civil-date frame they are `null` because the converter does not consult them.

```php
final readonly class HijriCalculationOutcome
{
    public function __construct(
        public HijriDate $result,
        public HijriCalculationMetadata $metadata,
    ) {}

    public function toArray(): array;
}
```

`GregorianToHijriConverter::convert()` keeps its signature and behavior.
`convertWithMetadata()` uses the same arguments and returns the outcome. The
converter's internal trace records the resolved civil date, offset days, and
whether the maghrib probe used its `maghribAt()` fallback. That trace is shared
with the legacy path; the legacy path ignores it.

`convertWithMetadata()` emits `GENERATED_CALENDAR_TABLE` when the calendar
provenance coverage is `GENERATED_TABLE`, `TABULAR_CALENDAR_ESTIMATE` when it
is `ARITHMETIC_RULE`, and no coverage warning for `OFFICIAL_TABLE`. A custom
calendar without `DescribesCalendar` gets
`UNIDENTIFIED_CALCULATION_SOURCE` with `component = calendar`. The warning is
driven by the reported coverage, not by a class-name check, so a custom
generated table is labelled honestly.

### 6.5 Service methods

Exact additions; existing methods are unchanged.

```php
// Prayer\Services\PrayerTimesCalculator
public function calculateWithMetadata(
    Coordinates|array $coordinates,
    CivilDate $date,
    CalculationParameters $parameters,
): PrayerCalculationOutcome;

// Prayer\Services\TimetableService
/** @return list<PrayerCalculationOutcome> */
public function monthlyWithMetadata(
    Coordinates $coordinates,
    int $year,
    int $month,
    CalculationParameters $parameters,
): array;

// Qibla\Services\QiblaCalculator
public function calculateWithMetadata(
    Coordinates|array $coordinates,
): QiblaCalculationOutcome;

// Moon\Services\CrescentCalculator
public function atSunsetWithMetadata(
    Coordinates $coordinates,
    CivilDate $date,
    ?float $deltaTOverride = null,
): CrescentGeometryOutcome;

public function evaluateWithMetadata(
    Coordinates $coordinates,
    CivilDate $date,
    ?float $deltaTOverride = null,
): CrescentEvaluationOutcome;

public function evaluateGeometryWithMetadata(
    CrescentGeometry $geometry,
): CrescentEvaluationOutcome;

// Hijri\Services\GregorianToHijriConverter
public function convertWithMetadata(
    \DateTimeImmutable $dateTime,
    HijriCalendar $calendar,
    ?Coordinates $coordinates = null,
    ?CalculationParameters $parameters = null,
    bool $changeAtSunset = false,
    ?OffsetStore $offsets = null,
): HijriCalculationOutcome;
```

### 6.6 Additive result serializers

The outcome envelopes need JSON-safe payloads. These existing public value
objects gain additive `toArray()` methods. Their PHP properties and existing
methods are unchanged.

| Type | Wire shape |
|---|---|
| `PrayerSchedule` | Already exists; unchanged. |
| `QiblaResult` | `direction_degrees`, `distance_km`. |
| `CrescentGeometry` | Unit-suffixed keys listed below. |
| `CrescentVerdict` | `criterion`, `visible`, `score`, `zone`. |
| `HijriDate` | `year`, `month`, `day`, `iso`. |
| `CalculationParameters` | Full policy snapshot listed below. |
| `PrayerAdjustments` | All ten prayer keys, missing values as `0`. |

`PrayerAdjustments::toArray()` walks `Prayer::cases()` in enum order and reads
`for($prayer)`; a missing offset is `0`, so the shape is stable for every set.

`CrescentGeometry::toArray()`:

```php
[
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
]
```

`CalculationParameters::toArray()`:

```php
[
    'method' => $this->method->value,
    'madhab' => $this->madhab->value,
    'high_latitude_rule' => $this->highLatitudeRule->value,
    'polar_circle_rule' => $this->polarCircleRule->value,
    'fajr_angle_degrees' => $this->fajrAngle,
    'isha_rule' => [
        'type' => $this->ishaRule->angle() !== null ? 'angle' : 'interval',
        'angle_degrees' => $this->ishaRule->angle(),
        'minutes' => $this->ishaRule->minutesAfterMaghrib(),
    ],
    'maghrib_rule' => [
        'type' => $this->maghribRule->angle() !== null ? 'angle' : 'interval',
        'angle_degrees' => $this->maghribRule->angle(),
        'minutes' => $this->maghribRule->minutesAfterSunset(),
    ],
    'dhuhr_offset_minutes' => $this->dhuhrOffsetMinutes,
    'imsak_offset_minutes' => $this->imsakOffsetMinutes,
    'ishraq_offset_minutes' => $this->ishraqOffsetMinutes,
    'dhuha_offset_minutes' => $this->dhuhaOffsetMinutes,
    'elevation_m' => $this->elevationM,
    'is_ramadan' => $this->isRamadan,
    'method_adjustments' => $this->methodAdjustments->toArray(),
    'adjustments' => $this->adjustments->toArray(),
]
```

Rule snapshots always include `type`, `angle_degrees`, and `minutes`; the
unused pair is `null`. This keeps the shape stable for consumers. The twilight
model is not a separate `CalculationParameters` field: it is identified by the
`prayer.high-latitude.seasonal_twilight` source when the seasonal mode is
selected, and by the high-latitude correction source when the portions mode is
selected.

## 7. Status and warning semantics

### 7.1 Warning table

| Code | Emitted by | Status effect | Detail keys |
|---|---|---|---|
| `delta_t_extrapolated` | Moon, provider ΔT outside 2005–2050 | `extrapolated` | `year`, `calibrated_from_year`, `calibrated_to_year` |
| `polar_fallback_applied` | Prayer, polar handler invoked | `fallback_applied` | `rule`, `requested_latitude`, `requested_longitude`, `effective_latitude`, `effective_longitude`, `requested_date`, `effective_date` |
| `sunset_frame_maghrib_fallback` | Hijri, sunset frame uses `maghribAt()` | `fallback_applied` | `date`, `reason` |
| `moonset_indeterminate` | Moon, no moonset on the civil date | `indeterminate` | `date`, `reason` |
| `crescent_criterion_is_not_observation` | Moon evaluation | `decision_required` | `criteria` (comma-separated fixed order) |
| `generated_calendar_table` | Hijri UQ/MABIMS | none | `calendar`, `range` |
| `tabular_calendar_estimate` | Hijri tabular | none | `adjustment_days` |
| `unidentified_calculation_source` | Any custom component without `IdentifiesCalculationSource` | none | `component`, `source_id` |

`reason` values in this slice:

- `sunset_frame_maghrib_fallback`: `full_schedule_undefined`.
- `moonset_indeterminate`: `no_moonset_on_civil_date`.

`unidentified_calculation_source.source_id` is always `custom.<component>`;
`component` is one of the fixed names in section 8.2.

`tabular_calendar_estimate.adjustment_days` is the applied `OffsetStore`
correction (`0` when no store is supplied); the warning itself means the month
length is arithmetic, not observed.

A warning is never used to make an invalid request succeed. For example, an
out-of-range table lookup still raises `HijriOutOfRangeException`; a polar day
with `PolarCircleRule::NONE` still raises `UndefinedScheduleException`.

### 7.2 Deterministic ordering

- Warnings sort by `code` enum value, then `message`, then the scalar detail
  map serialized in key order.
- Sources sort by `id`, then `kind` value, then `version` (null last).
- Identical warnings and identical sources collapse to one entry after
  sorting; see section 6.3.
- `criteria` is the fixed order `yallop, odeh, mabims1992, neomabims2021`.
- `PrayerSchedule::toArray()` keeps its existing prayer order.
- `CalculationParameters::toArray()` uses the field order in section 6.6.
- No metadata includes a calculation timestamp, a random value, a host name,
  or a clock read.

### 7.3 Status derivation

`CalculationMetadata::create()` applies the table in section 7.1 and keeps the
highest rank from `CalculationStatus`. The warning list is not reduced. This
means:

- a normal prayer result is `complete`;
- a polar fallback is `fallback_applied` even when the resulting times are
  mathematically valid;
- a Moon geometry outside the ΔT interval is `extrapolated`;
- a no-moonset geometry is `indeterminate`, even if it is also extrapolated;
- a Moon evaluation is `decision_required`, with any extrapolation/no-moonset
  warnings still present.

### 7.4 What the legacy methods keep

The legacy methods keep their current exception behavior and return values.
They do not expose warnings. A caller that wants the warnings calls the
metadata sibling. The library does not add a global warning mode and does not
change `set_error_handler` behavior. Applications may promote selected warning
codes to exceptions at their own boundary.

## 8. Source identity

### 8.1 Built-in source registry

The registry is part of the public vocabulary. Ids are stable within schema
major version 1. Each built-in strategy or engine that participates through a
contract exposes its id as a class constant and implements
`IdentifiesCalculationSource`; the constant is the single source of truth for
the id. Synthetic sources (method presets, caller inputs, the Hijri probe) are
built by the service from the documented id and are not component objects.

| Source id | Kind | Version/range | Owner |
|---|---|---|---|
| `prayer.method.<enum value>` | `ALGORITHM` | version `method-defaults-v1` (`CalculationMethod::SOURCE_VERSION`) | `CalculationMethod` preset; `<enum value>` is the canonical lowercase value |
| `solar.mean-meeus` | `ALGORITHM` | version `meeus-2ed` | `Astro\Services\MeanSolarCalculator` |
| `prayer.high-latitude.middle_of_night` | `ALGORITHM` | — | `MiddleOfNightCorrection` |
| `prayer.high-latitude.seventh_of_night` | `ALGORITHM` | — | `SeventhOfNightCorrection` |
| `prayer.high-latitude.twilight_angle` | `ALGORITHM` | — | `TwilightAngleCorrection` |
| `prayer.high-latitude.seasonal_twilight` | `ALGORITHM` | — | `SeasonAdjustedTwilight` (also the twilight model) |
| `prayer.polar.nearest_latitude` | `ALGORITHM` | — | `NearestLatitudeHandler` |
| `prayer.polar.nearest_day` | `ALGORITHM` | — | `NearestDayHandler` |
| `prayer.polar.makkah` | `ALGORITHM` | — | `MakkahHandler` |
| `prayer.polar.none` | `ALGORITHM` | — | `ThrowHandler` (policy-only; never emitted as a source) |
| `prayer.isha.angle` | `ALGORITHM` | — | `AngleIsha` |
| `prayer.isha.interval` | `ALGORITHM` | — | `IntervalIsha` |
| `prayer.maghrib.angle` | `ALGORITHM` | — | `AngleMaghrib` |
| `prayer.maghrib.interval` | `ALGORITHM` | — | `MinutesAfterSunsetMaghrib` |
| `caller.adjustments` | `CALLER_INPUT` | — | non-empty caller adjustment layer |
| `qibla.spherical` | `ALGORITHM` | version `todhunter-haversine` | `SphericalCalculator` |
| `qibla.wgs84-karney` | `ALGORITHM` | version `geographiclib-2.1` | `Wgs84KarneyCalculator` |
| `moon.ephemeris.meeus-2ed` | `ALGORITHM` | version `meeus-2ed` | `CrescentEphemeris` |
| `moon.conjunction-search.meeus-hourly` | `ALGORITHM` | version `meeus-hourly` | `EphemerisConjunctionSearch` |
| `moon.delta-t.espenak-2005-2050` | `ALGORITHM` | range `2005..2050` | `DeltaTProvider` |
| `caller.delta-t` | `CALLER_INPUT` | — | `CrescentCalculator` override |
| `moon.criterion.yallop` | `ALGORITHM` | — | `YallopEvaluator` |
| `moon.criterion.odeh` | `ALGORITHM` | — | `OdehEvaluator` |
| `moon.criterion.mabims-1992` | `ALGORITHM` | — | `Mabims1992Evaluator` |
| `moon.criterion.neo-mabims-2021` | `ALGORITHM` | — | `NeoMabims2021Evaluator` |
| `hijri.maghrib-probe` | `ALGORITHM` | — | `GregorianToHijriConverter` sunset frame |
| `hijri.offset.caller` | `CALLER_INPUT` | — | caller-supplied `OffsetStore` |

The prayer method source is emitted even for `CalculationMethod::NONE`
(`prayer.method.none`), so the audit trail never has an empty method identity.
`CalculationMethod::SOURCE_VERSION` is a separate contract from the package
version; it is bumped when any preset default changes.

A source list identifies the algorithm/data dependency set that the public
service directly orchestrates; it is not a per-value data-flow trace. A listed
component may be present without changing a value. Actual replacements and
extrapolation are recorded by warnings, status, and effective inputs. This
keeps the first slice small and avoids claiming more than the code proves;
P0.2 may refine the list to consulted-only per value.

For prayer, the dependency set is:

- `solar.mean-meeus` (the mean-solar engine) and the Isha and Maghrib rules are
  always listed;
- the high-latitude correction is listed only when `NightResolver` selects the
  portions mode;
- the twilight model is listed only when `NightResolver` selects the seasonal
  mode;
- the polar handler is listed only when it actually ran (a fallback was
  applied); `prayer.polar.none` is policy-only and is never emitted because it
  throws instead of producing a result;
- `caller.adjustments` is listed when the caller adjustment layer is non-empty.

`NightResolver` returns its selected mode (`seasonal`, `none`, or `portions`)
with the two instants so the calculator can apply this table without a second
copy of the mode predicate. The mode is an internal trace, not public metadata.

The shared solar engine id is `solar.mean-meeus`, not `prayer.solar.mean-meeus`,
because the Moon path uses the same engine. Context-specific ids describe
context-specific algorithms; shared engine ids stay context-neutral.

For Hijri, the converter directly orchestrates the calendar (reported in the
`calendar` block, not as a `CalculationSource`) and, in the sunset frame, the
maghrib probe. It does not repeat the probe's internal prayer sources; the
request policy already records the maghrib rule used by that probe.

For Moon, the calculator directly orchestrates the solar engine, the lunar/solar
ephemeris, the conjunction search, the ΔT provider, and (for evaluation
outcomes) the four criterion strategies. Caller-supplied geometry lists only
the criteria, because the calculator did not produce the geometry.

`prayer.high-latitude.seasonal_twilight` is the only seasonal id: the same
`SeasonAdjustedTwilight` class serves both the high-latitude seasonal mode and
the twilight model, so the calculator must not emit a second id for it.

The selected identities come from the wired objects, not from a second lookup
of the enum. That keeps explicit wiring honest when a caller substitutes a
custom strategy. A custom component that implements
`IdentifiesCalculationSource` supplies its own id; one that does not gets a
`custom.<component>` id plus an `UNIDENTIFIED_CALCULATION_SOURCE` warning.

### 8.2 Custom components

- A custom component may implement `IdentifiesCalculationSource` and return any
  valid id.
- A component that does not implement it gets
  `CalculationSource::algorithm('custom.' . $component)` and an
  `UNIDENTIFIED_CALCULATION_SOURCE` warning.
- `component` values are fixed: `solar`, `high_latitude`, `polar`, `twilight`,
  `isha`, `maghrib`, `geodesy`, `ephemeris`, `conjunction_search`, `criterion`,
  `calendar`, `offset`.
- Custom ids are namespaced under `custom.` so they cannot collide with the
  built-in registry. A custom component that wants a first-class id should use
  `IdentifiesCalculationSource` and a documented id.

### 8.3 Data digests

Generated and official tables must carry a lowercase SHA-256 digest. The
already-shipped constants are the source of truth:

- `UmmAlQuraMonthStarts::SHA256`;
- `MabimsMonthStarts::digestFor(MabimsCountry)`.

No new digest is invented for tabular arithmetic; that calendar reports
`coverage = arithmetic_rule` and a null digest. A future official dataset must
ship its own digest, range, source, and license before it can use
`CalendarCoverage::OFFICIAL_TABLE`.

## 9. Serialization contract

### 9.1 Rules

1. `toArray()` is the canonical serializer for this slice. A separate
   serializer is deferred to P1.6 and must not be allowed to drift from
   `toArray()`.
2. `DateTimeImmutable` values become ISO 8601 strings with an explicit offset.
   UTC values use `Y-m-d\TH:i:sP`, matching `PrayerSchedule::toArray()`.
3. PHP enums become their string values. PHP objects are never embedded.
4. Internal class names, `Astro` namespaces, and exception messages are never
   part of the wire shape.
5. Nullable facts are present with `null`; optional arrays are present as `[]`.
6. `schema_version` is `"1.0"` for every metadata object in Release A.
7. The schema is independent of the package version. `library_version` is a
   separate field with the runtime library version.
8. Additive schema changes are minor (`1.0` → `1.1`) and consumers must ignore
   unknown keys. Removing, renaming, changing units, or changing the meaning of
   a key requires a major schema version.
9. Context-specific metadata is nested under `metadata`, never merged into the
   existing result payload. The outcome wrapper is:
   ```php
   ['result' => <payload>, 'metadata' => <metadata>]
   ```
10. `PrayerSchedule::toArray()` remains the prayer payload and keeps its
    existing promise: no new keys without a minor package bump. The metadata
    path wraps it and does not change it.

### 9.2 Common metadata shape

Every context metadata starts with the same six keys:

```json
{
  "schema_version": "1.0",
  "context": "prayer",
  "status": "complete",
  "library_version": "0.3.0-dev",
  "warnings": [],
  "sources": []
}
```

Warning entries:

```json
{
  "code": "polar_fallback_applied",
  "message": "Polar fallback replaced the requested coordinates because the solar day is undefined.",
  "details": {
    "rule": "nearest_latitude",
    "requested_latitude": 69.6492,
    "requested_longitude": 18.9553,
    "effective_latitude": 66.0,
    "effective_longitude": 18.9553,
    "requested_date": "2026-06-21",
    "effective_date": "2026-06-21"
  }
}
```

Source entries:

```json
{
  "id": "solar.mean-meeus",
  "kind": "algorithm",
  "version": "meeus-2ed",
  "digest": null,
  "range": null
}
```

### 9.3 Prayer outcome

Illustrative values; the shape is the contract, not these numbers.

```json
{
  "result": {
    "imsak": "2026-11-15T05:31:00+00:00",
    "fajr": "2026-11-15T05:41:00+00:00",
    "sunrise": "2026-11-15T06:51:00+00:00",
    "syuruk": "2026-11-15T06:51:00+00:00",
    "ishraq": "2026-11-15T07:06:00+00:00",
    "dhuha": "2026-11-15T07:19:00+00:00",
    "dhuhr": "2026-11-15T12:57:00+00:00",
    "asr": "2026-11-15T16:10:00+00:00",
    "maghrib": "2026-11-15T18:59:00+00:00",
    "isha": "2026-11-15T20:09:00+00:00"
  },
  "metadata": {
    "schema_version": "1.0",
    "context": "prayer",
    "status": "complete",
    "library_version": "0.3.0-dev",
    "warnings": [],
    "sources": [
      {
        "id": "prayer.high-latitude.middle_of_night",
        "kind": "algorithm",
        "version": null,
        "digest": null,
        "range": null
      },
      {
        "id": "prayer.isha.angle",
        "kind": "algorithm",
        "version": null,
        "digest": null,
        "range": null
      },
      {
        "id": "prayer.maghrib.interval",
        "kind": "algorithm",
        "version": null,
        "digest": null,
        "range": null
      },
      {
        "id": "prayer.method.singapore",
        "kind": "algorithm",
        "version": "method-defaults-v1",
        "digest": null,
        "range": null
      },
      {
        "id": "solar.mean-meeus",
        "kind": "algorithm",
        "version": "meeus-2ed",
        "digest": null,
        "range": null
      }
    ],
    "input": {
      "coordinates": {"latitude": 3.139, "longitude": 101.6869},
      "date": "2026-11-15"
    },
    "effective_input": {
      "coordinates": {"latitude": 3.139, "longitude": 101.6869},
      "date": "2026-11-15"
    },
    "policy": {
      "method": "singapore",
      "madhab": "shafi",
      "high_latitude_rule": "middle_of_night",
      "polar_circle_rule": "nearest_latitude",
      "fajr_angle_degrees": 20.0,
      "isha_rule": {"type": "angle", "angle_degrees": 18.0, "minutes": null},
      "maghrib_rule": {"type": "interval", "angle_degrees": null, "minutes": 0},
      "dhuhr_offset_minutes": 0,
      "imsak_offset_minutes": 10,
      "ishraq_offset_minutes": 15,
      "dhuha_offset_minutes": 28,
      "elevation_m": 0.0,
      "is_ramadan": false,
      "method_adjustments": {
        "imsak": 0, "fajr": 0, "sunrise": 0, "syuruk": 0, "ishraq": 0,
        "dhuha": 0, "dhuhr": 0, "asr": 0, "maghrib": 0, "isha": 0
      },
      "adjustments": {
        "imsak": 0, "fajr": 0, "sunrise": 0, "syuruk": 0, "ishraq": 0,
        "dhuha": 0, "dhuhr": 0, "asr": 0, "maghrib": 0, "isha": 0
      }
    },
    "fallback": {
      "applied": false,
      "kind": null,
      "rule": null
    },
    "rounding": "half_up_to_minute_utc"
  }
}
```

The fallback object for a polar result:

```json
"fallback": {
  "applied": true,
  "kind": "polar",
  "rule": "nearest_latitude"
}
```

The means of the fallback are also in the warning details and the effective
input block. The warning is the machine-readable reason; the effective input is
the reproducible replacement. A polar fallback additionally adds the handler
source (for example `prayer.polar.nearest_latitude`) to `sources`.

### 9.4 Qibla outcome

Illustrative values; the shape is the contract, not these numbers.

```json
{
  "result": {
    "direction_degrees": 292.4,
    "distance_km": 7070.0
  },
  "metadata": {
    "schema_version": "1.0",
    "context": "qibla",
    "status": "complete",
    "library_version": "0.3.0-dev",
    "warnings": [],
    "sources": [
      {
        "id": "qibla.wgs84-karney",
        "kind": "algorithm",
        "version": "geographiclib-2.1",
        "digest": null,
        "range": null
      }
    ],
    "input": {
      "coordinates": {"latitude": 3.139, "longitude": 101.6869}
    },
    "heading_reference": "true_north",
    "magnetic_declination_degrees": null
  }
}
```

### 9.5 Moon geometry outcome

Illustrative values; the shape is the contract, not these numbers.

```json
{
  "result": {
    "sun_altitude_degrees": -0.83,
    "moon_altitude_degrees": 8.4,
    "elongation_degrees": 9.1,
    "moon_age_hours": 27.2,
    "lag_minutes": 42.0,
    "delta_azimuth_degrees": 6.3,
    "illuminated_fraction": 0.016,
    "arcv_geocentric_degrees": 9.2,
    "width_arcminutes": 0.21,
    "age_at_moonset_hours": 27.9
  },
  "metadata": {
    "schema_version": "1.0",
    "context": "moon",
    "status": "complete",
    "library_version": "0.3.0-dev",
    "warnings": [],
    "sources": [
      {
        "id": "moon.conjunction-search.meeus-hourly",
        "kind": "algorithm",
        "version": "meeus-hourly",
        "digest": null,
        "range": null
      },
      {
        "id": "moon.delta-t.espenak-2005-2050",
        "kind": "algorithm",
        "version": null,
        "digest": null,
        "range": "2005..2050"
      },
      {
        "id": "moon.ephemeris.meeus-2ed",
        "kind": "algorithm",
        "version": "meeus-2ed",
        "digest": null,
        "range": null
      },
      {
        "id": "solar.mean-meeus",
        "kind": "algorithm",
        "version": "meeus-2ed",
        "digest": null,
        "range": null
      }
    ],
    "input": {
      "coordinates": {"latitude": 3.139, "longitude": 101.6869},
      "date": "2026-11-15"
    },
    "delta_t": {
      "seconds": 69.2,
      "origin": "provider",
      "calibrated_from_year": 2005.0,
      "calibrated_to_year": 2050.0
    },
    "geometry_source": "calculated",
    "criteria": []
  }
}
```

For a caller override:

```json
"delta_t": {
  "seconds": 69.0,
  "origin": "override",
  "calibrated_from_year": null,
  "calibrated_to_year": null
}
```

For caller-supplied geometry:

```json
"input": {
  "coordinates": null,
  "date": null
},
"delta_t": null,
"geometry_source": "caller_supplied",
"criteria": []
```

For an evaluation outcome, `result` becomes:

```json
{
  "result": {
    "geometry": { "...": "the same geometry shape" },
    "verdicts": {
      "yallop": {"criterion": "yallop", "visible": true, "score": 1.2, "zone": "A"},
      "odeh": {"criterion": "odeh", "visible": false, "score": 3.1, "zone": "C"},
      "mabims1992": {"criterion": "mabims1992", "visible": true, "score": 9.1, "zone": "visible"},
      "neomabims2021": {"criterion": "neomabims2021", "visible": true, "score": 8.2, "zone": "visible"}
    }
  },
  "metadata": { "...": "same metadata, criteria populated" }
}
```

`criteria` in metadata is:

```json
["yallop", "odeh", "mabims1992", "neomabims2021"]
```

An evaluation outcome adds the four criterion sources
(`moon.criterion.yallop`, `moon.criterion.odeh`,
`moon.criterion.mabims-1992`, `moon.criterion.neo-mabims-2021`) to the
geometry's sources. Caller-supplied geometry lists only the criteria, because
the provenance of the geometry belongs to the caller.

### 9.6 Hijri outcome

Illustrative values; the shape is the contract.

```json
{
  "result": {
    "year": 1448,
    "month": 5,
    "day": 25,
    "iso": "1448-05-25"
  },
  "metadata": {
    "schema_version": "1.0",
    "context": "hijri",
    "status": "complete",
    "library_version": "0.3.0-dev",
    "warnings": [
      {
        "code": "generated_calendar_table",
        "message": "This calendar is a generated table, not an official authority dataset.",
        "details": {
          "calendar": "mabims",
          "range": "1445-01..1500-12 AH"
        }
      }
    ],
    "sources": [
      {
        "id": "hijri.maghrib-probe",
        "kind": "algorithm",
        "version": null,
        "digest": null,
        "range": null
      }
    ],
    "input": {
      "instant": "2026-11-15T12:00:00+00:00",
      "zone": "Asia/Kuala_Lumpur",
      "frame": "sunset_rollover",
      "coordinates": {"latitude": 3.139, "longitude": 101.6869},
      "parameters": { "...": "CalculationParameters::toArray()" }
    },
    "effective": {
      "gregorian_date": "2026-11-15"
    },
    "calendar": {
      "key": "mabims",
      "family": "mabims",
      "coverage": "generated_table",
      "authority": "MY",
      "range": "1445-01..1500-12 AH",
      "digest": "0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef",
      "source": "tools/gen_hijri.py @ a7baa8b",
      "license": "MIT"
    },
    "offsets": {
      "days": null,
      "applied": false
    },
    "fallback": {
      "applied": false,
      "kind": null
    }
  }
}
```

The digest above is a shape example, not a real table digest; the tests use the
shipped constants. If an `OffsetStore` is supplied, `offsets.days` is its
answer (including `0`) and `offsets.applied` is `true`. A maghrib-only fallback
sets `fallback.applied = true`, `fallback.kind = "maghrib_only"`, and adds
`sunset_frame_maghrib_fallback`.

## 10. Internal trace and single-path rules

The metadata path must share the numeric path with the legacy method. The
implementation adds an internal trace at each service boundary; it does not
recompute astronomy and does not parse exceptions.

### 10.1 Prayer

`PrayerTimesCalculator` gets a private calculation routine that returns:

```php
[
    'schedule' => PrayerSchedule,
    'effectiveCoordinates' => Coordinates,
    'effectiveDate' => CivilDate,
    'polarFallbackRule' => ?PolarCircleRule,
    'nightMode' => 'seasonal'|'none'|'portions',
]
```

`calculate()` returns `['schedule']`. `calculateWithMetadata()` uses the same
array to build `PrayerCalculationMetadata`. The polar branch already knows
whether it ran; the trace records the configured rule at that point. The
`nightMode` comes from `NightResolver`, which returns it beside the two
instants; the calculator does not duplicate the mode predicate. The legacy path
allocates the small trace array but does not build metadata objects, warnings,
or source lists.

`TimetableService::monthlyWithMetadata()` calls
`calculateWithMetadata()` per day. It does not call `calculate()` and then try
to infer fallback state. `monthly()` stays on `calculate()`.

### 10.2 Qibla

`QiblaCalculator` gets a private result routine that returns the
`QiblaResult`; `calculate()` and `calculateWithMetadata()` both call it once.
The metadata path adds the geodesy source. There is no fallback state beyond
the source identity.

### 10.3 Moon

`CrescentCalculator` extracts the existing sunset computation into a private
routine returning:

```php
[
    'geometry' => CrescentGeometry,
    'deltaTSeconds' => float,
    'deltaTSource' => CalculationSource,
    'sources' => list<CalculationSource>,
]
```

`atSunset()` returns `['geometry']`. `atSunsetWithMetadata()` builds the
metadata from the same trace. `evaluateWithMetadata()` calls the metadata
geometry routine once and then runs `evaluateGeometry()`; it does not call
`atSunset()` and then a second metadata method. `evaluateGeometryWithMetadata()`
builds a caller-supplied metadata object and runs the same evaluator fan-out.

The ΔT extrapolation check uses the same decimal year the provider receives. If
`$deltaTOverride` is non-null, the override source is recorded and the
calibrated-range warning is suppressed.

### 10.4 Hijri

`GregorianToHijriConverter` gets a private conversion routine returning:

```php
[
    'date' => HijriDate,
    'resolvedCivilDate' => CivilDate,
    'offsetDays' => ?int,
    'maghribFallbackApplied' => bool,
]
```

`convert()` returns `['date']`. `convertWithMetadata()` uses the rest. The
maghrib probe returns its instant plus a fallback flag; the flag is aggregated
across the forward and backward sunset probes. The legacy path ignores the
flag but follows the same probe order and exception behavior.

The calendar provenance is read through `DescribesCalendar` when implemented.
A custom calendar that does not implement it gets a provenance object with the
stable key and null family/coverage/range/digest, plus the
`UNIDENTIFIED_CALCULATION_SOURCE` warning.

## 11. Compatibility and release policy

### 11.1 Additive surface

| Change | Classification | Compatibility note |
|---|---|---|
| New `SharedKernel` enums/value objects/interfaces | Minor | Additive. No existing type changes. |
| New context metadata/outcome classes | Minor | Additive. Existing result objects remain the payload. |
| New `*WithMetadata()` methods | Minor | Additive. Existing methods and return types unchanged. |
| New `PrayerMetadataCalculatorInterface` | Minor | Additive. It extends the existing calculator contract; existing implementers are not forced to adopt it. |
| `TimetableService::monthlyWithMetadata()` | Minor | New method; `monthly()` unchanged. Runtime check only on the new path. |
| `DescribesCalendar` on the three shipped calendars | Minor | Additive interface implementation. Custom calendars unaffected. |
| `IdentifiesCalculationSource` on built-in strategies/engines | Minor | Additive interface implementation. Custom strategies unaffected; they get an explicit unknown-source warning instead of a break. |
| `toArray()` on `QiblaResult`, `CrescentGeometry`, `CrescentVerdict`, `HijriDate`, `CalculationParameters`, `PrayerAdjustments` | Minor | Additive methods. Existing properties and `PrayerSchedule::toArray()` unchanged. |
| `CalculationMetadata::SCHEMA_VERSION` / metadata shape | Minor | New contract. Schema version is independent of package version. |
| `LibraryVersion::CURRENT` | Patch/minor per release | Checked-in value; release process updates it with the changelog. |

No public API is removed or deprecated in this slice. A future removal follows
the project's SemVer policy: deprecate for at least one minor release, provide a
migration note, then remove in a major release.

### 11.2 Output stability

- Existing numeric outputs must be bit-identical. The legacy methods keep
  their current code paths and the existing goldens prove no drift.
- Metadata paths may share private routines with legacy methods, but the
  legacy return values must compare equal in the compatibility tests.
- Existing `PrayerSchedule::toArray()` keys and order are unchanged. The
  metadata wrapper is new and does not modify the payload.
- Existing exception types and conditions are unchanged. Metadata is built
  only after a successful calculation; a failed calculation returns no partial
  outcome.
- Warnings are additive information. They do not change the result value.

### 11.3 Schema compatibility

- Schema `1.0` ships with Release A. A2/A3 implement the contexts already
  defined here; they do not change the common key set.
- Adding a new context or a new field is a minor schema bump. Consumers must
  ignore unknown keys.
- Removing a key, renaming a key, changing a unit, or changing the meaning of a
  key requires a major schema bump.
- `library_version` is the package version, not the schema version. A package
  release can ship the same schema version.
- Warning codes are stable within schema major version 1. Adding a code is a
  minor schema change; repurposing a code is a major schema change.
- Human messages are not part of the stable contract. Consumers must branch on
  `code` and `details`, not on message text.

### 11.4 Library version release process

`LibraryVersion::CURRENT` is the only runtime version source. Release
preparation updates it with `CHANGELOG.md` and the docs snapshot. During normal
development it may carry a `-dev` suffix; at a release tag it must equal the
exact `X.Y.Z` version. The release checklist must include this bump. No runtime
Composer metadata is read.

## 12. Testing and evidence

### 12.1 Baseline

The design is checked against the `v0.2.0` checkout:

- 607 tests, 50,536 assertions (`composer test-unit`);
- 32 architecture tests (`composer arch`);
- 95% line-coverage floor;
- MSI ≥ 85 mutation gate;
- benchmark tripwires 50 ms single-day / 30,000 ms year.

New code must not lower the coverage floor or weaken the architecture gates.

### 12.2 Test matrix

| Area | Required tests |
|---|---|
| Shared vocabulary | Enum values, warning/source/metadata validation, status derivation, deterministic ordering, schema version, `LibraryVersion` format. |
| Prayer metadata | Normal result, polar fallback, caller adjustments, elevation, `NONE` method/custom parameters, custom solar engine warning, source registry, exact JSON shape. |
| Timetable metadata | Per-day count/order, a day-specific polar fallback, injected non-metadata calculator throws `ConfigurationException`, legacy `monthly()` unchanged. |
| Qibla metadata | Spherical and WGS84 sources, exact shape, true-north fields, legacy result equality. |
| Moon metadata | Provider ΔT, caller override, extrapolated year (before 2005 and after 2050), no-moonset geometry, evaluation criteria, caller-supplied geometry, exact shape, legacy result equality. |
| Hijri metadata | Tabular, UQ, MABIMS country/digest, custom calendar warning, offset store with 0 and non-zero days, civil-date frame, sunset frame, maghrib-only fallback, exact shape, legacy result equality. |
| Serialization | `json_encode(..., JSON_THROW_ON_ERROR)` succeeds, no objects, no internal `Astro` names, nullable keys present, empty lists present. |
| Backward compatibility | For representative grids, legacy result equals metadata outcome result; existing `PrayerSchedule::toArray()` unchanged. |
| Architecture | New classes documented in `docs/user/api-reference.md`, docblocks complete, layering clean, final/readonly shape rules, contracts interfaces-only, no Astro leakage. |
| Wiring | New `docs/integrations.md` snippet executes verbatim. |
| Performance | Record legacy vs metadata single-day median and peak memory; confirm the legacy tripwires still pass. |

### 12.3 Snapshot policy

- Pin exact keys, key order, nullability, warning codes, warning details,
  source ids, and statuses.
- Do not pin human warning messages as stable identifiers. Assert they are
  non-empty and either normalize them in full-JSON snapshots or assert them
  separately.
- Do not pin `library_version` to a development literal. Assert it equals
  `LibraryVersion::CURRENT`, or normalize it in full-JSON snapshots.
- Keep numeric astronomy goldens untouched. Metadata tests are shape/behavior
  tests, not new numerical parity vectors.
- Use the shipped digest constants, not copied hashes, in test expectations.

### 12.4 Commands

Narrow checks first:

```bash
vendor/bin/phpunit --no-coverage --filter PrayerMetadata
vendor/bin/phpunit --no-coverage --filter Provenance
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

`composer mutation` is the PR-only Infection gate. Run it before opening a pull
request that changes `src/` behavior. `composer bench` must stay within the
enforced tripwires; the printed PRD numbers remain headroom references.

## 13. Documentation and release work

### 13.1 Contributor documentation

- **Create `docs/provenance.md`** — canonical contributor page:
  - why the metadata path exists and what it does not claim;
  - shared vocabulary and status precedence;
  - warning code table with details keys;
  - source-id registry and custom-component policy;
  - per-context metadata fields and JSON examples;
  - schema-version and library-version policy;
  - one reproducibility recipe (inputs + metadata + result).
- Add the page to `docs/README.md` and its counterpart column.
- **Update `ARCHITECTURE.md`** — add provenance as a cross-cutting public
  capability, show that metadata is built at orchestration boundaries, and keep
  `Astro` internal.
- **Update `docs/integrations.md`** — add an executable `calculateWithMetadata()`
  recipe. `tests/Wiring/CalculatorWiringTest.php` executes every fenced PHP
  block, so the snippet is checked.
- **Update `docs/prayer.md`, `docs/qibla.md`, `docs/moon.md`, `docs/hijri.md`**
  with the context-specific metadata fields and the warnings they can emit.
- **Update `AGENTS.md` and `CONTEXT.md`** when the phase lands: public surfaces,
  arch groups, and the new provenance contract.
- **Update `docs/README.md`** with the design-spec row and the new page.

### 13.2 User documentation

- **Create `docs/user/calculation-metadata.md`** — published guide:
  - when to use the metadata path and when the compact path is enough;
  - a complete prayer example with JSON;
  - how to store inputs + metadata + result for reproduction;
  - how to handle warning codes at an application boundary;
  - true-north Qibla versus caller-supplied magnetic declination;
  - Moon criterion versus observation/authority;
  - generated versus official Hijri tables;
  - schema-version compatibility.
- Add the page to `docs_site/astro.config.mjs` and to the docs map
  counterpart.
- **Update `docs/user/api-reference.md`** with every new class, interface,
  enum, method, and `toArray()` addition. `ArchApiReferenceTest` gates both
  directions.
- **Update `docs/user/integration.md`** with a short pointer to the metadata
  page; do not duplicate the full schema there.
- **Update `docs/user/migration.md`** with an "additive, no migration required"
  note and the two entry points users should adopt.
- **Update `docs/user/citations.md`** if source ids/digests are added to the
  published provenance guidance.

### 13.3 Changelog and release

- Add an `Added` entry to `CHANGELOG.md` for the provenance vocabulary, the
  opt-in methods, the serializers, and the docs pages.
- Run `composer changelog` to regenerate `docs/user/changelog.md`; never edit
  the generated page by hand.
- Run `composer changelog:check` before commit.
- Do not snapshot the docs site or tag a release as part of the implementation
  PR. The release process freezes docs after the phase is green.
- When the phase ships, update `README.md`/`CONTEXT.md` status and the API
  reference floor notes only if the count changes; the existing API-reference
  non-vacuity floor remains valid because the new count is higher.

## 14. Implementation sequence

The first slice is delivered in three independently reviewable units. A1 is the
first executable plan; A2/A3 reuse the frozen shared vocabulary. Each unit
must leave `composer ci` green and must not change legacy numeric output.

### A1 — Shared vocabulary and prayer/timetable

**Goal.** Land the shared provenance types and the complete prayer/timetable
metadata path.

**Creates**

- `src/SharedKernel/Enums/CalculationContext.php`
- `src/SharedKernel/Enums/CalculationStatus.php`
- `src/SharedKernel/Enums/WarningCode.php`
- `src/SharedKernel/Enums/SourceKind.php`
- `src/SharedKernel/ValueObjects/CalculationWarning.php`
- `src/SharedKernel/ValueObjects/CalculationSource.php`
- `src/SharedKernel/ValueObjects/CalculationMetadata.php`
- `src/SharedKernel/ValueObjects/LibraryVersion.php`
- `src/SharedKernel/Contracts/IdentifiesCalculationSource.php`
- `src/Prayer/ValueObjects/PrayerCalculationMetadata.php`
- `src/Prayer/ValueObjects/PrayerCalculationOutcome.php`
- `src/Prayer/Contracts/PrayerMetadataCalculatorInterface.php`

**Modifies**

- `src/Prayer/Services/PrayerTimesCalculator.php` — private trace plus
  `calculateWithMetadata()`.
- `src/Prayer/Services/NightResolver.php` — return the selected mode beside the
  two instants for the source list; no numeric change.
- `src/Prayer/Services/TimetableService.php` — `monthlyWithMetadata()`.
- `src/Prayer/ValueObjects/CalculationParameters.php` — `toArray()`.
- `src/Prayer/ValueObjects/PrayerAdjustments.php` — `toArray()`.
- `src/Prayer/Enums/CalculationMethod.php` — `SOURCE_VERSION` for the preset
  source id.
- `src/Astro/Services/MeanSolarCalculator.php` and the prayer strategy classes —
  `IdentifiesCalculationSource` implementations.
- `docs/user/api-reference.md`, `docs/integrations.md`, `docs/README.md`,
  `docs/provenance.md` (new), `docs/user/calculation-metadata.md` (new),
  `docs_site/astro.config.mjs`, `ARCHITECTURE.md`, `CHANGELOG.md`,
  `docs/user/changelog.md` (generated).

**Tests**

- `tests/SharedKernel/Provenance/` unit tests.
- `tests/Prayer/PrayerMetadataTest.php` (including the night-mode source table).
- `tests/Prayer/TimetableMetadataTest.php`.
- `tests/Prayer/ProvenanceBackwardCompatibilityTest.php`.
- `tests/ArchShapeTest.php` — extend the allowed contract-name lists with
  `IdentifiesCalculationSource` and `PrayerMetadataCalculatorInterface`.
- `tests/Wiring/CalculatorWiringTest.php` picks up the new docs snippet.
- API-reference and arch gates.

**Exit gate.** Shared vocabulary and prayer/timetable metadata are documented,
tested, mutation-covered, and green; legacy prayer output is unchanged.

### A2 — Qibla and Moon

**Goal.** Reuse the frozen vocabulary for the geodesy and crescent paths.

**Creates**

- `src/Qibla/ValueObjects/QiblaCalculationMetadata.php`
- `src/Qibla/ValueObjects/QiblaCalculationOutcome.php`
- `src/Moon/ValueObjects/MoonCalculationMetadata.php`
- `src/Moon/ValueObjects/CrescentGeometryOutcome.php`
- `src/Moon/ValueObjects/CrescentEvaluationOutcome.php`

**Modifies**

- `src/Qibla/Services/QiblaCalculator.php` — `calculateWithMetadata()`.
- `src/Qibla/Strategies/SphericalCalculator.php`,
  `src/Qibla/Strategies/Wgs84KarneyCalculator.php` — source identity.
- `src/Qibla/ValueObjects/QiblaResult.php` — `toArray()`.
- `src/Moon/Services/CrescentCalculator.php` — trace plus three metadata
  methods.
- `src/Moon/Services/EphemerisConjunctionSearch.php`,
  `src/Moon/Strategies/*Evaluator.php`, `src/Astro/Services/CrescentEphemeris.php`,
  `src/Astro/Services/DeltaTProvider.php` — source identity/constants.
- `src/Moon/ValueObjects/CrescentGeometry.php`,
  `src/Moon/ValueObjects/CrescentVerdict.php` — `toArray()`.
- The context docs, API reference, changelog, and user metadata page.

**Exit gate.** Qibla and Moon metadata are documented and tested; ΔT
extrapolation and no-moonset are visible without parsing exceptions; legacy
results unchanged.

### A3 — Hijri

**Goal.** Reuse the vocabulary for calendar and sunset-frame provenance.

**Creates**

- `src/Hijri/Enums/HijriDateFrame.php`
- `src/Hijri/Enums/CalendarFamily.php`
- `src/Hijri/Enums/CalendarCoverage.php`
- `src/Hijri/ValueObjects/CalendarProvenance.php`
- `src/Hijri/Contracts/DescribesCalendar.php`
- `src/Hijri/ValueObjects/HijriCalculationMetadata.php`
- `src/Hijri/ValueObjects/HijriCalculationOutcome.php`

**Modifies**

- `src/Hijri/Services/GregorianToHijriConverter.php` — trace plus
  `convertWithMetadata()`.
- `src/Hijri/Strategies/TabularCalendar.php`,
  `src/Hijri/Strategies/UmmAlQuraCalendar.php`,
  `src/Hijri/Strategies/MabimsCalendar.php` — `DescribesCalendar`.
- `src/Hijri/ValueObjects/HijriDate.php` — `toArray()`.
- The context docs, API reference, changelog, and user metadata page.

**Exit gate.** Hijri metadata identifies calendar key/family/coverage,
country/proxy, span, digest, offset source, frame, and maghrib-only fallback;
legacy results unchanged.

### Why not one big-bang unit

The shared vocabulary is the risky part: if it is wrong, every context is
wrong. A1 proves it on the most-used pipeline, including the polar-fallback
trace that is the hardest metadata case. A2 and A3 then add context-specific
fields without reopening the common shape. The improvement report's decision 1
explicitly prefers this sequencing: a shared outcome shape only after its common
fields are proven across contexts.

## 15. Acceptance criteria mapping

| Report requirement | Where this spec satisfies it | Phase |
|---|---|---|
| A schedule can be audited from serialized inputs and metadata alone. | Prayer outcome contains requested/effective inputs, full policy snapshot, adjustments, fallback, rounding, sources, and warnings. | A1 |
| A polar fallback and a normal calculation are distinguishable without parsing exception strings. | `status = fallback_applied`, `polar_fallback_applied` warning, effective inputs. | A1 |
| Metadata has a documented JSON shape and compatibility policy. | Sections 9 and 11; `docs/provenance.md`; user page. | A1 |
| Golden tests include representative metadata snapshots. | Section 12.3 snapshot policy and the A1 test list. | A1 |
| Existing methods and return types stay unchanged. | Sections 5, 6, and 11; backward-compatibility tests. | A1–A3 |
| Metadata is built from the same calculation run. | Section 10 internal trace rules. | A1–A3 |
| A timetable carries metadata for each day, including day-specific fallback. | `monthlyWithMetadata()` returns one outcome per day. | A1 |
| Qibla reports true-north reference and does not claim magnetic provenance. | `heading_reference`, null declination, P2.3 deferral. | A2 |
| Moon result identifies ephemeris/ΔT and evaluator criteria separately. | `MoonCalculationMetadata` fields and source registry. | A2 |
| Hijri result identifies calendar key, country/proxy, span, digest, correction, and sunset frame. | `HijriCalculationMetadata` and `CalendarProvenance`. | A3 |
| Warnings are typed, stable codes in deterministic order. | Sections 6.2, 7, and 9. | A1–A3 |
| No internal engine objects in the public report. | Section 5; source ids replace objects; serialization tests. | A1–A3 |
| Library version is available without Composer runtime metadata. | `LibraryVersion::CURRENT` and section 11.4. | A1 |
| Schema version is independent of package version. | `CalculationMetadata::SCHEMA_VERSION` and section 11.3. | A1 |
| Docs, API reference, contributor appendix, changelog move together. | Section 13 and each phase's exit gate. | A1–A3 |
| Benchmark impact is measured. | Section 12.2 and `composer bench` in section 12.4. | A1–A3 |

One report field, "requested time zone", is intentionally absent from prayer
metadata: `PrayerTimesCalculator::calculate()` has no zone input and returns
UTC instants for a zone-free `CivilDate`, so there is no requested zone to
record. The Hijri metadata records the caller's instant zone because that
frame consumes it. Display-zone provenance and DST policy remain P1.2.

## 16. Risks and trade-offs

| Risk | Mitigation |
|---|---|
| The public API grows by many small types. | Keep the shared vocabulary small and frozen; split A1/A2/A3; use the API-reference gate; do not add per-context base classes or a god metadata bag. |
| Metadata drifts from the calculation. | One internal trace per service, no recomputation, no exception parsing, backward-compatibility tests. |
| Source ids become unstable. | Documented registry, class constants, lowercase ids, schema major version, tests for built-in ids. |
| Custom strategies silently lose provenance. | `IdentifiesCalculationSource` opt-in plus an explicit `unidentified_calculation_source` warning and `custom.<component>` id. |
| Status semantics become a second truth. | The warning table and precedence are the only mapping; `CalculationMetadata::create()` owns derivation; tests pin every transition. |
| Serialization and PHP API drift apart. | `toArray()` is canonical for this slice; P1.6 may add schemas but cannot introduce a second serializer that disagrees. |
| Library version drifts from the changelog/tag. | One checked-in constant, release checklist, format test; no runtime package metadata. |
| Warnings become noisy or imply false precision. | Small initial code set; warnings state limitations, not new precision; human messages are not identifiers. |
| Moon `decision_required` surprises users. | Document that it is not a failure and that criterion scores are not observations; keep the legacy method free of the status. |
| Metadata path slows the legacy path. | Legacy path ignores metadata objects; only a small trace array is allocated; benchmark and revert to a lazy trace if the tripwire moves materially. |
| Hijri generated tables look official. | `coverage = generated_table`, `authority = null` for `uqu`, warning `generated_calendar_table`; `official_table` is reserved for a separately sourced dataset. |

## 17. Deferred decisions

These are intentionally not decided here; none blocks A1.

- Full per-model applicability intervals and validity statements (P0.2).
- Warning promotion policy and exception classes for warning codes (P0.2).
- High-latitude safe-cap application traces (P0.2 may extend the NightResolver
  trace; A1 records the selected mode and configured rule, not whether a cap
  changed a value).
- JSON Schema files, cross-language contract tests, and schema generation
  (P1.6).
- Localized warning/status messages and locale packs (P1.1).
- Civil-date/time-zone context and DST gap/fold policy (P1.2).
- Official calendar datasets and their licenses (P1.3).
- Profile registry and calculation-method aliases (P1.4).
- Convenience facade (P1.5).
- Magnetic declination provider, epoch, and uncertainty (P2.3).
- Caches, batch APIs, and timetable partial-failure policy (P2.5).
- Sunnah and NextPrayer metadata envelopes (later; they consume schedules).
- Whether `Astro` symbols can ever be promoted to public API (P2.6).

## 18. Next step

Write the A1 implementation plan from this spec. It should be a task-by-task
TDD plan with exact files, failing tests first, minimal implementations, and
verification commands, following the repository's `docs/plans/` convention. A2
and A3 plans follow after A1 lands and its shared vocabulary is proven.

The A1 plan must include:

1. failing tests for the shared enums/value objects and deterministic
   ordering;
2. failing tests for `CalculationParameters::toArray()` and
   `PrayerAdjustments::toArray()`;
3. failing tests for the prayer metadata trace, polar fallback, custom source
   warning, and timetable metadata;
4. the minimal implementation for each;
5. the docs/API/changelog updates;
6. `composer ci`, `composer arch`, `composer mutation`, and `composer bench`
   evidence.

<!-- END -->

