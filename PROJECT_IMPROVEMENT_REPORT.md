# al-falaq Improvement Report

## Executive summary

`al-falaq` already has a strong numerical and architectural foundation. It is
offline, dependency-free at runtime, deterministic, typed, layered, backed by
golden vectors, and unusually disciplined about provenance. The current test
suite passes (`607` tests and `50,536` assertions), the architecture group
passes, and the benchmark is inside its enforced tripwires.

The project should not be modernized by replacing the calculation core. The
highest-value path is to make the core a globally trusted platform with:

1. explicit calculation provenance, uncertainty, and explainability;
2. first-class civil-time, locale, calendar, and regional policy support;
3. a broader and reproducible validation program;
4. stable interoperability formats and adapters;
5. a community governance model for religious-method decisions and official
   calendar data; and
6. release, performance, and documentation maturity suitable for long-term
   adoption.

The goal should be “the most transparent and adaptable Islamic astronomy
engine,” not an unqualified claim that one library can be universally correct.
Prayer methods and Hijri dates can differ legitimately between authorities.
The library should expose those differences clearly and reproducibly instead
of hiding them behind a single default.

## Assessment basis

This assessment is grounded in the current repository:

- Public contexts: `SharedKernel`, `Prayer`, `Qibla`, `Moon`, and `Hijri`.
- Internal astronomical engine: `Astro`, including solar geometry, lunar
  ephemeris, ΔT, and WGS84/Karney geodesy.
- Current calendars: tabular, Umm al-Qura-style shipped tables, and per-country
  MABIMS tables with a Gregorian bridge.
- Current crescent criteria: Yallop, Odeh, MABIMS 1992, and Neo-MABIMS 2021.
- Current calculation methods: frozen presets with method-specific adjustments
  and high-latitude/polar policies.
- Current quality controls: PHPStan max + strict rules, PER style, executable
  architecture tests, coverage ratchet, mutation testing in CI, golden CSVs,
  wiring tests, benchmark tripwires, and a multi-version/time-zone CI matrix.
- Current documented non-goals in `README.md`: no CLI/HTTP service, no magnetic
  declination model, only `en`/`ar` Hijri month labels, and bounded embedded
  Hijri tables.

## Validation status and important corrections

This report was checked against the checkout on 2026-11-11. The following
commands were run locally with PHP 8.4.27:

| Check | Result | What it proves |
|---|---:|---|
| `composer test-unit` | 607 tests, 50,536 assertions | The current unit and golden suite is green in this environment. |
| `composer arch` | 32 tests, 380 assertions | The existing architecture group is green; new architecture work must extend it rather than assume it is absent. |
| `composer bench` | 1.707 ms single-day median, 747 ms year, 6.0 MB peak | The current run is inside the enforced 50 ms/30,000 ms tripwires. The 2 ms/1,000 ms/16 MB numbers are reference budgets, not gates. |

These are local observations, not a substitute for the PHP 8.2/8.3/8.4 and
Windows matrix in CI. A future update to this report should refresh the
numbers and date rather than treating them as permanent project facts.

The plan also needs to be read with these current-state corrections:

- `tests/ArchShapeTest.php`, `tests/ArchDocsTest.php`,
  `tests/ArchLayeringTest.php`, and `tests/ArchApiReferenceTest.php` already
  enforce a substantial part of the written architecture. The architecture
  work below is an incremental gap analysis, not a proposal to create all of
  those gates from scratch.
- `PrayerSchedule` and `DisplaySchedule` already have stable JSON-safe
  `toArray()` forms. `QiblaResult`, `CrescentGeometry`, `CrescentVerdict`,
  `HijriDate`, and the calculation-parameter objects do not yet share a
  cross-context serialization or metadata contract. The schema work should
  preserve the prayer shape documented in `docs/integrations.md`.
- `QiblaResult::magneticHeading()` already applies caller-supplied
  east-positive declination. The missing capability is a separately sourced
  declination model/provider and provenance around its use, not the basic
  heading arithmetic.
- Time-zone handling is not absent. The core deliberately computes in UTC,
  exposes `forZone()` at the display edge, and supports both local-wall-date
  and sunset-rollover frames in `GregorianToHijriConverter`. The proposed
  civil-time work must make ambiguous/nonexistent local times and rollover
  policies more explicit without replacing this discipline.
- The current 19 `CalculationMethod` cases, `CalculationParameters`, and
  strategy contracts already form a closed, validated policy surface. A
  registry must be an additive extension with compatibility aliases; it must
  not silently turn existing enum values into mutable configuration.
- `UmmAlQuraCalendar` and `MabimsCalendar` ship generated tables, not official
  third-party table transcriptions. `UQ_PROVENANCE.md` records the pinned
  source, generator, digests, supported spans, and the known post-2050 ΔT
  extrapolation caveat. Any future official dataset needs a distinct identity,
  provenance record, license decision, and range rather than replacing these
  tables in place.
- `DeltaTProvider` currently extrapolates outside its 2005–2050 calibrated
  interval without a PHP warning/status channel. That is a concrete P0 gap and
  should not be described as already solved by the existing provenance notes.

## How the team should use this report

The numbered recommendations are strategic workstreams, not implementation
tickets. Before coding any workstream, the owner should produce a short issue
or plan containing the following information:

1. **User and policy problem.** State who needs the capability, what decision
   they are making, and which religious, regional, astronomical, or formatting
   policy is involved. If the request could produce two legitimate answers,
   name both and explain how the caller chooses.
2. **Current seam.** Name the existing service, value object, contract, data
   file, generator, or documentation page that owns the behavior today. Trace
   the call path before proposing a new abstraction.
3. **Boundary and package decision.** State whether the change belongs in the
   zero-dependency core, a new optional Composer package, a data package, a
   host-application adapter, or documentation only. New I/O, framework types,
   network access, ICU requirements, and install-time data must stay out of the
   core unless the project explicitly revises its product contract.
4. **Compatibility plan.** Describe unchanged signatures, new entry points,
   enum/profile behavior, serialization versioning, deprecations, exception
   behavior, and the migration path for existing callers. Do not make a
   convenience API opt users into an unspecified method or authority.
5. **Evidence plan.** List the authoritative or independent references,
   license status, source date/version, tolerance, test fixtures, and the exact
   command that regenerates or checks each artifact. External sources may be
   used to create checked-in fixtures, but normal tests must remain offline.
6. **Verification plan.** Include unit and boundary tests, golden or property
   tests where numeric behavior is involved, architecture/doc tests, static
   analysis, mutation coverage where applicable, serialization contract tests,
   and benchmark impact. State which checks are mandatory for the slice and
   which are deferred.
7. **Documentation and release work.** Identify the contributor appendix,
   published user page, API reference, migration note, changelog entry, and
   release/data version that must move together. Generated pages must be
   regenerated by their prescribed command, not hand-edited.

### Definition of ready

A workstream is ready to implement only when its public vocabulary, policy
owner, package boundary, data source, compatibility approach, and acceptance
fixtures are agreed. “Add support for country X”, “make it global”, and
“improve accuracy” are goals, not ready tasks: each must name the rule, data,
scope, output, and tolerance.

