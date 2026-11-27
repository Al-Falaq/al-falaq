# Calculation provenance

The provenance surface answers one question about a finished calculation:
*which inputs, policy, and algorithm identities produced this result, and did
anything replace or degrade them?* It is an audit trail, not a data-flow trace
and not a second calculation.

The frozen contract lives in
[design/2026-11-15-provenance-explainability-foundation.md](design/2026-11-15-provenance-explainability-foundation.md).
This page is the working summary for contributors; the spec is the decision
record. A1 shipped the shared vocabulary and the prayer/timetable path; A2
added Qibla and Moon metadata; A3 added Hijri calendar metadata. The
provenance surface is complete for prayer, Qibla, Moon, and Hijri.

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

A1, A2, and A3 emit eight codes: `polar_fallback_applied` and
`unidentified_calculation_source` (A1), `delta_t_extrapolated`,
`moonset_indeterminate`, and `crescent_criterion_is_not_observation` (A2), and
`sunset_frame_maghrib_fallback`, `generated_calendar_table`, and
`tabular_calendar_estimate` (A3).

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

A2 Qibla registry:

| Source id | Kind | Version/range | Owner |
|---|---|---|---|
| `qibla.spherical` | `ALGORITHM` | `todhunter-haversine` | `SphericalCalculator` |
| `qibla.wgs84-karney` | `ALGORITHM` | `geographiclib-2.1` | `Wgs84KarneyCalculator` |

A2 Moon registry:

| Source id | Kind | Version/range | Owner |
|---|---|---|---|
| `moon.ephemeris.meeus-2ed` | `ALGORITHM` | `meeus-2ed` | `CrescentEphemeris` |
| `moon.conjunction-search.meeus-hourly` | `ALGORITHM` | `meeus-hourly` | `EphemerisConjunctionSearch` |
| `moon.delta-t.espenak-2005-2050` | `ALGORITHM` | range `2005..2050` | `DeltaTProvider` (identity via `CrescentCalculator`) |
| `moon.criterion.yallop` | `ALGORITHM` | — | `YallopEvaluator` |
| `moon.criterion.odeh` | `ALGORITHM` | — | `OdehEvaluator` |
| `moon.criterion.mabims-1992` | `ALGORITHM` | — | `Mabims1992Evaluator` |
| `moon.criterion.neo-mabims-2021` | `ALGORITHM` | — | `NeoMabims2021Evaluator` |
| `caller.delta-t` | `CALLER_INPUT` | — | non-null ΔT override |

A3 Hijri registry:

| Source id | Kind | Version/range | Owner |
|---|---|---|---|
| `hijri.maghrib-probe` | `ALGORITHM` | — | `GregorianToHijriConverter` (sunset frame) |
| `hijri.offset.caller` | `CALLER_INPUT` | — | non-null `OffsetStore` |

