# Provenance A1–A3 delivery audit

- **Date:** 2026-11-28
- **Scope:** Read-only verification of the current source at merge commit `349ab6e` (PR #54, `feat/provenance-a3-hijri`) against:
  - [Provenance and Explainability Foundation design spec](../design/2026-11-15-provenance-explainability-foundation.md)
  - [al-falaq Improvement Report](2026-11-11-project-improvement-report.md), the source document from which the design was spawned
- **Method:** Four parallel read-only source/doc/gate audits (shared vocabulary + Prayer, Qibla + Moon, Hijri, documentation/gates/report cross-check), plus direct verification of cross-cutting constraints and repository gates. No files were modified during the analysis.

## Verdict

**All functional deliverables in the design spec are delivered, and its stated objectives are met.** A1, A2 and A3 are green on the current merged tree, with only three minor file/documentation-level deviations and no functional gaps. Against the improvement report, **P0.1 is delivered**; P0.2 is partially delivered by design; the remaining report workstreams are either explicitly deferred by the spec or belong to later workstreams that A1–A3 never claimed to implement.

## 1. Design spec: deliverable-by-deliverable

| Spec section | Deliverable | Status |
|---|---|---|
| §6.1–6.3 | Shared vocabulary: `CalculationContext`, `CalculationStatus`, `WarningCode`, `SourceKind`, `CalculationWarning`, `CalculationSource`, `CalculationMetadata`, `LibraryVersion`, `IdentifiesCalculationSource` | **Delivered** — exact enum values, private `CalculationMetadata` constructor with `create()` as sole path, `SCHEMA_VERSION = '1.0'`, deterministic sort + de-duplication, ranked status derivation, no client-settable status, fixed six-key `toArray()` |
| §6.4 | Context metadata/outcome types for Prayer, Qibla, Moon, Hijri, including `CalendarProvenance`, `DescribesCalendar`, the three Hijri enums and `HijriDateFrame` | **Delivered** — all classes exist with the specified fields, validation and wire shapes |
| §6.5 | Five opt-in entry points: `calculateWithMetadata()`, `monthlyWithMetadata()`, Qibla `calculateWithMetadata()`, three Moon metadata methods, `convertWithMetadata()` | **Delivered** — each reuses one private `run()`/`solve()`/`sunset()`/`trace()`; legacy methods unchanged |
| §6.6 | Additive `toArray()` on `QiblaResult`, `CrescentGeometry`, `CrescentVerdict`, `HijriDate`, `CalculationParameters`, `PrayerAdjustments` | **Delivered** — exact keys, order and units; `PrayerSchedule::toArray()` untouched |
| §7 | 8 warning codes, detail keys, deterministic ordering, status effects and precedence | **Delivered** — all codes mapped in `CalculationMetadata::deriveStatus()`; ranks 0–4; `prayer.polar.none` never emitted as a source |
| §8.1–8.2 | Source registry ids/versions, custom `custom.<component>` fallback, 12 fixed component names, data digests | **Delivered** — all constants present (`solar.mean-meeus`, `prayer.method.*` + `method-defaults-v1`, geodesy, ephemeris, conjunction search, ΔT, four criteria, two Hijri ids); digests recomputed from the shipped tables match |
| §9 | JSON-safe `toArray()` contract, schema `1.0`, ISO-8601 UTC, enums as values, nullable keys present, no `Astro` names | **Delivered** — tests assert `assertStringNotContainsString('Astro\\', $json)` across contexts |
| §10 | Single-path internal traces; no double calculation, no exception-string parsing | **Delivered** — e.g. `GregorianToHijriConverter::convert()` is exactly `trace()['date']`; `CrescentCalculator::evaluateWithMetadata()` computes geometry once |
| §11 | Additive-only API, output stability, schema compatibility, checked-in `LibraryVersion` | **Delivered** — php-only runtime (`composer.json:7–9`), strict types in every `src/` file, no clock/I/O/network reads, `LibraryVersion::CURRENT = '0.3.0-dev'` |
| §12 | Test matrix, architecture gates, wiring, benchmark evidence | **Delivered in substance** — see gate evidence below |
| §13 | Contributor docs, published guide, API reference, changelog, docs-site wiring | **Delivered** — all 18 named files present; bidirectional API-reference gate green |
| §14 | A1, A2, A3 exit gates | **Delivered** — each phase documented, tested, mutation-covered and green; legacy outputs unchanged |
| §15 | 16-row acceptance mapping | **All 16 satisfied in substance**; two caveats noted below |

The three objective-level claims from §1 and §2 also hold: the common shape was proven on prayer/timetable before replication; metadata is built at orchestration boundaries; and public results can be audited from serialized inputs and metadata alone.

### Deviations found (all minor)

1. **Spec A1 names `tests/Prayer/ProvenanceBackwardCompatibilityTest.php`; no such file exists.** The required assertions are embedded in `tests/Prayer/PrayerMetadataTest.php:149–176,415–427` and `tests/Prayer/TimetableMetadataTest.php:132–139,244–256`. Coverage delivered, named file missing.
2. **§13.2 `docs/user/citations.md` cross-reference.** The page lists the source papers and one digest but does not link to the machine ids published in `docs/provenance.md` and `docs/user/calculation-metadata.md`. The spec's wording is conditional ("if source ids/digests are added to the published provenance guidance") — they were added elsewhere, so this is a convention gap, not a contract gap.
3. **`README.md:5` still says "M5 landed"** while `CONTEXT.md:18` says A1+A2+A3 landed. §13.3 asks for the status line to move with the phase.

Non-issues worth recording:

- Metadata "golden snapshots" are exact-array assertions inside metadata tests rather than files under `tests/Goldens/`, which is consistent with §12.3's instruction to leave numeric goldens untouched.
- `CalculationMetadata` adds digest/range as extra source-sort tiebreakers and `CalculationWarning` uses `ksort(..., SORT_STRING)` — both strictly more deterministic than the spec.
- `DeltaTProvider` exposes identity via a `CalculationSource` constant rather than implementing `IdentifiesCalculationSource`, because it is a static internal provider, not a wired component (documented in `AGENTS.md` and the A2 plan).
- `PrayerCalculationMetadata` captures an eager private policy snapshot and enforces the fallback/status invariant on the service path rather than in the value-object constructor; both are additive hardening.
- No single test pins the combined ΔT-extrapolated + no-moonset geometry status case; the generic rank logic is correct and other status transitions are pinned.

## 2. Cross-check against the improvement report

| Report item | Status | Reason / evidence |
|---|---|---|
| **P0.1** provenance & explainability | **Delivered** | Opt-in envelopes in all five contexts; status/warning/source vocabulary; documented JSON shape; backward-compatibility assertions. The design's own acceptance mapping is fully satisfied. |
| **P0.2** applicability, uncertainty, status | **Partially delivered** | Five ranked statuses, 8 stable warnings, ΔT 2005–2050, calendar spans, polar fallback and no-moonset are visible. Full per-context applicability matrix, warning-promotion policy and high-latitude safe-cap traces are explicitly deferred to P0.2 in spec §3 and §17. |
| **P0.3** independent reference validation | **Not delivered** | No validation ledger exists. Separate workstream, not part of A1–A3. |
| **P0.4** executable architecture conventions | **Partially delivered** | Arch suite extended and green (32 tests / 473 assertions), including new contract allow-lists and non-vacuity floors. No gap-table artifact, no signature-level `Astro`-leak check, no documented exception list beyond `AGENTS.md`/`ARCHITECTURE.md`. |
| **P1.1, P1.2, P1.4, P1.5, P1.7** locale, civil-time, profiles, facade, exports/CLI | **Deferred by design** | Spec §3 out-of-scope list and §17. Core remains php-only and dependency-free. |
| **P1.3** calendar data system | **Partially delivered** | `CalendarProvenance` is a manifest for each shipped calendar (key, family, authority, span, digest, source, license). No generic table adapter, official dataset or new authority data. |
| **P1.6** canonical schemas | **Partially delivered** | `toArray()` is canonical and schema `1.0` exists. JSON Schema files and cross-language contract tests are explicitly out of scope (P1.6). |
| **P2.1** solar/atmospheric/ΔT models | **Partially delivered** | ΔT identity, calibrated range, extrapolation warning and caller override reported. No configurable refraction/pressure/horizon models or ΔT update policy. |
| **P2.2** rich Moon outputs | **Partially delivered** | Honesty layer only: `crescent_criterion_is_not_observation`, geometry/evaluation separation. No phase/topocentric APIs or observation reports. |
| **P2.3, P2.5** magnetic heading, caches | **Deferred by design** | True-north only with `magnetic_declination_degrees: null`; no caches or batch APIs. |
| **P2.4** jurisprudence profiles | **Not delivered** | Research workstream; no new presets. |
| **P2.6** API inventory / compatibility policy | **Partially delivered** | Bidirectional `ArchApiReferenceTest` gate extended and green; migration note and schema rules documented. No generated inventory, dedicated SemVer/deprecation page or migration fixtures. |
| **P3** adoption/benchmarks/governance/supply chain | **Partially delivered** | Benchmark now prints legacy vs metadata medians; design spec acts as a decision record; `composer audit`, npm audit, zero-runtime-package assertion and table digests exist. No "why al-falaq" page, ADR template, signed data manifests or SBOM policy. |
| **Decisions 1–3** | **Delivered** | Opt-in sibling envelope, core/package boundary upheld, typed status model with deterministic warnings and no global warning mode. |
| **Decisions 4–6** | **Partially delivered** | No new policy was introduced, so governance approval was never triggered; no dedicated SemVer/deprecation page; per-model horizon matrix deferred to P0.2. |
| **Release A exit gate** | **Partially delivered** | Provenance for every preset/calendar: yes. Reproducible serialized inputs: yes, with a documented caveat that an unset adjustment and an explicit zero need `sources` to disambiguate. No output changes: yes. Applicability status: only taxonomy-level; full intervals deferred by spec §17. |

## 3. Gate evidence (current tree)

| Check | Result |
|---|---|
| `composer ci` | **930 tests / 51,648 assertions**; coverage **98.9 % vs 95 % floor**; lint, CS, PHPStan and changelog clean |
| `composer arch` | **32 tests / 473 assertions** |
| `composer validate --strict` | valid |
| `composer changelog:check` | in sync |
| `composer bench` | single-day **3.065 ms** vs 50 ms tripwire; year **1110 ms** vs 30,000 ms; metadata medians recorded for A1/A2/A3 |
| Docs site `npm run build` | **78 pages built**, including `calculation-metadata/` |
| Mutation | Verified earlier in this work on the same tree: full MSI **93 %**; SharedKernel shard **91 %**; Hijri shard **95 %** — all above the 85 gate. Not re-run during this read-only pass. |

## Bottom line

The design spec's contract is satisfied end to end: the shared vocabulary is frozen and reused, all five contexts expose opt-in metadata through one shared calculation path, serialization is deterministic and JSON-safe, legacy behavior is unchanged, and docs/API/changelog moved together. The only shortfalls are the named backward-compatibility test file, a `citations.md` cross-reference and a stale status line in `README.md` — none affect behavior.

The improvement report is a much larger strategic program. A1–A3 deliver **P0.1 completely** and lay the groundwork for P0.2/P0.4/P1.3/P1.6/P2.1/P2.2/P2.6, but the rest of the report's requirements are explicitly deferred by the design or belong to later workstreams. In particular, Release A's exit gate is not fully met as written because the applicability-interval half of P0.2 is outstanding — and that is a planned deferral, not a defect in the landed code.