### Definition of done

A workstream is done only when the implementation, tests, provenance, docs,
API reference, migration guidance, and changelog are consistent. The owner
must show the narrow verification commands first and then the applicable
repository gate (`composer ci`, plus package-specific checks). For a numerical
or policy change, the handoff must include a before/after output comparison and
an explanation for every changed golden row. For a data release, it must also
include a digest, source/license record, regeneration command, and rollback
identity.

## Current capability map and ownership boundaries

The following map is the starting point for decomposition. It prevents a new
team from treating the repository as an undifferentiated calculation engine.

| Area | Current owner and seam | Current limitation to address | Suggested primary owner |
|---|---|---|---|
| Prayer | `PrayerTimesCalculator`, `CalculationParameters`, prayer strategies, `PrayerSchedule` | No cross-context explanation/status envelope; closed method enum; polar behavior is visible mainly through result/exception paths. | Domain/API team |
| Timetables and Sunnah | `TimetableService`, `SunnahCalculator`, `SunnahTimes` | Monthly loop is single-day and constant-memory; no standard metadata attached to each row. | Domain/API team |
| Qibla | `QiblaCalculator`, spherical/WGS84 strategies, `QiblaResult` | True-north result is strong; magnetic heading needs an external model, epoch, and uncertainty. | Astronomy/integration team |
| Moon | `CrescentCalculator`, `CrescentGeometry`, four evaluator strategies | Geometry and criterion scores are available, but no common applicability/status vocabulary or observation-report workflow. | Astronomy/validation team |
| Hijri | `GregorianToHijriConverter`, `HijriCalendar`, generated UQ/MABIMS tables, `OffsetStore` | Table identities and spans are explicit, but there is no general dataset manifest/registry or official-announcement result type. | Calendar/data team |
| Shared kernel | `Coordinates`, `CivilDate`, `Angle`, `Minutes`, `JulianDay`, exception hierarchy | No shared metadata, warning, locale, schema, or policy identity types. | Domain/API team |
| Reference data | `tests/Goldens/`, `tools/*golden*`, `UQ_PROVENANCE.md`, `docs/*` citations | Provenance is strong but formats differ by context and most checks are regression/parity checks rather than independent validation. | Validation/data team |
| Documentation | `ARCHITECTURE.md`, `docs/`, `docs/user/`, executable integration snippets | Product direction is clear; future work needs decision records, compatibility notes, and fuller task briefs. | Maintainers/docs team |
| Release/tooling | `composer.json`, GitHub workflows, benchmark, changelog sync, docs-site build | Core release discipline is strong; optional packages/data releases would need their own version and support policy. | Release/DevEx team |

## What is already strong

### Numerical and domain foundation

- The main calculations are deterministic and do not read the system clock.
- UTC computation with display-zone conversion is a sound DST-safe boundary.
- Prayer calculation is decomposed into selectable method, Madhab,
  high-latitude, and polar strategies.
- Qibla has both a fast spherical implementation and a precise WGS84/Karney
  implementation.
- Crescent geometry is calculated once and can be evaluated by several
  independent criteria.
- Hijri conversion separates calendar strategies from correction data.
- Immutable value objects, typed exceptions, finite-number validation, and
  explicit constructor injection give the code a strong base for extensions.

### Engineering quality

- `ARCHITECTURE.md` describes bounded contexts, ports, layering, and data flow
  in enough detail for maintainers to extend the project safely.
- Golden vectors and provenance files make numerical drift reviewable.
- `tests/Arch*.php` turn important conventions into executable constraints.
- CI uses pinned actions, least-privilege permissions, a PHP-version matrix, a
  Windows release gate, docs checks, static analysis, and mutation testing.
- The docs site, executable integration snippets, and generated changelog show
  a serious commitment to user experience and release hygiene.

## Strategic product direction

The project should be positioned around five promises:

| Promise | Meaning |
|---|---|
| Reproducible | Same explicit inputs, algorithm version, data version, and policy produce the same result. |
| Transparent | Every result can explain its method, inputs, fallbacks, adjustments, and source. |
| Globally adaptable | Regional authorities, languages, scripts, calendars, and time-zone rules are extension points, not hard-coded assumptions. |
| Numerically defensible | Algorithms are compared against authoritative references, independent implementations, and documented error bounds. |
| Integration-friendly | PHP applications can consume typed objects, arrays, JSON, ICS, and framework-neutral adapters without depending on internal classes. |

## Prioritized improvement plan

### P0 — trust and correctness foundations

These should come before adding many new features because they protect every
future calculation and every regional preset.

#### 1. Add calculation provenance and an explainability model

Current results expose values, but a host application cannot uniformly answer:
which preset, algorithm, data table, fallback, correction, and rounding policy
produced a value.

Add a public, immutable `CalculationReport` or `CalculationMetadata` model with
fields such as:

- library version and algorithm revision;
- calculation context and method/preset identifier;
- geographic coordinates, elevation, civil date, and requested time zone;
- effective coordinates/date after polar fallback;
- selected Madhab, twilight, Maghrib, Isha, high-latitude, and polar rules;
- method and caller adjustments;
- source/data identifiers and table digests;
- undefined-event or fallback decisions;
- rounding convention and precision mode.

Allow callers to request either a compact result or a result plus metadata so
the default API remains ergonomic. Do not put internal engine objects into the
public report.

**Acceptance criteria**

- A schedule can be audited from serialized inputs and metadata alone.
- A polar fallback and a normal calculation are distinguishable without parsing
  exception strings.
- Metadata has a documented JSON shape and a compatibility policy.
- Golden tests include representative metadata snapshots.

#### 2. Define and publish uncertainty and applicability boundaries

Astronomical calculations are not equally accurate across all dates, latitudes,
elevations, refraction assumptions, and ΔT horizons. Crescent visibility
criteria are observational decision aids, not proof of visibility.

Add explicit applicability metadata and warnings for:

- solar and lunar model validity ranges;
- ΔT extrapolation outside the calibrated interval;
- polar and near-polar fallback use;
- approximate refraction and atmospheric assumptions;
- geodetic versus magnetic heading;
- calendar table coverage and generated-versus-official status;
- crescent verdict confidence/category versus binary “visible” language.

Prefer typed status values over silently changing result semantics. Keep the
existing pure API and let applications choose whether warnings are ignored,
returned, or promoted to exceptions.

**Acceptance criteria**

- Every public astronomy result has documented validity and limitation fields.
- Out-of-range dates and fallback paths have deterministic status codes.
- Moon documentation clearly distinguishes geometric criteria, visibility
  probability, local observation, and authority announcement.

#### 3. Create a reference-validation program independent of one pinned port

The current Python parity goldens are valuable, but parity alone can reproduce
the same upstream assumption or defect. Build a validation matrix using:

- authoritative published timetables from multiple regions;
- independent astronomy libraries or trusted ephemerides;
- IERS/IAU or equivalent reference data where appropriate;
- GeographicLib/Karney reference cases;
- observed crescent reports and negative reports with provenance;
- published Hijri month-start tables from each supported authority.

