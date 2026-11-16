# Calculation provenance

The provenance surface answers one question about a finished calculation:
*which inputs, policy, and algorithm identities produced this result, and did
anything replace or degrade them?* It is an audit trail, not a data-flow trace
and not a second calculation.

The frozen contract lives in
[design/2026-11-15-provenance-explainability-foundation.md](design/2026-11-15-provenance-explainability-foundation.md).
This page is the working summary for contributors; the spec is the decision
record. A1 shipped the shared vocabulary and the prayer/timetable path; Qibla,
Moon, and Hijri metadata remain future phases.

## What it claims and does not claim

It claims:

- the requested inputs, plus the effective inputs when a fallback replaced them;
- the exact policy that ran (`CalculationParameters`), including both
  adjustment layers;
- the stable ids of the components the public service directly orchestrated;
- machine-readable warnings when a component was unidentified or a fallback was
  applied;
- a derived status that ranks those warnings.

It does not claim:

- a per-value dependency trace. A listed component ran, but it may not have
  changed any instant. Replacements and extrapolation are recorded by warnings,
  status, and `effective_input`, not by a value-level graph;
- that a criterion, calendar table, or calculation is an official authority.
  Warnings such as `crescent_criterion_is_not_observation` (A2) and
  `generated_calendar_table` (A3) exist precisely to deny that claim;
- a clock timestamp, host name, or random id. Metadata is deterministic: the
  same inputs, policy, library version, and wired components produce the same
  metadata bytes.

## Shared vocabulary

All contexts share the `SharedKernel` namespace:

| Type | Purpose |
|---|---|
| `Enums\CalculationContext` | `prayer`, `qibla`, `moon`, `hijri` |
| `Enums\CalculationStatus` | Derived outcome rank |
| `Enums\SourceKind` | `algorithm`, `generated_data`, `official_data`, `caller_input` |
| `Enums\WarningCode` | Stable warning identifiers |
| `ValueObjects\CalculationMetadata` | Common envelope: schema/context/status/version/warnings/sources |
| `ValueObjects\CalculationSource` | One algorithm/data dependency |
| `ValueObjects\CalculationWarning` | One machine-readable warning |
| `ValueObjects\LibraryVersion` | `CURRENT`, the single runtime version source |
| `Contracts\IdentifiesCalculationSource` | `calculationSource(): CalculationSource` capability |

Status precedence, lowest to highest:

```text
complete < extrapolated < fallback_applied < indeterminate < decision_required
```

`CalculationMetadata::create()` derives the status from the warning codes and
keeps the highest rank. Warnings are sorted and de-duplicated but never
filtered by status.

Determinism rules:

- warnings sort by code, then message, then the scalar detail map in key order;
- sources sort by id, then kind, then version with null last;
- identical warnings and identical sources collapse to one entry after sorting;
- the common serialization order is fixed: `schema_version`, `context`,
  `status`, `library_version`, `warnings`, `sources`.

## Warning codes

A1 emits exactly two codes: `polar_fallback_applied` and
`unidentified_calculation_source`. The remaining rows are defined now so A2/A3
implement a frozen vocabulary rather than inventing codes later.

| Code | Emitted by | Status effect | Detail keys |
|---|---|---|---|
| `delta_t_extrapolated` | Moon, provider ΔT outside 2005–2050 | `extrapolated` | `year`, `calibrated_from_year`, `calibrated_to_year` |
| `polar_fallback_applied` | Prayer, polar handler invoked | `fallback_applied` | `rule`, `requested_latitude`, `requested_longitude`, `effective_latitude`, `effective_longitude`, `requested_date`, `effective_date` |
| `sunset_frame_maghrib_fallback` | Hijri, sunset frame uses `maghribAt()` | `fallback_applied` | `date`, `reason` |
| `moonset_indeterminate` | Moon, no moonset on the civil date | `indeterminate` | `date`, `reason` |
| `crescent_criterion_is_not_observation` | Moon evaluation | `decision_required` | `criteria` (comma-separated, fixed order) |
| `generated_calendar_table` | Hijri UQ/MABIMS | none | `calendar`, `range` |
| `tabular_calendar_estimate` | Hijri tabular | none | `adjustment_days` |
| `unidentified_calculation_source` | Any custom component without `IdentifiesCalculationSource` | none | `component`, `source_id` |

`reason` values in the frozen vocabulary:

- `sunset_frame_maghrib_fallback`: `full_schedule_undefined`.
- `moonset_indeterminate`: `no_moonset_on_civil_date`.

`unidentified_calculation_source.source_id` is always `custom.<component>`.
`component` values are fixed: `solar`, `high_latitude`, `polar`, `twilight`,
`isha`, `maghrib`, `geodesy`, `ephemeris`, `conjunction_search`, `criterion`,
`calendar`, `offset`.

