# Handoff — P0.2 Applicability, Uncertainty and Status

- **Date:** 2026-11-28
- **Purpose:** start a fresh session on the next leg of the improvement report
  without re-deriving the prior context.
- **Contract of record:** [`docs/design/2026-11-28-applicability-uncertainty-status.md`](../design/2026-11-28-applicability-uncertainty-status.md)
- **Source programme:** [`docs/artifacts/2026-11-11-project-improvement-report.md`](../artifacts/2026-11-11-project-improvement-report.md), P0.2 and decisions 3 and 6
- **Preceding leg:** [`docs/design/2026-11-15-provenance-explainability-foundation.md`](../design/2026-11-15-provenance-explainability-foundation.md) (A1–A3, shipped as `v0.3.0`)
- **Delivery evidence:** [`docs/artifacts/2026-11-28-provenance-a1-a3-delivery-audit.md`](../artifacts/2026-11-28-provenance-a1-a3-delivery-audit.md)
- **A3 plan's own hand-off:** [`docs/plans/2026-11-24-provenance-a3-hijri.md`](../plans/2026-11-24-provenance-a3-hijri.md) §After A3 — "A1–A3 can ship as `0.3.0`, and P0.2 can become `0.4.0`."

## 1. One-paragraph brief

A1–A3 made every result auditable (inputs, policy, sources, warnings, derived
status) and were released as `v0.3.0`. Release A's exit gate still lacks the
*applicability* half: published per-model ranges, calibrated/extrapolated
states, assumptions, and the distinction between a computed answer and a
decision requiring observation or an authority announcement. Build P0.2 as a
single additive `applicability` block on `CalculationMetadata` (schema `1.0` →
`1.1`), populate it from the existing private traces in all four contexts,
publish the per-context matrix, and prepare `0.4.0` as the release that
completes Release A. Do not change status derivation, warning codes, numeric
output, or any existing method signature.

## 2. State of the repository at handoff

| Item | Value |
|---|---|
| Branch / remote | `main` pushed to `origin/main` at `2e55ef7` |
| Release | annotated tag `v0.3.0` → `a4c2749`; GitHub Release published; Windows gate green |
| Published docs | `https://al-falaq.github.io/al-falaq/` with version switcher v0.1.0–v0.3.0 + Latest |
| `LibraryVersion::CURRENT` | `'0.3.0'` (exact release value) |
| Tests | 932 tests / 51,694 assertions |
| Architecture | 32 tests / 474 assertions |
| Coverage | 98.9% vs 95% floor |
| Mutation | MSI 93% vs 85 gate |
| Benchmark | 3.719 ms single-day / 1256 ms year vs 50 ms / 30,000 ms tripwires |
| Working tree | clean at handoff; this handoff and the new design spec are the only new artifacts |

The `v0.3.0` release notes were generated at the tag commit; a post-tag docs
fix (`2e55ef7`) corrected stale `0.3.0-dev` strings in the published guide and
re-froze the snapshot. Do not rewrite the tag.

## 3. Read in this order

1. `docs/design/2026-11-28-applicability-uncertainty-status.md` — the frozen
   shape. Sections 6 (API), 7 (wire), 9 (traces) and 11 (tests) are the
   implementation contract.
2. `docs/artifacts/2026-11-11-project-improvement-report.md` — P0.2 brief and
   decisions 3 and 6.
3. `docs/artifacts/2026-11-28-provenance-a1-a3-delivery-audit.md` — what was
   delivered and exactly why the Release A gate is still partial.
4. `AGENTS.md` and `ARCHITECTURE.md` — non-negotiable repo conventions.
5. `docs/provenance.md` and `docs/user/calculation-metadata.md` — where the new
   block must be documented without duplicating the matrix.
6. `tests/SharedKernel/Provenance/`, `tests/Prayer/PrayerMetadataTest.php`,
   `tests/Moon/MoonMetadataTest.php`, `tests/Hijri/HijriMetadataTest.php` —
   existing test style and fixtures to extend.

## 4. Decisions already frozen (do not re-litigate)

- One additive `applicability` key on the shared envelope, not four
  context-specific blocks.
- Schema `1.1`; package `0.4.0`; `LibraryVersion::CURRENT` is the only runtime
  version source and is bumped only during release prep.
- `ApplicabilityState` has exactly `supported`, `extrapolated`, `unavailable`.
  `unavailable` never appears in a successful outcome because the service
  throws first.
- `DecisionKind` has exactly `none`, `observation`, `authority`. It is a policy
  fact, not a status: Hijri stays `complete` with `decision = authority`.
- **No new warning codes** and no change to `CalculationMetadata::deriveStatus()`.
  All eight A1–A3 codes keep their names, detail keys and status effects.
- Applicability is built from the existing private `run()`/`solve()`/`sunset()`/
  `trace()` values. No second calculation, no clock, no exception parsing, no
  `Astro` import under `src/Hijri/`, `src/Moon/ValueObjects` or
  `src/Moon/Contracts`.
- `docs/user/applicability.md` is a new published page; the contributor matrix
  is `docs/applicability.md`. Neither duplicates the other.

## 5. Work sequence

### B1 — shared vocabulary and envelope (first commit)

Create:

- `src/SharedKernel/Enums/ApplicabilityState.php`
- `src/SharedKernel/Enums/DecisionKind.php`
- `src/SharedKernel/ValueObjects/ModelApplicability.php`
- `src/SharedKernel/ValueObjects/Applicability.php`

Modify:

- `src/SharedKernel/ValueObjects/CalculationMetadata.php` — `SCHEMA_VERSION =
  '1.1'`, optional trailing `?Applicability $applicability = null`, seventh
  `toArray()` key after `sources`; status derivation untouched.
- `docs/user/api-reference.md`, `docs/provenance.md`, `CHANGELOG.md`,
  generated `docs/user/changelog.md`.
- `tests/SharedKernel/Provenance/` — enum values, validation, de-dup, wire
  order, seven-key envelope with `applicability: null`, all eight status
  transitions still identical.

**Start with failing tests.** The plan should follow `docs/plans/` convention
and be task-by-task TDD, as the A1–A3 plans did.

Exit gate: `composer ci`, `composer arch`, and the focused applicability tests
green; no context service touched yet.

### B2 — per-context facts (second commit)

Modify:

- `src/Prayer/Services/PrayerTimesCalculator.php` — solar range from
  `CivilDate::MIN_YEAR`/`MAX_YEAR`, refraction/elevation/horizon assumptions,
  polar fallback rule from the existing trace; `TimetableService.php` per day.
- `src/Qibla/Services/QiblaCalculator.php` — geodesy id from the injected
  strategy, true-north assumptions, `decision = none`.
- `src/Moon/Services/CrescentCalculator.php` — ephemeris, conjunction search,
  ΔT with the same decimal year already used for the extrapolation warning;
  `decision = observation` for evaluations, `none` for geometry.
- `src/Hijri/Services/GregorianToHijriConverter.php` — calendar key/range/
  coverage from the single `calendarProvenance()` read, frame and offset
  assumptions, `decision = authority`.
- Context metadata tests, `tests/Wiring/CalculatorWiringTest.php`, benchmark
  line if needed.

Exit gate: every metadata path emits the right block; compact paths still
produce byte-identical results; no new `Astro` import or runtime dependency.

### B3 — matrix, docs and release prep (third commit or later)

Create and update every file listed in design spec §12. Verify the docs site
build and the bidirectional API-reference gate, then prepare `0.4.0` exactly as
`v0.3.0` was prepared (see `README.md` → "Freezing docs for a release"):

1. bump `LibraryVersion::CURRENT` to `0.4.0`;
2. close `## [0.4.0] - YYYY-MM-DD` in `CHANGELOG.md` and run `composer changelog`;
3. add `v0.4.0` to `docs_site/versions.json`;
4. delete any stale snapshot and run `cd docs_site && npm run build` so the
   plugin generates `docs/user/v0.4.0/` and its version JSON;
5. commit, push, tag, and let `pre-release.yml` run the Windows gate before the
   GitHub Release; verify the versioned pages at
   `https://al-falaq.github.io/al-falaq/v0.4.0/`.

Do not tag or push unless the user asks.

## 6. Commands

Narrow first:

```bash
vendor/bin/phpunit --no-coverage --filter Applicability
vendor/bin/phpunit --no-coverage --filter CalculationMetadata
vendor/bin/phpunit --group arch
```

Full gates:

```bash
composer validate --strict
composer ci
composer arch
composer mutation
composer bench
composer changelog:check
cd docs_site && npm run build
```

CI legs after push: `test.yml` (PHP 8.2/8.3/8.4 UTC + 8.4 America/New_York),
`docs.yml` (npm audit + build + Pages deployment), and `pre-release.yml` on the
tag (Windows matrix + GitHub Release).

## 7. Definition of done

- Schema `1.1` envelope with `applicability` present (or `null`) on all four
  contexts, exact key order pinned by tests.
- Contributor matrix and published applicability guide live and linked from
  `docs/README.md`, `docs_site/astro.config.mjs`, `docs/provenance.md` and
  `docs/user/calculation-metadata.md`.
- API reference contains every new public type; `ArchApiReferenceTest` green in
  both directions.
- All legacy results, exception conditions, warning codes, status meanings and
  numeric goldens unchanged; backward-compatibility tests still green.
- Gates green with no lowered floor: 95% coverage, MSI ≥ 85, benchmark tripwires.
- Changelog entry, regenerated published changelog, migration note, and plugin-
  generated docs snapshot consistent; release prepared but not published
  without instruction.

## 8. Do not touch in this leg

P0.3 validation ledger; P1.1 locale; P1.2 civil-time/DST context; P1.3 official
calendar datasets; P1.4 profile registry; P1.5 facade; P1.6 JSON Schema files;
P2.1 configurable atmospheric models; P2.2 observation reports; P2.3 magnetic
model; P2.5 caches/batch APIs; P2.6 generated API inventory and full SemVer
policy. They are explicitly deferred in design spec §16 and in the provenance
design §17.

Also do not: widen `CivilDate` or shipped table ranges; add a warning code;
change `WarningCode` detail keys; change `CalculationStatus` rank order; add a
global warning mode; read a clock; add a Composer runtime dependency; or
hand-edit a generated docs snapshot or changelog page.

## 9. Quick orientation

- Working style: strict types, final/readonly VOs, validation at construction,
  docblocks on public methods, one class per file, PSR-4, `Contracts/`
  interfaces only.
- Tests are Plain PHPUnit 11; `tests/bootstrap.php` fixes UTC; no ecosystem
  runner.
- The coverage floor is a ratchet (95); never lower it.
- `composer changelog` regenerates `docs/user/changelog.md`; never hand-edit.
- The versioning plugin generates snapshots; hand-copied pages lack `slug`
  frontmatter and 404 in the switcher.
- Mutations run only in `.github/workflows/mutation.yml`, gated by the
  `release-readiness` label; do not fold them back into `test.yml`.

Open the design spec first and work from its task units; this document is only
the map to the current tree.