Store the source URL, access date, source version, license status, location,
date, expected tolerance, and interpretation notes beside every vector.

**Acceptance criteria**

- At least one independent reference family exists for each public context.
- Tolerances are stated per output and are not hidden in a generic comparator.
- CI checks vector provenance and detects stale or silently altered sources.
- Disagreement is reported by category rather than reduced to a pass/fail
  number.

#### 4. Make architecture conventions executable

The architecture tests cover important dependency rules, but several written
conventions should also be checked automatically:

- `Contracts/` contains interfaces only;
- public service contracts use the intended suffix convention;
- one class-like declaration exists per file;
- value objects are `final readonly` where required;
- service and strategy finality rules hold;
- public API docblocks are complete and resolvable;
- internal `Astro` classes cannot leak into documented public return types.

This is a small investment that prevents architectural drift as the public
surface expands.

### P1 — global support and user-facing completeness

#### 5. Introduce a first-class locale and formatting layer at the edge

The current `en`/`ar` month-label limitation is too narrow for a global
library. Keep the calculation core locale-free, then add a formatting package
or optional module with:

- BCP 47 locale identifiers;
- CLDR/ICU-backed month, weekday, number, and direction formatting when the
  host provides it;
- Arabic, Latin, Persian, Urdu, Malay, Indonesian, Turkish, Bengali, and other
  high-demand scripts;
- RTL-safe labels and punctuation;
- configurable transliteration, script, and numeral system;
- localized names for prayers, Hijri months, crescent criteria, and status
  messages.

Do not add ICU as a runtime requirement to the zero-dependency core. Define a
small formatter contract and ship an optional intl adapter plus a deterministic
fallback formatter.

**Acceptance criteria**

- Formatting is separated from astronomical calculation.
- Locale fallback is deterministic and documented.
- Snapshot tests cover RTL, Arabic-Indic digits, Persian digits, and mixed
  localized/ISO output.

#### 6. Make civil-time and timezone behavior an explicit domain boundary

The UTC-first policy is correct, but global consumers need safer primitives for
local civil dates, DST gaps/folds, historical timezone data, and date rollover
at sunset. Add:

- a `LocalDateContext` or equivalent carrying zone, local date, and conversion
  policy;
- explicit policies for midnight, sunset, and fajr-based Hijri day rollover;
- validation for nonexistent and ambiguous local times;
- APIs that accept IANA zones but never infer a zone from coordinates;
- test fixtures covering DST transitions, dateline crossings, half-hour and
  quarter-hour zones, and historical offset changes.

This should remain at the edge and must not reintroduce hidden clock reads.

#### 7. Expand calendar support as a data-and-policy system

Global Hijri support requires more than adding one algorithm. Separate:

- computational calendars;
- published/official observation tables;
- regional authority policies;
- caller corrections;
- date-range and provenance metadata.

High-value additions to investigate:

- official Saudi Umm al-Qura data as a separately named dataset;
- additional Southeast Asian authority datasets;
- regional calendars used in South Asia, Turkey, North Africa, and Europe;
- a generic month-start table adapter with signed/versioned data packages;
- forward and reverse conversion APIs with explicit range behavior;
- optional “astronomical estimate” and “official announced date” result types.

Never present a table-backed authority calendar as a universal astronomical
truth. Preserve the current distinction between generated and official data.

#### 8. Add a policy registry instead of growing one closed enum forever

`CalculationMethod` is convenient, but a global ecosystem needs local methods
that are not suitable for the core enum. Introduce a registry or profile model
that can load immutable, validated profiles containing:

- Fajr/Isha definitions;
- Maghrib definition;
- Madhab default;
- high-latitude and polar defaults;
- administrative adjustments;
- authority, country, language, source, and version metadata.

Keep existing enum presets as compatibility aliases. Validate profiles at
construction and reject contradictory configurations with typed exceptions.

### P1 — API and interoperability

#### 9. Add a stable public facade for common workflows

The current service-oriented API is excellent for explicit wiring but requires
users to understand internal composition quickly. Add an optional facade such
as `AlFalaq` or `Calculator` that exposes common operations:

- `prayerTimes()`;
- `monthlyPrayerTimes()`;
- `nextPrayer()`;
- `qibla()`;
- `crescentVisibility()`;
- `hijriDate()`;
- `convertHijri()`.

The facade should compose existing services and return the same value objects.
It must not become a god class or hide policy choices. Require a named profile
or explicit parameters for ambiguous choices.

#### 10. Add canonical serializable schemas

Define versioned schemas for:

- prayer schedules;
- timetable days/months;
- Qibla results;
- Hijri dates and calendar metadata;
- crescent geometry and verdicts;
- calculation metadata and warnings.

Provide `toArray()` and JSON-safe serializers without making `DateTimeImmutable`
or PHP enums the wire contract. Include ISO 8601 UTC values, local display
values only when requested, numeric units, schema version, and status fields.

Add JSON Schema documents and contract tests so other languages can consume the
outputs consistently.

#### 11. Provide an iCalendar/export module and optional CLI

The core should remain library-only, but adoption improves substantially with
small separate packages or optional tools for:

- ICS prayer-event export with time zones and alarms;
- CSV/JSON timetable export;
- a CLI for one-off calculations, batch generation, and diagnostics;
- machine-readable explain/provenance output.

Keep these outside the pure domain and avoid adding runtime dependencies to the
core package. The CLI should make it easy to reproduce a bug report by saving a
complete input/profile/metadata bundle.

#### 12. Publish adapters and examples for major PHP ecosystems

The existing plain-`new` recipes are a good base. Add maintained, separately
versioned integration examples for:

- Laravel;
- Symfony;
- WordPress;
- Slim/Mezzio or PSR-15 applications;
- command-line PHP;
- serverless PHP where applicable.

Adapters should provide configuration and clock wiring only. They must not leak
framework types into `src/`.

### P2 — astronomical capability expansion

#### 13. Add configurable solar and atmospheric models

Expose model choices without changing defaults:

- configurable refraction model;
- pressure and temperature inputs;
- observer horizon/dip model;
- optional atmospheric extinction/visibility metadata;
- configurable solar radius convention;
- explicit elevation semantics and validation.

Each model must carry provenance and have separate golden vectors. Do not add
configuration that appears more precise than its input data supports.

#### 14. Add a real ΔT provider seam and update policy

`DeltaTProvider` should evolve from a fixed internal helper into a documented
provider with:

- a stated model/version and validity interval;
- historical and future extrapolation policies;
- optional caller-supplied ΔT values;
- a release process for updating estimates;
- output uncertainty/status.

This matters most for lunar conjunction, crescent age, eclipse, and long-range
calendar work.

#### 15. Expand Moon beyond binary criterion evaluation

Add clearly separated capabilities:

- conjunction time and location-independent lunar phase;
- topocentric sun/moon positions;
- moonset and visibility geometry diagnostics;
- optional illumination, azimuth, altitude, elongation, and lag series;
- eclipse calculations only if the project is willing to support their data and
  validation burden;