A warning never makes an invalid request succeed. An out-of-range table lookup
still raises `HijriOutOfRangeException`; a polar day with
`PolarCircleRule::NONE` still raises `UndefinedScheduleException`. Metadata is
built only after a successful calculation — there is no partial outcome.

Human-readable `message` text is not part of the contract. Branch on `code`
and `details`.

## Source registry

Ids are stable within schema major version `1`. Each built-in strategy or
engine that participates through a contract exposes its id as a `SOURCE_ID`
class constant and implements `IdentifiesCalculationSource`; the constant is
the single source of truth. Synthetic sources (method presets, caller inputs)
are built by the service from the documented id.

A1 prayer registry:

| Source id | Kind | Version/range | Owner |
|---|---|---|---|
| `prayer.method.<enum value>` | `ALGORITHM` | `method-defaults-v1` | `CalculationMethod` preset |
| `solar.mean-meeus` | `ALGORITHM` | `meeus-2ed` | `Astro\Services\MeanSolarCalculator` |
| `prayer.high-latitude.middle_of_night` | `ALGORITHM` | — | `MiddleOfNightCorrection` |
| `prayer.high-latitude.seventh_of_night` | `ALGORITHM` | — | `SeventhOfNightCorrection` |
| `prayer.high-latitude.twilight_angle` | `ALGORITHM` | — | `TwilightAngleCorrection` |
| `prayer.high-latitude.seasonal_twilight` | `ALGORITHM` | — | `SeasonAdjustedTwilight` (also the twilight model) |
| `prayer.polar.nearest_latitude` | `ALGORITHM` | — | `NearestLatitudeHandler` |
| `prayer.polar.nearest_day` | `ALGORITHM` | — | `NearestDayHandler` |
| `prayer.polar.makkah` | `ALGORITHM` | — | `MakkahHandler` |
| `prayer.polar.none` | `ALGORITHM` | — | `ThrowHandler` (policy-only; never emitted) |
| `prayer.isha.angle` | `ALGORITHM` | — | `AngleIsha` |
| `prayer.isha.interval` | `ALGORITHM` | — | `IntervalIsha` |
| `prayer.maghrib.angle` | `ALGORITHM` | — | `AngleMaghrib` |
| `prayer.maghrib.interval` | `ALGORITHM` | — | `MinutesAfterSunsetMaghrib` |
| `caller.adjustments` | `CALLER_INPUT` | — | non-empty caller adjustment layer |

Reserved ids for later phases (defined in the spec, not emitted in A1):
`qibla.spherical`, `qibla.wgs84-karney`, `moon.ephemeris.meeus-2ed`,
`moon.conjunction-search.meeus-hourly`,
`moon.delta-t.espenak-2005-2050`, `caller.delta-t`,
`moon.criterion.yallop`, `moon.criterion.odeh`,
`moon.criterion.mabims-1992`, `moon.criterion.neo-mabims-2021`,
`hijri.maghrib-probe`, `hijri.offset.caller`.

Prayer dependency rules:

- the solar engine, Isha rule, Maghrib rule, and method source are always
  listed. Built-ins use `solar.mean-meeus`, the matching `prayer.isha.*` /
  `prayer.maghrib.*` id, and `prayer.method.<enum value>`; custom components
  contribute their own id or `custom.<component>`;
- `MOON_SIGHTING_COMMITTEE` always takes the seasonal branch, even when its
  parameters carry `HighLatitudeRule::NONE`, so it lists
  `prayer.high-latitude.seasonal_twilight` rather than omitting a night source;
- the night correction is listed only in `portions` mode;
- the twilight model is listed only in `seasonal` mode;
- the polar handler is listed only when it actually ran;
- `caller.adjustments` is listed only when the caller adjustment layer is
  non-empty (method defaults are part of `policy.method` and do not add it).

`NightResolver::resolve()` returns the selected mode beside `fajr` and `isha`
so the calculator applies this table without a second copy of the mode
predicate. The mode is an internal trace, not public metadata.

The shared engine id is `solar.mean-meeus`, not `prayer.solar.mean-meeus`,
because the Moon path uses the same engine. Context-specific ids describe
context-specific algorithms; shared engine ids stay context-neutral.

`CalculationMethod::SOURCE_VERSION` (`method-defaults-v1`) is a contract
separate from the package version. Bump it whenever any preset default
changes, so metadata pins the defaults that actually ran.

### Custom components

A custom component may implement `IdentifiesCalculationSource` and return any
valid id; its identity is reported as-is.

A component that does not implement the interface gets
`CalculationSource::algorithm('custom.' . $component)` plus an
`UNIDENTIFIED_CALCULATION_SOURCE` warning. The library never guesses an id from
a class name or a rule's numeric shape. Custom ids are namespaced under
`custom.` so they cannot collide with the built-in registry.