The calendar itself is reported in the `calendar` block, never as a
`CalculationSource`; see [Hijri metadata](#hijri-metadata). A caller-supplied
`OffsetStore` is caller-owned by contract, so it is reported as
`hijri.offset.caller` and never as an unidentified component.

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

## Qibla metadata

`QiblaCalculator::calculateWithMetadata()` returns a `QiblaCalculationOutcome`:
the unchanged `QiblaResult` plus `QiblaCalculationMetadata`. The published
example is
[Calculation Metadata → Qibla](user/calculation-metadata.md#qibla).

Qibla dependency rule:

- exactly one geodesy source is listed: `qibla.spherical` from `default()` or
  `qibla.wgs84-karney` from `wgs84()`;
- a custom `GeodesyCalculator` without `IdentifiesCalculationSource` is
  reported as `custom.geodesy` with an `unidentified_calculation_source`
  warning. There is no fallback state: either the wired geodesy ran and is
  named, or its identity is explicitly unknown.

| Key | Meaning |
|---|---|
| `input` | Coordinates the caller requested |
| `heading_reference` | Always `true_north` |
| `magnetic_declination_degrees` | Always `null` until a sourced magnetic model ships |

`QiblaCalculationOutcome::toArray()` wraps `result`
(`QiblaResult::toArray()`: `direction_degrees`, `distance_km`) and `metadata`.
The true-north policy is unchanged from A1: a consumer that displays a compass
bearing applies its own declination and states the model and date.

## Moon metadata

The Moon path has two opt-in levels. `atSunsetWithMetadata()` returns
`CrescentGeometryOutcome` (geometry plus provenance);
`evaluateWithMetadata()` and `evaluateGeometryWithMetadata()` return
`CrescentEvaluationOutcome` (geometry, four verdicts, plus provenance).
`MoonCalculationMetadata` extends the common six keys with `input`, `delta_t`,
`geometry_source`, and `criteria`.

Moon dependency rules:

- a geometry outcome lists `solar.mean-meeus`, `moon.ephemeris.meeus-2ed`,
  `moon.conjunction-search.meeus-hourly`, and the ΔT source
  (`moon.delta-t.espenak-2005-2050`, or `caller.delta-t` for an override);
- an evaluation outcome adds the four `moon.criterion.*` sources; the common
  source list serializes them in id order (`mabims-1992`, `neo-mabims-2021`,
  `odeh`, `yallop`) like every other source list;
- caller-supplied geometry (`evaluateGeometryWithMetadata()`) lists only the
  four criteria: the bundle carries no coordinates, date, or ΔT, so those
  fields are `null` and `geometry_source` is `caller_supplied`;
- the ΔT provider's decimal year is the same value the provider receives:
  `2050-01-01` is exactly `2050.0` (complete), `2050-01-02` is past the
  calibrated endpoint (extrapolated);
- a no-moonset evening keeps finite geometry with null lag/age and adds
  `moonset_indeterminate`; caller-supplied geometry serializes
  `details.date = null` because the bundle carries no date;
- every evaluation adds `crescent_criterion_is_not_observation` and therefore
  derives at least `decision_required`: a verdict is not a sighting.

| Key | Meaning |
|---|---|
| `input` | Requested coordinates and civil date, or nulls for caller-supplied geometry |
| `delta_t` | `seconds`, `origin` (`provider`/`override`), and the provider's calibrated years (null for overrides and caller-supplied geometry) |
| `geometry_source` | `calculated` or `caller_supplied` |
| `criteria` | Empty for geometry outcomes; the fixed four-criterion order for evaluations |

`CrescentGeometryOutcome::toArray()` wraps `result`
(`CrescentGeometry::toArray()`) and `metadata`.
`CrescentEvaluationOutcome::toArray()` wraps
`result.geometry` + `result.verdicts` (fixed key order, each
`CrescentVerdict::toArray()`) and `metadata`.

## Hijri metadata

`GregorianToHijriConverter::convertWithMetadata()` returns a
`HijriCalculationOutcome`: the unchanged `HijriDate` plus
`HijriCalculationMetadata`. It takes the same arguments as `convert()`. The
published example is
[Calculation Metadata → Hijri](user/calculation-metadata.md#hijri).

`HijriCalculationMetadata` extends the common six keys with `input`,
`effective`, `calendar`, `offsets`, and `fallback`:

| Key | Meaning |
|---|---|
| `input` | `instant` (UTC), `zone`, `frame` (`civil_date` or `sunset_rollover`), and the frame's `coordinates`/`parameters` (both null for the civil frame) |
| `effective` | `gregorian_date` actually converted after the frame and rollover decision |
| `calendar` | `CalendarProvenance::toArray()` |
| `offsets` | `days` (`null` when no store answered) and `applied` (`days !== null`) |
| `fallback` | `applied` and `kind` (`maghrib_only` or null) |

The three built-in calendars describe themselves through `DescribesCalendar`:

| Calendar | `key` | `family` | `coverage` | `authority` | `range` | `digest` | `source` | `license` |
|---|---|---|---|---|---|---|---|---|
| `TabularCalendar` | `tabular` | `tabular` | `arithmetic_rule` | `null` | `1900-01-01..2100-12-31 CE` | `null` | `null` | `null` |
| `UmmAlQuraCalendar` | `uqu` | `umm_al_qura` | `generated_table` | `null` | `1423-01..1500-12 AH` | `21903bf9442b546908ab18a62f173e2011c760d1247a6cfcf46bb8f7584c0f9f` | `tools/gen_hijri.py @ a7baa8b202790d3bc84c47a610ac763be6c0b7e7` | `MIT` |
| `MabimsCalendar` | `mabims` | `mabims` | `generated_table` | `MY`, `ID`, `SG`, or `BN` | `1445-01..1500-12 AH` | per-country SHA-256 (below) | `tools/gen_hijri.py @ a7baa8b202790d3bc84c47a610ac763be6c0b7e7` | `MIT` |

MABIMS per-country digests (`MabimsMonthStarts::digestFor($country)`):

- MY `4b3e2a9a62ea1683616219b4373e988364dddb4f710bd6793369c2e3a5bd70fc`
- ID `56e6ea28b91c77503e2577d35d41013033790bff7740a2c48bfc8bc5008658bc`
- SG `38d2042f37ae6b380ad68083843b716d2bf8fed27ec45f34ed0130bab07b6197`
- BN `6f1ddde50b696caa517575bde93008a9d6ae98e517221bd40dd886ba0885c20f`

Rules:

- The calendar is reported in `calendar`, not `sources`. A calendar is a
  conversion system, not a `CalculationSource`; only the maghrib probe and a
  supplied offset store contribute sources.
- A sunset-frame conversion adds `hijri.maghrib-probe`. A supplied
  `OffsetStore` adds `hijri.offset.caller` regardless of its answer. A
  civil-frame conversion with no store has an empty `sources` list.
- `offsets.days` distinguishes no store (`null`) from a real zero (`0`).
  `offsets.applied` is derived as `days !== null`: it means a store answered,
  not that the date moved. A store returning `0` still lists
  `hijri.offset.caller` and serializes `applied: true`.
- A maghrib-only probe sets `fallback.applied = true`,
  `fallback.kind = maghrib_only`, and emits
  `sunset_frame_maghrib_fallback` with `reason = full_schedule_undefined`.
  The warning's `date` detail is the resolved civil date — the effective
  Gregorian date — not necessarily the day whose individual probe fell back.
  The flag is aggregated across the forward and backward probes, so a fallback
  in the backward probe is reported against the effective (current UTC) day.
  The warning is visible even when the resolved date is unchanged.
- Coverage drives the coverage warning, not the class name:
  `generated_table` emits `generated_calendar_table` with `calendar` and
  `range`; `arithmetic_rule` emits `tabular_calendar_estimate` with
  `adjustment_days`; `official_table` emits no coverage warning. No shipped
  calendar claims `official_table`. The `adjustment_days` detail is the
  `OffsetStore` answer (0 when no store answered), not `TabularCalendar`'s
  constructor `adjustmentDays`: that arithmetic nudge is applied inside
  `fromGregorian()` and A3 metadata has no separate field for it.
- A custom calendar without `DescribesCalendar` is reported in `calendar` as
  its `name()` with null family/coverage, and emits
  `unidentified_calculation_source` with `source_id = custom.calendar`. It adds
  no source entry.
- `MabimsCalendar::name()` is the generic `mabims` for every country. Key
  per-country corrections and comparisons by `calendar.authority`, not
  `calendar.key`.

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
   Reconstruction and comparison are documented in
   [Storing a result for reproduction](user/calculation-metadata.md#storing-a-result-for-reproduction).
   Contributor-level caveat: the snapshot cannot distinguish an unset prayer
   from an explicit zero, so do not rebuild the caller layer by applying all
   ten zero-filled `PrayerAdjustments::toArray()` values with `with()`. Use the
   recorded `sources` to decide whether `caller.adjustments` existed; a
   zero-only caller layer serializes identically with one zero entry;
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

For Qibla, reproducibility needs the same geodesy wiring
(`default()` vs `wgs84()` vs the same injected strategy). For Moon, it needs
the same solar engine, ephemeris, conjunction search, and evaluator set, plus
the same ΔT choice: an override (`caller.delta-t`) and the provider
(`moon.delta-t.espenak-2005-2050`) can differ by seconds and change a boundary
verdict. `evaluateGeometryWithMetadata()` replays criteria only; store the
geometry payload with it when the sky itself must be reproduced.

For Hijri, reproducibility needs the same calendar key *and* — for MABIMS —
the same country authority, the same frame, the same `CalculationParameters`
for the sunset frame, the same `OffsetStore` answers, and the same
`library_version`. Feed `input.instant` and `input.zone` back through
`convertWithMetadata()` with the same frame; do not feed
`effective.gregorian_date` directly, because that would bypass the frame
decision. A different calendar table (key or authority), frame, offset store,
or library version can change the date, the digest, or the warnings.

The compact methods remain the default and are not deprecated:
`calculate()`, `monthly()`, `atSunset()`, `evaluate()`, `evaluateGeometry()`,
`calculate()` on Qibla, `convert()`, and the other pre-existing methods return
the same values and throw the same exceptions. They expose no warnings. A
caller that wants provenance opts in with `calculateWithMetadata()` /
`monthlyWithMetadata()` (Prayer), `calculateWithMetadata()` (Qibla),
`atSunsetWithMetadata()` / `evaluateWithMetadata()` /
`evaluateGeometryWithMetadata()` (Moon), or `convertWithMetadata()` (Hijri).

## Implementation notes

- Metadata is built at orchestration boundaries (`PrayerTimesCalculator`,
  `TimetableService`, `QiblaCalculator`, `CrescentCalculator`,
  `GregorianToHijriConverter`), never inside `Astro/`.
- `calculate()` and `calculateWithMetadata()` share one private `run()`; the
  compact path returns `run()['schedule']`, so the numeric pipeline cannot
  drift between them. Qibla's `calculate()`/`calculateWithMetadata()` share
  one `solve()`, Moon's `atSunset()`/metadata methods share one `sunset()`,
  and Hijri's `convert()`/`convertWithMetadata()` share one `trace()`.
- `convert()` returns `trace()['date']` and never calls `DescribesCalendar`,
  builds warnings, or reads provenance-only code. A custom calendar whose
  `calendarProvenance()` throws still converts through the compact path.
  `convertWithMetadata()` reads provenance only after the numeric path
  succeeds.
- The maghrib fallback flag is a single boolean aggregated across the forward
  and backward probes; the trace records no probe date, so the warning names
  the resolved civil date. `HijriCalculationMetadata` validates the
  frame/input pairing: a civil frame cannot carry coordinates, parameters, or
  a fallback, and a sunset frame requires both coordinates and parameters.
- `Moon\ValueObjects` cannot import `Astro\Services\DeltaTProvider` (the
  layering gate), so the calibrated bounds travel through the provider
  source's `range` (`2005..2050`); the metadata object parses that documented
  carrier instead of duplicating the constants.
- `PrayerMetadataCalculatorInterface` extends `PrayerTimesCalculatorInterface`.
  Existing implementers are not forced to adopt it; `monthlyWithMetadata()`
  rejects an injected calculator that lacks it with a
  `ConfigurationException` instead of silently dropping provenance.
- Numeric exception behavior is unchanged: each metadata method throws exactly
  what its compact sibling throws for the same inputs, and metadata is
  constructed only after the numeric path succeeds. Opting into metadata can
  additionally surface provenance-only failures — for example a custom
  `DescribesCalendar` implementation whose `calendarProvenance()` throws. The
  compact path never reads that capability, so callers must handle these extra
  failures when they enable metadata.