- observation-report ingestion as data, not as a claim of automatic sighting.

Return a rich geometry object and let authorities or applications choose the
decision policy. Avoid a universal `visible: true/false` abstraction.

#### 16. Add Qibla uncertainty and magnetic-heading integration at the edge

The geodetic bearing is already strong. Improve practical navigation by adding:

- convergence/near-antipodal diagnostics;
- documented bearing precision and rounding;
- optional magnetic declination provider contract;
- date/location/height-aware magnetic model adapters outside the core;
- device compass calibration guidance in documentation.

Never call a magnetic heading a Qibla bearing without identifying the
declination model and epoch.

#### 17. Investigate additional prayer jurisprudence profiles

Potential additions should be community-reviewed and source-documented rather
than chosen solely by popularity:

- additional twilight definitions;
- regional fixed-minute conventions;
- configurable sunrise/sunset safety margins;
- custom Asr shadow conventions beyond the current Madhab enum;
- configurable imsak and fasting boundaries;
- authority-specific Ramadan and high-latitude policies.

The profile registry in item 8 should be implemented first so this work does
not turn `CalculationMethod` into an unbounded enum.

### P2 — performance and maintainability

#### 18. Add caching and batch APIs without compromising purity

Monthly and yearly consumers repeatedly calculate related solar values. Add
optional immutable or caller-owned caches keyed by:

- engine/model version;
- coordinates and elevation;
- date;
- relevant ΔT/model parameters.

Add batch APIs for month/year and multi-location generation while preserving
the single-day service as the source of truth. Benchmark cold and warm paths,
memory use, and concurrency assumptions.

#### 19. Reduce high-risk boilerplate and make public API inventory generated

The large `CalculationParameters` wither surface is easy to break when fields
change. Refactor it around one validated copy mechanism or a carefully typed
builder, while preserving the existing API through compatibility methods.

Generate the API reference from reflection/docblocks or a checked inventory so
new public classes and methods cannot be silently omitted. Treat generated
documentation as a release artifact and validate it in CI.

#### 20. Define compatibility and deprecation policy

Document:

- semantic versioning scope;
- which value-object and enum changes are breaking;
- serialized schema compatibility;
- preset default changes;
- deprecation duration;
- how corrected goldens are announced;
- PHP version support policy;
- whether `Astro` symbols are ever promotable to public API.

This is essential once integrations and data packages exist.

### P3 — ecosystem and adoption

#### 21. Improve discoverability and comparative documentation

Add a short “why al-falaq” page that compares dimensions, not marketing claims:

- offline versus hosted APIs;
- deterministic versus clock-driven APIs;
- method/profile extensibility;
- provenance and auditability;
- supported calendars and their status;
- precision and known limitations;
- licensing and runtime dependencies.

Include a feature matrix against established libraries only when the comparison
is reproducible and dated. Avoid claiming superiority without published tests.

#### 22. Build a reproducible benchmark suite

The current benchmark is useful but should distinguish:

- cold process startup;
- warm single-day calculation;
- month/year throughput;
- Moon calculations;
- Qibla spherical versus WGS84;
- Hijri table lookup versus algorithmic conversion;
- memory and allocation behavior.

Report median, p95, environment, PHP version, CPU, and dataset. Keep loose CI
tripwires separate from aspirational product budgets.

#### 23. Establish governance for religious and regional decisions

Create a lightweight advisory process with contributors or reviewers from
different regions and schools of thought. Record decisions in versioned ADRs:

- source authority;
- interpretation;
- geographic scope;
- effective date;
- dissent or alternatives;
- test vectors;
- migration impact.

This is as important as code review for prayer presets, Hijri dates, and
crescent policy.

#### 24. Add security and supply-chain hardening for data extensions

If calendars, profiles, or magnetic data become installable packages:

- sign or hash data releases;
- verify schemas before loading;
- reject unexpected ranges and duplicate dates;
- document trust boundaries;
- keep data packages inert and non-executable;
- scan Composer and docs-site dependencies continuously;
- publish SBOM/provenance for releases.

## Implementation briefs by priority

The recommendations above explain what to improve. This section explains what
the delivery team must actually settle and produce. The file and symbol names
are starting points taken from the current checkout; they are not permission to
break the existing boundaries.

### P0.1 Provenance and explainability

**Current seam.** Prayer provenance begins at `PrayerTimesCalculator::calculate()`
and `TimetableService::monthly()`. Qibla begins at `QiblaCalculator`, Moon at
`CrescentCalculator::atSunset()`/`evaluateGeometry()`, and Hijri at
`GregorianToHijriConverter::convert()`. Their value objects are intentionally
small and have no common result envelope.

**Required delivery.** The owner must first write an API decision record. The
recommended compatibility shape is to keep all existing methods and return
types unchanged, then add a sibling opt-in operation such as
`calculateWithMetadata()` or `explain()` that returns a typed envelope whose
payload is the existing value object. Do not change `calculate()` into a union
return merely to add optional diagnostics. The decision record must settle:

- the names and PHP types for `CalculationMetadata`, warnings, status, source
  identity, and the result envelope;
- whether metadata is produced eagerly or only by the opt-in path;
- a stable schema version and stable identifiers for methods, strategies,
  datasets, algorithms, and fallback policies;
- how the library version is supplied without adding a runtime Composer
  dependency or reading arbitrary package metadata at calculation time;
- which fields are safe to expose (coordinates, elevation, date, zone,
  effective fallback inputs, adjustments, rounding, and data digests) and which
  internal objects must remain private.

The implementation should build metadata at public orchestration boundaries,
not by leaking `Astro` objects or scraping exception messages. A timetable
must carry the metadata for each day, including any day-specific fallback. A
Hijri table result must identify the calendar key, country/proxy where relevant,
table span, table digest, correction source, and sunset frame. A Moon result
must identify the ephemeris/ΔT model and the evaluator criterion separately.

**Verification and handoff.** Add normal, polar-fallback, custom-adjustment,
Qibla, Moon, and each calendar-family fixtures. Assert exact serialized shape,
stable field names, nullability, and absence of internal class names. Add a
backward-compatibility test proving legacy return values are unchanged. Update
`docs/integrations.md`, the API reference, relevant contributor appendices,
and the changelog with one complete example that can reproduce a schedule from
the recorded inputs.

### P0.2 Applicability, uncertainty, and status

**Current seam.** `CivilDate` constrains the shared civil range to 1900–2100;
`DeltaTProvider` is calibrated for 2005–2050 but extrapolates outside that
interval; polar handlers can replace coordinates or dates; the Moon geometry
can represent a no-moonset window with null fields; and Hijri table calendars
raise `HijriOutOfRangeException` outside their spans.

**Required delivery.** Define a status taxonomy before adding fields. At a
minimum, distinguish a normal result, an extrapolated model, an applied
fallback, an indeterminate/undefined event, and a result that requires a local
authority or observation decision. Decide which cases are non-fatal warnings
and which remain typed exceptions. Do not use a warning to make an undefined
prayer schedule look valid, and do not call a criterion score a probability of
observation unless its source supports that interpretation.