## Prayer metadata

`PrayerCalculationMetadata::toArray()` extends the common six keys with:

| Key | Meaning |
|---|---|
| `input` | Coordinates and civil date the caller requested |
| `effective_input` | Coordinates and date actually computed with |
| `policy` | `CalculationParameters::toArray()` snapshot |
| `fallback` | `applied`, `kind` (`polar` or null), `rule`; `applied` means the handler replaced the date, the location, or both |
| `rounding` | `half_up_to_minute_utc` |

The `policy` snapshot expands both rule objects with both keys and records
the Dhuhr/Imsak/Ishraq/Dhuha offsets plus both adjustment layers. It is a
record of what ran, not a re-input grammar.

`fallback.rule` reports the rule the wired handler actually applied, while
`policy.polar_circle_rule` records the configuration the caller passed.
`default()` always wires `NearestLatitude`, so the two differ when callers pass
parameters selecting another rule; use `defaultFor($parameters)` when the wiring
should follow the policy.

`PrayerCalculationOutcome::toArray()` wraps `result` (the unchanged
`PrayerSchedule::toArray()` payload) and `metadata`.

The published guide owns the canonical full payload example:
[Calculation Metadata](user/calculation-metadata.md#one-prayer-calculation).
This page defines the vocabulary and dependency rules that example
demonstrates.

A polar fallback changes up to three things at once, and they must agree:
`fallback` becomes `{"applied": true, "kind": "polar", "rule": "nearest_latitude"}`,
the warning carries the requested/effective pair, and `effective_input` carries
the replacement coordinates and/or date. `NearestLatitude` and `Makkah` replace
the location, `NearestDay` replaces the date, and a custom handler may replace
both. The handler source (for example `prayer.polar.nearest_latitude`) is added
to `sources`.

## Schema and library versions

- `CalculationMetadata::SCHEMA_VERSION` is `1.0`. It is independent of the
  package version.
- `library_version` is `LibraryVersion::CURRENT`, the single runtime version
  source. Release preparation updates it with `CHANGELOG.md`; during
  development it carries a `-dev` suffix.
- Adding a context or a field is a minor schema bump; consumers must ignore
  unknown keys. Removing or renaming a key, changing a unit, or changing a
  meaning requires a major schema bump.
- Warning codes are stable within schema major version `1`. Adding a code is
  minor; repurposing one is major.

## Reproducibility recipe

To reproduce a result from stored metadata, feed back exactly:

1. `input` — the requested coordinates and date (not `effective_input`; a
   fallback must be able to run again);
2. `policy` — the full scalar snapshot captured when the outcome was built.
   Reconstruct `CalculationParameters` through `create()`/withers; the rule
   blocks record both keys and set the unused one to null. The snapshot cannot
   distinguish an unset prayer from an explicit zero, so do not rebuild the
   caller layer by applying all ten zero-filled `PrayerAdjustments::toArray()`
   values with `with()`: that turns an originally empty layer into a non-empty
   one. Leave it as `PrayerAdjustments::empty()` when `sources` does not list
   `caller.adjustments`; when it does, apply the recorded values (a zero-only
   layer can use one zero entry and serializes identically);
3. the same `library_version` — engine, preset defaults, and ids can change
   across releases even when the schema does not;
4. the same calculator wiring — the payload does not record injected engines
   or strategies. Use the same factory (`default()` / `defaultFor($parameters)`)
   or inject the same implementations with the same configuration. A different
   solar engine or high-latitude/polar strategy can change times, warnings, or
   source identities without changing `policy`.

Then compare `result` with the stored payload and check that `sources`,
`warnings`, `status`, and `effective_input` match. A mismatch in `sources`
with equal times means the dependency set changed; a mismatch in `status`
means the replacement/extrapolation story changed.

The legacy methods remain the compact path and keep their current behavior:
`calculate()`, `monthly()`, and the other pre-existing methods return the same
values and throw the same exceptions. They expose no warnings. A caller that
wants provenance opts in with `calculateWithMetadata()` /
`monthlyWithMetadata()`.

## Implementation notes

- Metadata is built at orchestration boundaries (`PrayerTimesCalculator`,
  `TimetableService`), never inside `Astro/`.
- `calculate()` and `calculateWithMetadata()` share one private `run()`; the
  legacy path returns `run()['schedule']`, so the numeric pipeline cannot
  drift between them.
- `PrayerMetadataCalculatorInterface` extends `PrayerTimesCalculatorInterface`.
  Existing implementers are not forced to adopt it; `monthlyWithMetadata()`
  rejects an injected calculator that lacks it with a
  `ConfigurationException` instead of silently dropping provenance.
- Exception behavior is unchanged. `calculateWithMetadata()` throws exactly
  what `calculate()` throws; metadata is constructed only on success.