The owner must publish, per context:

- supported date/model intervals and what “outside” means;
- whether the numeric output is still returned, returned with status, or
  rejected;
- assumptions for refraction, pressure, temperature, elevation, horizon, and
  ΔT;
- the meaning of a polar fallback and the original/effective inputs;
- the difference between geometric crescent criteria, probability-like zones,
  local sighting evidence, and an authority announcement;
- the difference between table-backed calendar output and an astronomical
  estimate.

Tests must pin each status transition at a boundary and prove that status is
deterministic. Documentation must include examples where two legitimate
policies produce different answers. The team must not silently widen the
1900–2100 `CivilDate` range or the table ranges as a side effect of this work.

### P0.3 Independent reference validation

**Current seam.** `tests/Goldens/` contains 25 CSVs and context-specific
loaders/comparators. `tools/goldens.py` delegates to generators, and the
current files record source tags, Meeus citations, the pinned Python commit,
or drift-gated local/reference rows. This is excellent regression protection,
but a parity vector is not automatically an independent validation.

**Required delivery.** Create a checked-in validation ledger with one row per
reference family and vector set. Each row must identify context, output field,
source title/URL or publication, source version/date, license/redistribution
status, location/date, input frame, expected unit, tolerance, interpretation,
and whether the source is independent of the pinned Python implementation.
Separate these categories in names and reports:

- pin-parity regression vectors;
- mathematical/reference-implementation comparisons;
- published timetable comparisons;
- observed and negative crescent reports;
- published calendar announcements or month-start tables.

Keep CI offline. External retrieval belongs in a documented maintainer
regeneration command that produces reviewed, checked-in artifacts. The test
output should report the first failing field, absolute/relative error, source
category, and whether the disagreement is a known policy difference. A single
global tolerance or a single aggregate pass rate is insufficient. Add at
least one independent family for each public context before declaring this
workstream complete, and document contexts where an independent source is not
available rather than manufacturing one.

### P0.4 Executable architecture conventions

**Current seam.** The architecture suite already checks finality, readonly
value objects, contracts, suffixes, one class-like per file, docblocks,
offline purity, layering, and the documented public-class inventory. The
remaining work is to audit the written conventions against the actual test
coverage and add only missing non-vacuous checks.

**Required delivery.** Produce a gap table first: convention, current test,
known blind spot, proposed assertion, and false-positive risk. Likely additions
include checking public return/parameter types for internal `Astro` leakage,
checking the exact service/strategy placement rules, and checking that new
serializable public types are represented in the reference/schema inventory.
Do not add a regex that can be satisfied by comments or an empty directory;
follow the existing non-vacuity floors and tokenizer/reflection approach in
`tests/ArchSupport.php`.

Extend the existing arch group where the rule belongs, add scanner regression
probes for the new rule, and run `composer arch` plus the full CI gate. The
handoff must list intentional exceptions, because the existing `SolarDayEngine`
and `MeanSolarCalculator` wiring exception is part of the public integration
contract.

### P1.1 Locale and formatting

**Current seam.** Calculation returns UTC instants and typed values. The only
locale-aware API is `HijriMonth::label()`, which intentionally supports Arabic
or an English transliteration without ICU. `PrayerSchedule::toArray()` is a
stable machine shape, not a localized presentation API.

**Required delivery.** Keep formatting out of calculation services. Decide
whether the formatter is a separate package (recommended) or a deliberately
small optional namespace. Define a formatter contract around BCP 47 locale,
script, numbering system, direction, and fallback locale. Select a translation
source and license before promising prayer, calendar, criterion, error, or
status labels. An `intl` adapter may use host facilities, but the deterministic
fallback must work without `intl` and must document exactly which locales it
supports.

The owner must specify whether localized output is presentation-only or part of
the wire schema. ISO dates, enum keys, units, and machine statuses must remain
locale-neutral. Test Arabic RTL, Arabic-Indic digits, Persian digits, a Latin
locale, a missing-locale fallback, mixed localized/ISO output, and a timezone
offset that crosses the displayed date. Do not add translated strings directly
to astronomical value objects.

### P1.2 Civil date, timezone, and rollover boundary

**Current seam.** `PrayerSchedule` stores UTC, `forZone()` renders a zone, and
`GregorianToHijriConverter` already supports caller-zone civil dates and an
explicit `changeAtSunset` frame using the calculator's Maghrib path.

**Required delivery.** Write down the supported civil-time operations before
adding a `LocalDateContext`. The design must answer what happens for a
nonexistent local wall time during a DST spring-forward, an ambiguous local
time during a fall-back, a date-line crossing, and a sunset near midnight. It
must also define whether a requested zone is only a display zone or determines
the civil date. Never infer an IANA zone from coordinates and never read the
system clock inside the domain.

Prefer a small edge context that carries the requested zone, local date, and
rollover policy, while keeping `CivilDate` and the solar engine UTC-safe. Add
fixtures for New York DST transitions, Lord Howe or another half-hour zone,
Kathmandu or another quarter-hour zone, UTC+14/UTC-12 date-line behavior,
historical offset changes, and the 1900/2100 representable edges. Verify that
`forZone()` only changes representation and never recomputes astronomy.

### P1.3 Calendar data and policy system

**Current seam.** `HijriCalendar` is the driving strategy contract;
`TabularCalendar`, `UmmAlQuraCalendar`, and `MabimsCalendar` own conversion;
`OffsetStore` applies caller-supplied corrections. The UQ/MABIMS arrays are
generated data classes with embedded SHA-256 constants.

**Required delivery.** Define a calendar manifest before adding datasets. It
must include stable key, calendar family, authority/country/proxy, rule or
table status, Gregorian/Hijri span, source and effective date, generator and
pin, digest, license, update cadence, and whether the result is an estimate,
rule-generated table, or official announcement. A generic table adapter must
validate monotonic contiguous starts, 29/30-day month lengths, duplicate dates,
range limits, and digest/version metadata. It must load inert data only; a
calendar data package must not execute PHP supplied by the data author.

The official Saudi dataset, additional Southeast Asian datasets, and other
regional calendars each require a separate source/licensing decision. Do not
rename the current generated `uqu` key to imply official status. Add explicit
forward/reverse range tests, sunset-frame tests, offset precedence tests, and
one-day disagreement examples against the published authority where available.

### P1.4 Extensible calculation profiles

**Current seam.** `CalculationMethod` has 19 frozen enum cases and
`defaultParameters()` constructs validated `CalculationParameters`; custom
behavior can already be supplied through strategy contracts.

**Required delivery.** Define an immutable profile object and registry that
contains policy values plus authority, geography, language, source, effective
date, and version metadata. Profile construction must validate contradictory
Fajr/Isha/Maghrib, Madhab, high-latitude, polar, Ramadan, and adjustment
choices before calculation. Decide whether a profile is code-defined, data-
defined, or both; the recommended first step is code-defined profiles with no
remote loading.

Keep every existing enum case and serialized enum value working. If an enum
case maps to a profile, make that alias explicit and test that its defaults
are byte-for-byte/current-output compatible. A profile must not silently
override caller adjustments or jurisdictional choices. Add a provenance page
and community review record for each new religious/regional profile.

### P1.5 Convenience facade

**Current seam.** The services are already independently injectable and
`docs/integrations.md` contains executable plain-`new` recipes. The facade is
therefore an ergonomics layer, not a replacement composition root.

**Required delivery.** Define the target user journey and the minimum method
set before creating a facade. The recommended facade delegates to existing
services, accepts an explicit profile/parameters object, exposes explicit
clock/date inputs, and has no hidden global container. Decide how custom
strategies are injected and how a caller requests metadata. Keep advanced
operations available through the underlying services so the facade does not
become a god class.

Test that facade outputs equal the existing service outputs for representative
inputs, that no default authority is silently selected for an ambiguous
request, and that wiring remains framework-free. Add the facade to the API
reference and executable integration snippets only after its compatibility
surface is frozen.

### P1.6 Canonical schemas and serializers

**Current seam.** Prayer output already has a documented stable array shape;
the other public value objects are typed PHP-only values. There is no shared
schema version, JSON Schema directory, or cross-language contract test.

**Required delivery.** Inventory every public output before designing schemas.
For each schema define identifier, versioning policy, required/optional/null
fields, units, enum keys, precision, timezone semantics, status/warning
encoding, and whether a field is numeric evidence or a policy label. Keep
`DateTimeImmutable` and PHP enum implementation details out of the wire
contract. Decide whether `toArray()` is the canonical serializer or whether a
separate serializer owns the schema; do not let both drift independently.

Add JSON Schema documents, valid/invalid fixtures, round-trip tests where
lossless, and a contract test that checks the examples in `docs/integrations.md`.
A schema change requires a compatibility classification and changelog entry.
Do not promise JSON serialization for internal engine objects.

### P1.7 Exports, CLI, and ecosystem adapters

**Current seam.** The package deliberately has no CLI/HTTP service, and
integration documentation uses executable framework-neutral snippets. These
features should be optional delivery units.

**Required delivery.** Split the work into separately testable packages or
tools. For ICS, settle event naming, UID stability, local timezone/`VTIMEZONE`
strategy, alarms, date rollover, undefined prayer behavior, and updates before
writing an exporter. For CSV/JSON, use the canonical schemas and preserve
units/status/provenance. For the CLI, define exit codes, deterministic input
files, machine-readable errors, and a reproducibility bundle before adding
human output.

Laravel, Symfony, WordPress, PSR-15, CLI, and serverless examples should state
supported versions and remain adapters/configuration only. They must not add
framework classes to `src/` or make a framework a runtime dependency of the
core. Each example needs a smoke test or executable snippet and an explicit
maintenance owner; an abandoned example is worse than a documented recipe.

### P2.1 Solar, atmospheric, and ΔT models

`MeanSolarCalculator`, `CrescentEphemeris`, and `DeltaTProvider` currently
encode the default model assumptions internally; crescent calculation already
has a caller ΔT override. The owner must first inventory every model constant,
unit, and validity statement in `docs/prayer.md`, `docs/moon.md`, and
`docs/hijri.md`. Then define model/provider contracts, defaults, input
validation, and provenance IDs without changing current defaults.

Pressure, temperature, horizon dip, solar-radius, refraction, or ΔT inputs
must not suggest more accuracy than the caller's data supports. Each option
needs boundary tests, independent/reference vectors, and an explanation of
which outputs it can affect. ΔT updates require a versioned release policy,
historical/future extrapolation status, and a no-network default. Unifying the
existing Moon override with a provider must preserve the direct override for
deterministic tests and documented reproduction.

### P2.2 Rich Moon outputs and observation workflow

`CrescentGeometry` already contains altitude, elongation, age, lag, azimuth,
illumination, ARCV, and width, while `CrescentVerdict` contains one criterion's
score and zone. New work should fill documented gaps—phase/conjunction APIs,
topocentric series, moonset diagnostics, or observation reports—without
duplicating these fields or converting a criterion into an automatic sighting
decision.

Separate geometry services from policy evaluators. Specify the reference frame,
time scale, sampling interval, horizon/atmosphere assumptions, and null/undefined
behavior for every new field. An observation report is user-provided evidence
with source/time/location metadata, not proof generated by the library. Add
characterization fixtures, degenerate polar cases, and documentation that
keeps geometric result, visibility category, local observation, and authority
announcement distinct.

### P2.3 Qibla and magnetic heading

The geodetic spherical and WGS84/Karney paths are already separate and
golden-tested. `QiblaResult::magneticHeading()` currently accepts a declination
number and normalizes it. The missing work is an optional edge provider with a
declination model name, epoch, input height/location, source, validity range,
and uncertainty.

Keep the core's true-north Qibla result independent of magnetic data. A
magnetic adapter should return a distinct heading object or an explicit
metadata-enriched result, never overwrite `direction`. Test sign convention,
date/epoch, missing/out-of-range model data, pole/near-antipodal diagnostics,
rounding, and the existing Makkah-self behavior. Document compass calibration
as an integration concern and never label a magnetic heading as the Qibla
bearing without its model identity.

### P2.4 Additional jurisprudence profiles

This is policy research before it is code. For every proposed profile, the
owner must provide primary or authoritative sources, translated interpretation,
geographic/effective scope, exact numeric rules, disputed alternatives, and
test vectors. The team must record whether a difference is a Fajr/Isha angle,
fixed-minute convention, Asr shadow rule, safety margin, imsak boundary,
Ramadan rule, or high-latitude policy.

Do not add a new enum case simply because it is the shortest implementation.
Use the profile registry after its validation and provenance design is
approved. A profile that lacks a source or cannot be distinguished from an
existing preset should remain a documented extension example rather than a
core default.

### P2.5 Caches and batch APIs

`TimetableService::monthly()` currently loops over the single-day source of
truth and holds a list of schedules. Any cache must be caller-owned or an
explicit immutable cache object; no static/global cache may introduce hidden
state. The key must include coordinates, elevation, date, model/engine ID,
ΔT inputs, calendar/policy/profile identity, all adjustments, and any locale or
display policy that affects the result.

Define invalidation and memory limits, then benchmark cold/warm single-day,
month, year, multi-location, Moon, Qibla, and Hijri paths. Test cache hit
equivalence, collision resistance for materially different parameters,
mutation/immutability, and concurrent-read assumptions. Batch APIs must define
ordering, partial-failure behavior, bounded memory, and whether one invalid
day fails the whole batch or produces a per-row status.

### P2.6 Public API inventory and compatibility policy

`docs/user/api-reference.md` plus `ArchApiReferenceTest` already provide a
bidirectional documented-class inventory. The task is to decide whether to
generate that page, generate only a checked inventory, or keep the page
hand-authored with stronger completeness checks. Do not replace a readable
reference page with reflection output that loses usage guidance.

The compatibility owner must document semantic-versioning scope, PHP support,
public/internal namespace rules, enum and value-object evolution, exception
guarantees, schema versions, preset default changes, golden corrections,
deprecation duration, and whether any `Astro` symbol can ever be promoted.
Add migration fixtures for one additive, one deprecated, and one breaking
example. Link every intentional golden correction to a changelog and source
decision.

### P3. Adoption, benchmarks, governance, and supply chain

These four workstreams should have explicit non-code deliverables:

- **Discoverability:** a dated, reproducible “why al-falaq” comparison and
  limitations page; no unsupported superiority claims or stale competitor
  numbers.
- **Benchmarks:** extend `examples/bench.php` with cold/warm, month/year,
  Moon, Qibla model, Hijri lookup/conversion, memory, PHP version, CPU, and
  dataset reporting. Keep hardware-dependent product budgets separate from
  the current loose CI tripwires.
- **Governance:** add a lightweight decision-record template covering source,
  interpretation, scope, effective date, alternatives/dissent, vectors,
  reviewer roles, and migration impact. Religious/regional decisions need
  domain review in addition to code review.
- **Supply chain:** before data packages or magnetic/profile packages ship,
  define signed or hashed release manifests, schema/range validation, trust
  boundaries, inert-data guarantees, rollback identity, SBOM expectations, and
  Composer/docs dependency audit ownership. The current generated PHP tables
  are not a license to execute arbitrary future data formats.

## Decisions that must be made before implementation

The following decisions close the product and architecture questions for the
next work. They are direction-setting choices, not a claim that every
implementation detail is already specified. Each workstream still needs its
own acceptance fixtures, source review, and migration notes.

### 1. Explainability API: opt-in sibling operation returning an envelope

**Decision.** Keep existing calculation methods and return types intact. Add
an opt-in sibling operation for each supported context that returns a typed
envelope containing the existing domain result plus a versioned,
machine-readable report and ordered warnings/statuses. Prefer a shared
`CalculationOutcome`-style shape only after its common fields are proven across
contexts; context-specific result and metadata types remain explicit. Do not
make callers parse a JSON representation to use the PHP API.

**Alternatives considered.** A separate reporting service would need to
reconstruct inputs or duplicate calculation orchestration and could report
metadata that differs from the calculation actually used. Wrapping all current
results would make a cross-cutting breaking change to the most-used API. Adding
metadata properties directly to every result is viable later, but couples
serialization and policy evolution to domain result constructors and risks
unnecessary churn. An opt-in sibling operation provides auditability at the
point where effective fallback decisions are known while preserving the
ergonomic legacy path.

**Why this is best.** The product's differentiator should be reproducibility
and transparency, while its current typed services and result objects are
already useful. An outcome envelope makes metadata available as a first-class
PHP value and can serialize through an explicitly versioned schema. Existing
callers retain their current signatures and outputs; new integrations can
adopt the richer contract immediately. The implementation must share the same
calculation path rather than calculate twice.

### 2. Package boundary: keep the calculation kernel here; put optional
ecosystem capabilities in separately versioned packages

**Decision.** Keep astronomy, prayer policy execution, Qibla, crescent
evaluation, Hijri conversion, core value objects, and dependency-free typed
contracts in this repository. Keep CLI, HTTP, ICS/CSV export, framework
adapters, ICU/CLDR formatting, downloaded or frequently updated authority
tables, and magnetic-declination data providers out of the core. Deliver
capabilities that need their own dependencies, data cadence, or host lifecycle
as optional packages/adapters. Keep small, inert, finite datasets in core only
when their license, provenance, range, regeneration, and release identity are
reviewed and they are part of the core's offline promise. The core may provide
interfaces and plain integration recipes; it must not require those adapters.

**Alternatives considered.** Putting everything in this repository would
make installation convenient initially, but would add translation/data
maintenance and release cadence to a library whose strongest promise is a
zero-dependency offline kernel. Splitting every context into packages would
fragment the current coherent API and force users to assemble basic
calculations. A single optional "extras" package would still couple unrelated
dependencies and update schedules. The selected boundary keeps the coherent
calculation product together and splits by operational lifecycle, not by
arbitrary class ownership.

**Why this is best.** It preserves broad PHP compatibility, shared-hosting
use, deterministic offline operation, and a simple core install while still
allowing high-quality localization and interoperability for applications
that need them. This is a product boundary, not a restriction on the amount of
work: optional packages can provide complete, polished workflows. Each package
must declare supported core versions, data/source ownership, security and
release policy, and integration tests against the public core API.

### 3. Status model: typed, stable codes for calculation conditions; exceptions
for invalid requests and inability to produce a result

**Decision.** Define a small shared taxonomy with distinct concepts for
outcome status (for example `complete`, `fallback_applied`, `outside_model`),
warning codes (specific assumptions, extrapolations, or limitations), and
applicability. Include stable machine codes, context, and relevant numeric
bounds; human-readable messages are explanatory and are not identifiers.
Warnings are returned in the opt-in outcome in deterministic order. Legacy
methods retain current exception behavior. Keep typed exceptions for invalid
inputs/configuration, unsupported policy identities, and cases where no valid
result can be produced. A caller may promote selected warnings to exceptions
at its application boundary; the library does not add a global warning mode.

**Alternatives considered.** Exceptions for every limitation are simple but
make useful approximate/fallback calculations hard to consume and conflate
invalid requests with expected model limits. Free-form strings or a single
boolean lose machine stability and cannot distinguish multiple simultaneous
conditions. Returning warning values from every existing method is a broad
signature break. A shared typed vocabulary on the new outcome path provides
machine handling without changing legacy callers.

**Why this is best.** Some valid calculations require qualification (such as
ΔT extrapolation or a polar fallback), while some inputs genuinely cannot be
calculated. Modeling those separately is essential for honest user-facing
results and automation. Codes must be documented and stable within the
compatibility policy below; warnings must never imply precision or certainty
that the selected model does not support.

### 4. Policy governance: documented evidence plus domain and maintainer
approval

**Decision.** A new calculation method, authority calendar, or regional policy
requires (a) primary or authority-published evidence and a dated interpretation
note, (b) independent domain review from someone competent in the relevant
astronomy or religious/regional policy, and (c) maintainer approval for API,
provenance, licensing, and compatibility. Record the decision in a
repository ADR/policy decision record linked to source citations, dissenting
interpretations, scope/effective dates, vectors, and changelog impact. When no
qualified reviewer or adequate source is available, do not present the policy
as an official core profile; publish an extension recipe or mark the proposal
as research instead.

**Alternatives considered.** Maintainer-only approval is efficient but does
not establish religious or regional legitimacy. A vote or popularity measure
cannot resolve interpretation or source quality. Requiring one universal
authority would contradict the project's global adaptability promise.
Delegating all decisions to outside authorities would leave API and data
quality uncontrolled. The chosen review combines domain legitimacy with
technical stewardship while preserving multiple explicitly named policies.

**Why this is best.** The library should report which authority or method it
implements, not declare one answer universally correct. Evidence, scope,
independent review, and recorded dissent let users choose appropriately and
let maintainers reproduce later changes. This governance applies to decisions
that change policy meaning; routine implementation details remain normal code
review.

### 5. Compatibility and versioning: SemVer for PHP API, explicit schema and
data identities, immutable policy IDs

**Decision.** Follow SemVer for the Composer package. Treat documented public
classes, method signatures, enum cases, exception guarantees, and documented
calculation semantics as public API. Additive PHP API changes are minor;
removals, signature incompatibilities, or unannounced semantic changes require
a major release. Deprecate public APIs for at least one minor release and
provide a migration note before removal. Version serialized JSON contracts
independently with an explicit schema identifier/version; never infer schema
from package version. Give profile IDs and dataset IDs stable, namespaced,
immutable identities: a changed rule or changed source/range gets a new
identity or data revision, not a silent rewrite. Record SHA-256 digests for
generated data artifacts and their source manifests. Bug fixes that change
numeric output require source/evidence, changed-vector review, and a changelog
entry even when the API remains compatible.

**Alternatives considered.** Treating every PHP object as internal would
allow rapid change but conflicts with the documented public namespaces and
published API reference. Equating JSON schema version to package version
would unnecessarily couple independently evolving contracts. Mutable profile
names would make historical results ambiguous. A long fixed deprecation
window (such as multiple years) is disproportionate for a small library and
could freeze poor designs; immediate removal would undermine trust. The
selected policy gives users predictable API stability and reproducible data
without tying unrelated release streams together.

**Why this is best.** The expected users range from small PHP applications to
long-lived integrations. They need stable source-level APIs and the ability to
store/reproduce a result years later. Separate API, JSON, policy, and dataset
identities make those guarantees understandable and allow each to evolve at
its proper pace. Any deliberate exception must be called out in release notes
and migration docs.

### 6. Model horizons: publish supported, calibrated, and extrapolated ranges;
warn on bounded extrapolation and reject unsupported computation

**Decision.** Distinguish three states per model/dataset: supported and
validated range, calculable but extrapolated range, and unavailable range.
For the next release, retain the current shipped table spans as the only
supported official/generated lookup coverage (Umm al-Qura 1423–1500,
MABIMS 1445–1500, plus tabular arithmetic). Keep the current solar and lunar
algorithms available wherever their inputs are numerically defined, but expose
their documented validity/applicability and ΔT's calibrated 2005–2050 interval
in the outcome status. Dates outside calibrated intervals may be calculated
only where the underlying model has a defined deterministic extrapolation;
they must carry a stable extrapolation warning and must not be described as
validated. Reject dates/inputs outside a model's defined computational range
with a typed exception. Do not extend official calendar coverage by silently
extrapolating an authority table. A future release can widen a horizon only
with a named model/data revision, independent reference fixtures, provenance,
and an explicit review of resulting output drift.

**Alternatives considered.** Claiming unrestricted date support would hide
model degradation, particularly the known post-2050 ΔT caveat. Rejecting every
date outside a calibrated interval would unnecessarily remove useful
deterministic estimates and hinder long-range planning. Silently extending
calendar tables would falsely imply official authority. The three-state
boundary preserves useful computation while making confidence and authority
limits visible.

**Why this is best.** Users need long-range estimates, but need to know what
they can responsibly infer from them. Separating calculation availability
from validation and authority coverage communicates that distinction. The
specific horizon must remain model-specific; there is no single universal
validity date for solar, lunar, ΔT, and calendar data. Before implementation,
the model owners must publish exact bounds and the warning code for each
public result path.

These decisions establish direction; they do not authorize silent changes to
existing numeric outputs. Each workstream must still identify its evidence,
fixtures, compatibility effects, and release documentation before coding.

## Recommended release roadmap

### Release A — Trust foundation

- Calculation metadata and warnings.
- Applicability/uncertainty documentation and status codes.
- Independent reference-validation harness.
- Generated public API inventory.
- Architecture convention tests.
- Compatibility/deprecation policy.

**Exit gate:** every existing preset and calendar has provenance, applicability
status, and reproducible serialized inputs; no existing output changes without
an explicit golden and changelog entry.

### Release B — Global edge support

- Local civil-date/time-zone context.
- Locale/formatting contracts and optional `intl` adapter.
- JSON schemas and versioned serializers.
- Official/generated calendar data model.
- Profile registry with existing enum compatibility aliases.

**Exit gate:** representative users can generate localized prayer schedules and
Hijri dates for DST, RTL, non-Latin numeral, dateline, and half-hour-zone cases
without custom forks.

### Release C — Ecosystem delivery

- Stable convenience facade.
- CLI and ICS/CSV export package.
- Framework-neutral adapters/examples.
- Batch APIs and caller-owned caches.
- Benchmark dashboard and published comparison methodology.

**Exit gate:** a new PHP application can install the core, select a named
authority profile, produce a localized timetable, export it, and attach a
machine-readable explanation without importing `Astro` internals.

### Release D — Advanced astronomy

- ΔT provider/update policy.
- Atmospheric/refraction model options.
- Rich moon geometry and observation-report workflows.
- Optional magnetic declination adapter.
- Additional independently validated astronomical capabilities.

**Exit gate:** advanced outputs expose model version, input assumptions,
uncertainty/applicability status, and independent validation evidence.

## Suggested first implementation slice

The best first slice is the provenance/explainability foundation because it
improves every existing context and makes later global support safer.

1. Define `CalculationMetadata`, `CalculationWarning`, and status enums in a
   small public `SharedKernel` surface.
2. Add metadata factories to prayer, Qibla, Moon, and Hijri result paths.
3. Add `toArray()`/JSON-safe serialization with a schema version.
4. Record effective fallback policy, source identifiers, algorithm version, and
   data digests.
5. Add representative snapshots and documentation examples.
6. Keep existing result objects and method signatures backward compatible.

Do not begin with a broad rewrite, a new framework integration, or a large
collection of new presets. Without provenance and profile boundaries, those
changes would multiply ambiguity and make future corrections harder to audit.

## Risks and trade-offs

| Risk | Mitigation |
|---|---|
| Feature growth makes the core heavy | Keep the core dependency-free; use optional adapters and data packages. |
| Religious differences become encoded as technical truth | Use named profiles, authority metadata, ADRs, and explicit scope. |
| More precision creates false confidence | Publish assumptions, validity ranges, uncertainty, and model versions. |
| Locale support becomes a translation bottleneck | Use BCP 47/CLDR contracts and community-maintained locale packs. |
| More data creates licensing or provenance problems | Record source/licence/digest per dataset and separate code from data. |
| API surface becomes unmaintainable | Add a stable facade, generated API inventory, schema versioning, and deprecation rules. |
| Caching breaks determinism | Make caches caller-owned or immutable and include every model/input key. |
| External comparisons become marketing claims | Publish reproducible vectors, tolerances, dates, and limitations. |

## Final recommendation

Treat the present M5 codebase as a reliable calculation kernel and invest next
in trust infrastructure and global edge support. The differentiator should be
that users can see exactly what was calculated, under which authority and model,
with which assumptions, and can reproduce it in another language or service.
That positioning is more durable than adding an ever-growing list of presets
without provenance or a clear policy model.
