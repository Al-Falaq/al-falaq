# Repository Hygiene Snapshot — `al-falaq/al-falaq`

**Date:** 2026-10-22
**Commit under review:** `ebcc3aa` ("docs(release): prep v0.1.1 — changelog header, docs snapshot, version entry")
**Scope:** full repo — `src/`, `tests/`, `docs/`, `docs/user/`, `docs_site/`, `tools/`, `examples/`, `.github/`, root markdown, git history, CI/CD, and the live GitHub issue tracker.
**Method:** read-only. No source file was modified. All findings below were verified by executing the repo's own toolchain and by direct inspection; commands are reproducible from Appendix A.

---

## 0. Executive summary

This is a **well-run repository**. It is not a typical first-pass codebase: the layering is enforced by executable tests rather than prose, the numeric behaviour is pinned to an upstream Python reference with digests, the CI gates are SHA-pinned and least-privilege, and the release path refuses to publish without a docs snapshot and a CHANGELOG header.

The headline risk is **not correctness** — it is **truth drift between the documentation and the artefacts it describes**. Several stated budgets, API inventories, and "must be covered" claims are already out of date relative to the code they describe. Everything found is a *hygiene* issue: stale assertions, unused seams, duplicated domain facts, and conventions documented but not enforced.

**Overall assessment: healthy, release-ready, with 24 concrete follow-ups — none of them blocking.**

| Dimension | Score | Notes |
|---|---|---|
| Correctness / parity | **A** | 5,140 golden rows, 91% MSI, pin-digested Hijri tables |
| Architecture & layering | **A−** | Enforced by tests; a few unenforced conventions (P21) |
| Toolchain & CI/CD | **A−** | SHA-pinned, least-privilege, matrix-tested; local `composer mutation` broken (P3) |
| Test quality | **B+** | Strong goldens + sweeps; golden coverage is asymmetric across methods (P5) |
| Documentation accuracy | **B** | Rich and mostly correct, but several inventories/budgets are stale (P1, P6, P19, P20) |
| Code cleanliness | **B** | No dead code in the hot path, but vestigial seams (P10–P12) and copy-paste boilerplate (P16) |
| Performance | **B−** | Over its own printed p50 budget; ~20% of solar-triplet cost is redundant (P1, P2) |
| Repo hygiene (git, artifacts) | **A−** | Clean `.gitignore`, no tracked build artifacts, no stray secrets |

### Counts at a glance

| Metric | Value |
|---|---|
| `src/` PHP files / lines | 92 / 10,284 |
| `src/` public / private methods | 178 / 83 |
| Interfaces / enums / traits | 16 / 7 / 7 |
| Docblocks in `src/` | 449 |
| `tests/` PHP files / lines | 53 / 9,448 |
| Test methods / assertions | 436 / 49,724 |
| Golden CSV files / rows | 25 / 5,140 |
| Tracked files in git | 291 |
| `docs/` + `docs/user/` + `docs_site/` markdown | 675 + 1,689 + 1,689 (snapshots) lines |
| Commits (all by 1 author + Dependabot) | 204 |
| Open GitHub issues | 4 (all post-1.0.0, all deferred by design) |

---

## 1. Repository inventory

### 1.1 Bounded contexts (`src/`)

| Context | Files | Lines | Role |
|---|---:|---:|---|
| `SharedKernel/` | 14 | 610 | VOs (`Coordinates`, `CivilDate`, `Angle`, `Minutes`, `JulianDay`), exception hierarchy, `ClockView`/`DomainException` |
| `Prayer/` | 39 | 3,348 | Pipeline, timetable, next-prayer, sunnah, 12 strategies, 19-method preset table |
| `Qibla/` | 6 | 308 | Spherical + WGS84/Karney geodesy |
| `Moon/` | 10 | 780 | Crescent geometry + 4 criterion strategies |
| `Hijri/` | 14 | 1,921 | 3 calendars, month-start tables, Gregorian bridge |
| `Astro/` (`@internal`) | 9 | 3,317 | Meeus engine: `MeanSolarCalculator`, `CrescentEphemeris`, `GeodesyEngine`, `KarneyInverse`, `DeltaTProvider` |

Largest files: `KarneyInverse.php` (1,500 — vendored GeographicLib port, excluded from mutation), `MeanSolarCalculator.php` (677), `MabimsMonthStarts.php` (628, generated data), `CalculationParameters.php` (591).

### 1.2 Repository layout observations

- **Root is clean.** `composer.lock` is the only lockfile tracked; `vendor/`, `.phpunit.cache/`, `.php-cs-fixer.cache`, `infection.log`, `tools/__pycache__/` are all gitignored and untracked.
- **`docs/superpowers/`** (15 plan/spec files) is gitignored by design ("never merged") but present on disk — planning residue, correctly excluded from history.
- **`docs_site/src/content/docs` is a tracked symlink** (`../../../docs/user`), so `docs/user/` is the single edit target for the published site. This is a deliberate and well-documented design (`README.md:57-78`, `docs_site/src/starlightRouteData.ts`).
- **Three doc trees coexist:** `docs/` (contributor-facing, 675 lines, 8 files), `docs/user/` (user-facing, 1,689 lines, 20 files), and per-release snapshots `docs/user/v0.1.0/` + `docs/user/v0.1.1/`. See **P24**.

---

## 2. Toolchain and gate status (verified, not inferred)

All gates were executed against the working tree at `ebcc3aa`.

| Gate | Command | Result |
|---|---|---|
| Syntax lint | `composer lint` | ✅ pass (156 files) |
| Code style | `composer cs` (php-cs-fixer `@PER`) | ✅ **0 of 156 files** need fixing |
| Static analysis | `composer stan` (PHPStan **max** + strict-rules, `src`+`tests`) | ✅ **No errors** (141 files) |
| Unit tests | `composer test-unit` | ✅ **436 tests, 49,724 assertions**, 0 failures |
| Coverage ratchet | `composer test` (`tools/coverage_gate.php --min=95`) | ✅ **97.2%** vs floor 95 |
| Architecture group | `composer arch` | ✅ pass (shape / docs / layering) |
| Benchmark tripwire | `composer bench` | ✅ passes the enforced 50 ms / 30,000 ms gate — ⚠️ but **exceeds the printed p50 budget** (P1) |
| Mutation | `composer mutation` | ⚠️ **fails locally** (P3); CI ✅ green — **MSI 90%, Covered MSI 91%** (floor 85) |
| Examples | `php examples/*.php` | ✅ all 9 run clean |
| Manifest | `composer validate --strict` | ✅ valid |
| Dependency security | `composer audit` | ✅ no advisories |
| Locked runtime packages | CI assertion (`packages == 0`) | ✅ runtime is `php`-only |
| Docs site build | `npm run build` in `docs_site/` | ✅ builds; **0 broken internal links** across 58 built pages |
| Docs-site deps | `npm audit --omit=dev` | ⚠️ **3 moderate** advisories in transitive build deps (P22) |

---

## 3. Architecture assessment

### 3.1 What is genuinely strong

1. **Layering is executable, not aspirational.** `tests/ArchLayeringTest.php` enforces three directed edges (Astro → domain only; domain + `Moon/Strategies/` → never the engine; `Hijri/` → never Astro or Moon). Crucially, it strips comments *and string literals* via `token_get_all`, so a FQCN cannot be smuggled in as prose or as an example string. It also carries its own **regression probes** (lines 42-51, 94-101, 133-140) asserting the detection regex itself still fires on bare `use AlFalaq\AlFalaq\Astro;`, `use ... as Engine;`, and `use ...\{Astro, Qibla};` while *not* firing on `Astrology` — a genuinely rare level of self-testing for an architecture gate.
2. **Single-owner seams are real, not aspirational.** `Angle::normalized()` is the only unwind owner; `ClampsMakkahSelf` is the only self-clamp; `NightResolver` is the only owner of seasonal/NONE/portions fajr+isha logic; `PrayerTimesCalculator::maghribBase()` is the only maghrib owner, shared by `calculate()` and `maghribAt()`.
3. **Parity is pinned, not asserted.** `PYTHON_PIN = a7baa8b202790d3bc84c47a610ac763be6c0b7e7`, digest-pinned Hijri tables, `UQ_PROVENANCE.md` documenting that the tables are *generated*, not transcribed — with the licensing reasoning stated plainly ("facts are not copyrightable").
4. **The docblock gate is a real gate.** `tests/ArchDocsTest.php` enforces class summaries, `@param $name` per parameter, and `@return` per non-void return, with a self-test proving the `@param` extractor does not mistake `callable(int $value)` inner names or `@param-out` for the parameter itself.
5. **CI supply-chain hygiene is above average:** every action pinned to a full commit SHA with a `# vX.Y.Z` comment Dependabot can track, `permissions: contents: read` by default, `persist-credentials: false`, Pages deploy isolated behind `pages: write` + `id-token: write`, PR builds on their own concurrency group, and the release job refuses to publish unless the tag is an ancestor of `main`, `CHANGELOG.md` has an exact `## [X.Y.Z] - YYYY-MM-DD` header, and the docs snapshot + version JSON exist.

### 3.2 Architectural concerns

- **The `Astro/` duplication is a deliberate, documented trade-off** (`ARCHITECTURE.md:431-435`) — parity to the pinned Python beats reuse for a numerics library. That reasoning is sound and the boundary is clean (all trig behind the engine). No change recommended.
- **`Prayer → Qibla` sibling coupling is avoided but at the cost of duplication.** The Kaaba coordinates are a domain fact needed by two bounded contexts. Rather than lift it to `SharedKernel`, the fact is duplicated (P4).
- **`Hijri → Prayer` coupling** (`GregorianToHijriConverter` composing `PrayerTimesCalculatorInterface` for the maghrib probe) is intentional and documented, and the astronomy is correctly *composed* rather than reimplemented. Good.
- **Enforcement gaps in the arch group (P21).** Six conventions that `AGENTS.md` states as hard rules have no test: `Contracts/` must contain interfaces only; the `*Interface` suffix rule; one class per file; `ValueObjects/` must be `readonly`; `Traits/` must expose only `private` helpers; `Services/` must be `final` + expose `default()`. I verified manually that all six currently hold — which is precisely the problem: they hold by convention, so a future contributor can break them silently.

---

## 4. Documentation assessment

### 4.1 Strengths

- `ARCHITECTURE.md` (577 lines) is exemplary: numbered sections, Mermaid architecture/sequence diagrams, an explicit "what NOT to do" list, a payload-compatibility contract, and a deployment diagram. It documents the dependency rule in the same words the test enforces.
- `UQ_PROVENANCE.md` records digests, the pin SHA, the regeneration gate, the licence reasoning, and — notably — a **known caveat** (the pin's ΔT polynomial is calibrated only for 2005–2050, so far-future Hijri rows inherit extrapolated ΔT). Recording that is the mark of honest engineering.
- The per-method preset table in `src/Prayer/Enums/CalculationMethod.php` cites a source URL + access date **per cell**, and labels each preset's epistemic status (pin-frozen / drift-gated / labelled convention / provisional). This is unusually good practice.
- The docs site rebuilds edit links and last-updated dates from the real repo path because the content dir is a symlink (`docs_site/src/starlightRouteData.ts`) — with a careful comment explaining why `editLink.baseUrl` is deliberately absent. Verified: **0 broken internal links** across 58 built pages.

### 4.2 Documentation defects

| ID | Finding | Evidence |
|---|---|---|
| **P1** | The benchmark **prints a budget it does not meet**. `examples/bench.php:46` prints "M5 budget p50 < 2ms"; the measured median is **2.373 ms** — a 19% overshoot. The enforced gate is only 50 ms, so the overshoot is silent. | `composer bench` output |
| **P6** | `docs/user/api-reference.md` claims to map "the entire public surface" but omits **~26 public classes**, including `NightResolver` (a service in the *public* `Prayer\Services\` namespace), `EphemerisConjunctionSearch`, `JulianDay`, `Prayer` enum, `TwilightModel`, `NightPortions`, `ClockView`, and 8 strategy implementations (`NearestLatitudeHandler`, `NearestDayHandler`, `MakkahHandler`, `ThrowHandler`, `MiddleOfNightCorrection`, `SeventhOfNightCorrection`, `TwilightAngleCorrection`, `SeasonAdjustedTwilight`, `AngleIsha`, `IntervalIsha`, `AngleMaghrib`, `MinutesAfterSunsetMaghrib`, `SphericalCalculator`, `Wgs84KarneyCalculator`). | programmatic diff of `src/` vs the reference table |
| **P19** | `ARCHITECTURE.md:399,536` states `docs/integrations.md` "shows plain-`new` construction for **every feature**" and that recipes "must be covered by `tests/Wiring/`". In reality it covers only **Prayer + Qibla + clock + output shapes** — Moon, Hijri, Timetable, and Sunnah have **no wiring recipe and no wiring test**. | `grep -c 'CrescentCalculator\|GregorianToHijri\|TimetableService\|SunnahCalculator' docs/integrations.md` → 0 |
| **P20** | The changelog exists in **two unsynchronised files**: `CHANGELOG.md` (208 lines, release-gate-enforced) and `docs/user/changelog.md` (54 lines, hand-maintained, **not** gated). The release workflow validates only the former, so the published changelog can silently diverge. | `pre-release.yml` checks `CHANGELOG.md` only |
| **P24** | `docs/` and `docs/user/` overlap on **8 topics** (prayer, qibla, moon, hijri, sunnah, polar, migration, integrations) with no cross-check, so the same facts live in two places with different audiences. `docs/` is the one the tests cite in error messages (`docs/prayer.md`, `docs/polar.md`), so it is load-bearing — but its claims are not verified. | topic matrix |
| — | `docs/user/api-reference.md` correctly documents the `GeodesyCalculator` constants (verified present in `src/Qibla/Contracts/GeodesyCalculator.php:21-31`). | ✅ no defect |

---

## 5. Code quality

### 5.1 Positives

- **`declare(strict_types=1)` in 100% of `src/`**; `DateTimeImmutable` everywhere; no mutable `DateTime`; no `DateTimeInterface` unions — and the ban is *tested* (`ArchShapeTest::testDateTimeImmutableOnly`).
- **Pure domain:** no I/O, no network, no framework types; `curl_`/`exec`/`shell_exec`/http-URL bans are *tested* (`ArchShapeTest::testOfflineOnly`).
- **No clock reads in the domain** — `$nowUtc` is threaded explicitly.
- **Error handling is typed and layered:** `AlFalaqException` (abstract) → `ValidationException` / `AstronomicalException` / `ConfigurationException` / `InvalidCoordinatesException` / `HijriOutOfRangeException` / `UndefinedScheduleException`, with `DomainException` as a catch-all marker for host apps.
- **Float-safety is handled deliberately:** `ValidatesFinite` rejects bools and non-finite input; `Angle::normalized()` re-clamps `>= 360.0` because `fmod` on a value a few ULPs below zero rounds to exactly `360.0` — with the reasoning written down in a comment. This is the kind of detail most libraries get wrong.
- **PHPStan max + strict-rules is clean** over `src/` *and* `tests/`.

### 5.2 Defects

| ID | Severity | Finding |
|---|---|---|
| **P16** | Medium | **`CalculationParameters` wither boilerplate.** Ten `with*()` methods each repeat the full 15-argument constructor — ~430 of the file's 591 lines are near-identical. A future field addition means ten coordinated edits, and a missed one silently drops a field. PHP 8.2's `readonly` rules out the `clone`-with pattern, but a single private `copyWith(array $overrides)` helper (or an object-backed parameter bag) would collapse ~430 lines to ~100 and make the field set appear once. |
| **P13** | Low | **Duplicated class docblock paragraph.** `src/Astro/Services/MeanSolarCalculator.php:19-27` states "Default SolarDayEngine adapter: …" twice, with different wording. |
| **P14** | Low | **Blank-line gaps left by deleted code.** `CrescentEphemeris.php:523-536` (14 blanks), `MeanSolarCalculator.php:393-405` (13 blanks), plus 3- and 4-blank gaps in three other places. `git log -p` confirms these are where methods were removed during the `SharesSolarTheory` refactor. php-cs-fixer `@PER` does not collapse them, so CI stays green. |
| **P15** | Low | **Inline FQCNs instead of imports** — 7 occurrences in 3 files (`PrayerTimesCalculator.php:170-173,204`, `SunnahCalculator.php:65,74`, `UndefinedScheduleException.php:64-65`). The rest of the codebase imports normally; these look like leftovers from the `*Interface`-suffix import-collision workaround, which does not apply to these names. |
| **P23** | Low | **Inconsistent validation in `Mabims1992Evaluator`.** The null-age branch (lines 35-44) returns before the finiteness check on lines 46-48, so the no-moonset path accepts non-finite `moonAltitude`/`elongationDeg` while the other three evaluators always validate. |
| **P17** | Low | **Convention drift — `Traits/` with public methods.** `AGENTS.md` says traits hold "stateless, `private` helpers". `src/Hijri/Traits/LooksUpMonthStarts.php` exposes `public function fromGregorian/toGregorian/monthLength` and `src/Hijri/Traits/ShiftsHijriDays.php` exposes `public function shift`. The methods are contract implementations, so public is arguably necessary — but the convention as written is not what the code does. |
| **P18** | Low | **Convention drift — public services without `default()`.** `AGENTS.md` says `Services/` get a `default()` factory. `SunnahCalculator`, `NextPrayerResolver`, and `TimetableService` have none (the latter two take no constructor arguments, so a factory would be pointless — but the rule does not say so). |

---

## 6. Dead-end and vestigial code

Nothing in the hot path is dead. Three seams, however, carry no weight:

| ID | Dead code | Why it is dead |
|---|---|---|
| **P10** | **`src/SharedKernel/Contracts/ClockView.php`** | The most clear-cut dead-end in the repo. It is **never implemented, never referenced, and never documented** — not in `README.md`, `ARCHITECTURE.md`, `docs/`, `docs/user/`, or the API reference. The library's entire clock policy is "the domain never reads the clock; callers pass `$nowUtc` explicitly" — a port for reading the clock contradicts that policy. The `psr/clock` suggestion in `composer.json` concerns *host-app* adapters, not this interface. It is a YAGNI artifact from an earlier design. |
| **P11** | **`Astro\ValueObjects\SolarPosition` + `MeanSolarCalculator::position()`** | `position()` has exactly one caller: its own test. `SolarPosition` has exactly one caller: `position()`. The docblock justifies them as being for "direct consumers", but the class is marked `@internal` — an internal class cannot have stable direct consumers. |
| **P12** | **`MeanSolarCalculator::declination()` / `equationOfTime()`** | Public on an `@internal` class but used only internally (`solarTriplet()`) plus tests. `declination()` is the one method that `CrescentCalculator` does *not* use, since `CrescentEphemeris` computes its own solar geometry. |

Also noted: `PrayerTimesCalculator` exposes five introspection accessors (`solar()`, `night()`, `highLatitudeCorrection()`, `polarCircleHandler()`, `twilightModel()`) used only by tests. These are reasonable, but they widen the public surface for testing convenience; the `*Interface` contracts deliberately omit them, which is a coherent choice and should be stated in the API reference (currently it is not — see P6).

---

## 7. Correctness and robustness

**No correctness defect was found.** I specifically probed the areas most likely to hide one:

- **Prayer ordering invariant** (`fajr ≤ sunrise ≤ dhuhr ≤ asr ≤ maghrib ≤ isha`). The clamp sequence in `PrayerTimesCalculator::calculate()` (lines 284-296) looks suspicious: Asr is clamped up to Dhuhr *before* Maghrib exists, and then clamped down to Maghrib *after*. If `maghrib < dhuhr` were reachable, the second clamp would leave Asr below Dhuhr. I probed 80 schedules across 4 latitudes, 4 dates, and 6 extreme adjustment combinations (including `maghrib −60 / dhuhr +60`): **0 violations**. The invariant holds because solar transit structurally precedes sunset. The clamp order is correct, though a one-line comment saying *why* would prevent a future refactor from "fixing" it.
- **Golden parity:** all 25 golden files load and pass, including the `ALLOWED_EMPTY_CELLS` allow-list that is bidirectionally enforced (stale entries fail *and* unlisted empties fail).
- **Exception paths:** `maghribAt()`'s deliberate asymmetry (no polar resolve, no tomorrow-rise probe) is documented in a 9-line docblock and is the correct behaviour — maghrib never consumes night length.

Two robustness observations:

| ID | Severity | Finding |
|---|---|---|
| **P8** | Low | **`GregorianToHijriConverter::maghrib()` catches only `UndefinedScheduleException`** (`GregorianToHijriConverter.php:131`), not the broader `AstronomicalException` it extends. `PrayerTimesCalculator::calculate()` can also throw a plain `AstronomicalException` for "Unable to compute Asr" or "Unable to compute Maghrib". Those escape the intended `maghribAt()` fallback. Catching `AstronomicalException` would widen the fallback to every "schedule undefined" case while still letting genuine validation errors surface. |
| **P7** | Low | **`NightResolver` computes `$portions` that are then discarded.** For `SEASONAL_TWILIGHT` and for the MSC method, `$useSeasonalSafe` is true, so the `elseif ($portions === null)` / `else` branches are unreachable and the eagerly-computed portions are never read. The code comment at lines 65-67 says the SEASONAL_TWILIGHT arm "only consults these portions on the non-seasonal fallback path" — **that path cannot exist**, because `$useSeasonalSafe` is unconditionally true for that rule. The comment describes behaviour the code does not have. |

---

## 8. Performance

| ID | Severity | Finding |
|---|---|---|
| **P1** | Medium | **The bench overshoots its own printed budget**: measured single-day median **2.373 ms** against the printed "M5 budget p50 < 2ms" (`examples/bench.php:46`). The enforced gate is 50 ms, so this is invisible in CI. The year (862 ms vs 1,000 ms) and memory (6.0 MB vs 16 MB) budgets hold. |
| **P2** | Medium | **~20% of the solar-triplet cost is redundant.** `MeanSolarCalculator::solarTriplet()` (lines 434-455) computes `$century`, `$lambda`, and `$epsilonApp`, then calls `$this->declination($julianDay)` — which recomputes **all three** from scratch before doing its one `asin(sin·sin)`. All three are pure functions of `$century` (`src/Astro/Traits/SharesSolarTheory.php`), so this is a pure recomputation, not a semantic choice. Measured: one `declination()` call costs 0.0113 ms versus 0.057 ms for a full triplet → **19.8% of every triplet**. A single `calculate()` runs ~25 triplets (`isSolarDefined` ×4, two `solarTime` ×3, plus 5-6 `hourAngle`/`afternoon` calls ×3). The docblock calls the reuse "a single source of truth", which is a reasonable motive — but the single source of truth is the *value*, not the *method*, and inlining the final `asin` over the already-computed `$epsilonApp`/`$lambda` keeps both. Fixing this alone should bring the p50 back under the 2 ms budget. |

Secondary note: `SharesSolarTheory::nutationLongitude()` and `nutationObliquity()` each independently recompute `ascendingNode()`, `meanSolarLongitude()`, and `meanLunarLongitude()`, and `solarTriplet()` calls both — so each of those three values is computed twice per triplet, and `meanSolarLongitude`/`meanObliquity` are computed again inside `apparentLongitude`/`apparentObliquity`. The same "compute once, share" refactor would address all of these.

---

## 9. Test suite: coverage and quality

### 9.1 Coverage

**97.2% lines** (floor 95), **97.2%** overall. The floor is a ratchet (`tools/coverage_gate.php --min=95`) that has only ever moved up (20 → 80 → 85 → 95).

Per-class outliers (all still above the floor):

| Class | Methods | Lines |
|---|---:|---:|
| `SharedKernel\ValueObjects\Minutes` | 0% (0/3) | 80% (12/15) |
| `SharedKernel\ValueObjects\Angle` | 75% (3/4) | 88.9% (8/9) |
| `SharedKernel\ValueObjects\Coordinates` | 66.7% (4/6) | 85.7% (12/14) |
| `SharedKernel\ValueObjects\CivilDate` | 66.7% (6/9) | 88.9% (24/27) |
| `Qibla\Strategies\Wgs84KarneyCalculator` | 50% (1/2) | 90.9% (10/11) |
| `Prayer\ValueObjects\PrayerSchedule` | 83.3% (5/6) | 95.8% (23/24) |
| `Prayer\ValueObjects\SunnahTimes` | 75% (3/4) | 93.3% (28/30) |

`Minutes` at 0% *method* coverage is the notable one: its two public methods are exercised only indirectly through the pipeline, never directly. Given `Minutes::roundedHalfUp` carries a UTC-only guard and a half-up rule, and `Minutes::add` is the universal offset primitive, direct unit tests would be cheap and valuable. Same for `Angle::toRadians()` and `Coordinates::__toString()`.

### 9.2 Quality — what is strong

- **5,140 golden rows** across 25 CSVs, each citing its source (`py:a7baa8b202790d3bc84c47a610ac763be6c0b7e7`) or its generator.
- **Bidirectional allow-list enforcement** on empty cells (`PrayerGoldensTest`): an unlisted empty cell fails the build *and* a stale allow-list entry fails the build. This is the strongest anti-rot pattern in the suite (issue #29).
- **Golden file set is asserted explicitly** — a missing or extra CSV fails.
- **Duplicate-row detection** keyed on `method|date|latitude|longitude`, with coordinates in the key so an exemption cannot leak across locations.
- **Seeded property sweeps** (`tests/Sweep/`) with `mt_srand(1234)` inline per test, asserting idempotence and round-trip invariants over random inputs.
- **Ordering sweep** over 18 methods × 200 random dates/latitudes, asserting the six-way prayer ordering invariant with contextual failure messages.
- **Architecture tests carry self-tests for their own detection logic** (see §3.1) — a rarity worth preserving.
- **Golden CSV schema is asserted** (header shape) and duplicate rows are forbidden per file.

### 9.3 Quality — gaps

| ID | Severity | Finding |
|---|---|---|
| **P5** | Medium | **Golden coverage is sharply asymmetric across methods.** Pin-parity methods (MWL, Egyptian, Karachi, UQU, Dubai, MSC, NA, Kuwait, Qatar, Singapore) carry **48 rows each** (6 dates × 8 cities). Drift-gated methods (Tehran, Morocco, UOIF) carry **48 rows** (6 dates × 1 city). But the four T2-provisional methods — **Turkey, Kemenag, MUIS, Brunei — carry only 2 rows each** (2 dates × 1 city), i.e. **24× less coverage than the pin-parity set**. These four are precisely the methods with the documented no-fit residual cells, the provisional ihtiyat/temkin offsets, and the biggest documented uncertainty (Brunei Doha +24 min is an unsolved structural mismatch). The weak evidence sits exactly where the uncertainty is. Extending these four to the full 6-date × 1-city grid would cost ~16 rows each and materially raise confidence. |
| — | Low | **53 of 92 `src/` classes have no dedicated test file.** Most are exercised indirectly (traits, contracts, enums), and 97.2% coverage means nothing is untested in the line sense. But `Minutes`, `Angle`, `Coordinates`, and `CivilDate` have no dedicated file at all — see §9.1. |
| — | Low | **No test asserts `MakkahHandler`'s coordinates match `GeodesyCalculator::MAKKAH_LATITUDE/LONGITUDE`** — see P4. A two-line test would close the drift risk permanently. |
| **P3** | Medium | **The mutation gate cannot be run locally.** `composer mutation` dies with *"The process 'infection --threads=4' exceeded the timeout of 300 seconds"* — Composer's default `process-timeout` is a hard 300 s wall clock (verified in `Composer\Config.php:36`; no output-based reset exists in Composer 2.9.7 or Symfony `Process`). CI survives only because `shivammathur/setup-php` exports `COMPOSER_PROCESS_TIMEOUT: 0` (confirmed in the CI log). A contributor following `CONTRIBUTING.md`/`AGENTS.md` gets a spurious failure that looks like a broken repo. Fix: document `COMPOSER_PROCESS_TIMEOUT=0 composer mutation`, or set `config.process-timeout: 0` in `composer.json` (safe — the only long-running script is `mutation`). **For the record, the gate itself is healthy: MSI 90% / covered MSI 91% in CI, 91% / 93% locally, against an 85% floor.** |

---

## 10. CI/CD and supply chain

### 10.1 What is right

- **Matrix:** PHP 8.2 / 8.3 / 8.4 × UTC, plus one `America/New_York` leg on 8.4 — chosen to prove DST-independence, which is the library's central correctness claim. A separate Windows gate proves PHP's bundled tzdata needs no OS tzdata.
- **Static gate once on 8.4** (lint/cs/stan/audit), **matrix gate for tests + bench** — a sensible cost split.
- **Zero locked runtime packages** is asserted *in CI*, not just in the manifest.
- **`config.platform.php = 8.2.0`** keeps the lock installable across 8.2-8.4 — the classic Composer footgun is explicitly documented in `AGENTS.md`.
- **Release flow is genuinely gated:** tag format → `main` ancestry → exact dated CHANGELOG header → docs snapshot directory → version JSON → `versions.json` entry, *then* release — and the release job `needs:` the Windows gate.
- **Tag name is never interpolated into the shell**: it is passed via `env:` and read as `$TAG`, with the reasoning documented in a comment. Correct handling of a genuinely common injection vector.
- **`--generate-notes`** used for the release body, so the GitHub release never diverges from the hand-written CHANGELOG.

### 10.2 Gaps

| ID | Severity | Finding |
|---|---|---|
| **P22** | Low | **No `npm audit` gate for the docs site.** `npm audit --omit=dev` reports **3 moderate** advisories (`postcss-selector-parser` / `postcss-nested` via `@expressive-code/core` → `starlight-versions`). These are build-time-only and never shipped in the Composer package, but the repository has no gate and no dependabot config for the npm ecosystem (`.github/dependabot.yml` covers `github-actions` only). |
| — | Low | The docs build job does not fail on build *warnings*, and `docs.yml` triggers on `src/**` — so a source change rebuilds the site (good) — but nothing verifies that the rebuilt site still matches the code it documents (P6). |
| — | Info | `docs_site/` deps are exact-pinned (`0.42.6`, `7.3.8`, `0.10.1`) with a committed `package-lock.json` — good; a `dependabot` entry for `npm` would keep them current. |

---

## 11. Git hygiene

**Clean.** No build artifacts, caches, `__pycache__`, `infection.log`, or `.php-cs-fixer.cache` are tracked. `.gitignore` is precise and explains *why* each entry exists (including the non-obvious `/docs/superpowers/` "never merged" rule). `.gitattributes` `export-ignore`s `tests/`, `examples/`, `tools/`, `.github/`, and the logo, so the Composer dist archive is lean.

- 204 commits, all by one author plus Dependabot — a single-maintainer project.
- Conventional-commit prefixes throughout (`feat`, `fix`, `docs`, `refactor`, `ci`, `chore`).
- Commit messages explain *why*, not *what* — e.g. `fix(review): harden docs route middleware (editUrl overrides, cwd, dates)`.
- Two tags: `v0.1.0`, `v0.1.1`. Both releases have a passing CI run and a matching docs snapshot.
- **Open issues (4):** #16 (official Umm al-Qura table, blocked on obtaining a redistributable table), #12 (MABIMS-1992 pre-conjunction guard, post-1.0.0), and two more of the same shape — all explicitly scoped post-1.0.0 with a "workaround shipped" note and a named follow-up vehicle. **No orphan issues, no stale triage.** This is exemplary issue hygiene.

---

## 12. Convention drift — summary table

`AGENTS.md` / `ARCHITECTURE.md` state these as hard rules. Each was checked against the code:

| Rule (source) | Holds? | Enforcement |
|---|---|---|
| `Contracts/` contains interfaces only | ✅ | ❌ not tested |
| `*Interface` suffix on service contracts, short names elsewhere | ✅ | ❌ not tested |
| `Services/` are `final` | ✅ | ✅ `ArchShapeTest::testServicesAreFinal` |
| `Services/` expose `default()` | ⚠️ 3 public services don't | ❌ not tested |
| `ValueObjects/` are `final readonly` | ✅ | ⚠️ `final` only, not `readonly` |
| `Strategies/` are `final`, one `Contracts/` interface each | ✅ | ❌ not tested |
| `Traits/` expose only `private` helpers | ⚠️ 2 Hijri traits are public | ❌ not tested |
| One class per file | ✅ | ⚠️ implicit via PSR-4 path check only |
| `*Exception` suffix, no `*Error` | ✅ | ✅ `ArchShapeTest::testNoErrorSuffixedFiles` |
| No `Util/` namespace | ✅ | ✅ `ArchShapeTest::testNoUtilNamespaceRegrowth` |
| `DateTimeImmutable` only | ✅ | ✅ `ArchShapeTest::testDateTimeImmutableOnly` |
| Offline (no I/O, no network) | ✅ | ✅ `ArchShapeTest::testOfflineOnly` |
| No framework types in `src/` | ✅ | ❌ not tested (no import scan) |
| Docblocks on all public API | ✅ | ✅ `ArchDocsTest` (4 tests) |
| Layering (3 edges) | ✅ | ✅ `ArchLayeringTest` (3 tests + self-tests) |
| Coverage floor only moves up | ✅ | ✅ `tools/coverage_gate.php` |
| Runtime `composer.json` stays `php`-only | ✅ | ✅ CI assertion |

---

## 13. Prioritised action list

### Tier 1 — do these first (documentation truth + low-risk perf win)

| # | Action | Effort |
|---|---|---|
| 1 | **Inline the declination `asin` over the already-computed `$century`/`$lambda`/`$epsilonApp` in `MeanSolarCalculator::solarTriplet()`** (P2). ~20% off every triplet; should restore the p50 under the printed 2 ms budget. | ~10 lines |
| 2 | **Either tighten the bench gate to the printed budget or stop printing a budget the code does not meet** (P1). A printed "budget p50 < 2ms" that is routinely exceeded is worse than no number. | 1 line |
| 3 | **Fix `composer mutation` for local contributors** (P3): document `COMPOSER_PROCESS_TIMEOUT=0 composer mutation`, and/or set `config.process-timeout: 0`. | 1 line + doc |
| 4 | **Lift the Kaaba coordinates to a single owner** (P4) and add a test asserting `MakkahHandler`'s coordinates equal `GeodesyCalculator::MAKKAH_LATITUDE/LONGITUDE`. | ~15 lines |
| 5 | **Refresh `docs/user/api-reference.md`** (P6): add the ~26 omitted classes, or narrow the page's claim from "the entire public surface" to the subset it actually covers. | ~30 lines |
| 6 | **Extend the four T2-method goldens to the 6-date grid** (P5): turkey, Kemenag, MUIS, Brunei — ~16 rows each, the highest-value coverage gap in the suite. | small |

### Tier 2 — correctness-adjacent and structural

| # | Action | Effort |
|---|---|---|
| 7 | Widen `GregorianToHijriConverter::maghrib()`'s catch to `AstronomicalException` (P8). | 1 line |
| 8 | Fix the `NightResolver` seasonal-path comment and drop the eagerly-computed `$portions` on that path (P7). | ~10 lines |
| 9 | Collapse `CalculationParameters`' ten withers into one `copyWith()` helper (P16). | ~1-2 h |
| 10 | Delete `ClockView` (P10) — it is referenced nowhere — or implement it and document it. Delete `SolarPosition`/`position()` (P11) or document the "direct consumer" story. | ~20 lines |
| 11 | Add the missing arch assertions (P21): Contracts-are-interfaces, `*Interface` suffix rule, one-class-per-file, ValueObjects-readonly, Traits-private, Services-final+default. Six small loops; all six currently hold, so they are zero-risk to add. | ~1 h |
| 12 | Add `docs/integrations.md` recipes for Moon, Hijri, Timetable, and Sunnah, with matching `tests/Wiring/` coverage (P19) — this is what `ARCHITECTURE.md` already promises. | ~1 h |
| 13 | Extend the `Wiring` test to **parse** `docs/integrations.md` rather than re-implementing the snippets, so doc and code cannot drift. | ~30 lines |

### Tier 3 — hygiene polish

| # | Action | Effort |
|---|---|---|
| 14 | Tidy: duplicate docblock paragraph (P13), blank-line gaps (P14), inline FQCNs (P15). | ~20 min |
| 15 | Reconcile the two changelogs, or generate `docs/user/changelog.md` from `CHANGELOG.md` and add a release-gate check (P20). | ~30 min |
| 16 | Add `npm` to `dependabot.yml` and an `npm audit --audit-level=high` step to `docs.yml` (P22). | ~10 lines |
| 17 | Update `AGENTS.md` to match reality on traits-with-public-methods (P17) and services-without-`default()` (P18). | ~10 lines |
| 18 | Add direct unit tests for `Minutes`, `Angle::toRadians()`, `Coordinates::__toString()`, `CivilDate::compareTo()`. | ~30 lines |
| 19 | Add a comment in `PrayerTimesCalculator::calculate()` explaining why the Asr clamp order (up-to-Dhuhr then down-to-Maghrib) is correct — transit structurally precedes sunset. | 2 lines |
| 20 | Either merge or explicitly scope `docs/` vs `docs/user/` (P24); note that `docs/` is load-bearing because test error messages cite it. | decision |

---

## Appendix A — verification commands

All commands are read-only and were run from the repository root.

```bash
# Gates
composer lint
composer cs
composer stan
composer test-unit          # 436 tests, 49724 assertions
composer test               # coverage ratchet: 97.2% vs floor 95
composer arch
composer bench              # NOTE: prints p50 2.373ms against a "p50 < 2ms" budget
composer validate --strict
composer audit

# Mutation (local: needs COMPOSER_PROCESS_TIMEOUT=0 — see P3)
COMPOSER_PROCESS_TIMEOUT=0 composer mutation
php vendor/bin/infection --threads=4 --no-progress --min-msi=0 --min-covered-msi=0

# Structure probes
find src -name '*.php' | xargs wc -l | sort -rn | head
grep -rn 'AlFalaq\AlFalaq\' --include='*.php' src | grep -v ':use '   # inline FQCNs
for f in $(find src -path '*/Contracts/*.php'); do grep -qE '^\s*interface ' "$f" || echo "NON-INTERFACE: $f"; done
awk '/^[[:space:]]*$/{c++;next}{if(c>=3)print FILENAME": "c" blanks";c=0}' $(find src -name '*.php')

# Docs
cd docs_site && npm install && npm run build && npm audit --omit=dev
cd ../ && for f in $(find src -name '*.php' | grep -vE '/(Astro|Data)/'); do
  short=$(basename "$f" .php); grep -q "$short" docs/user/api-reference.md || echo "UNDOCUMENTED: $short"; done

# Git / CI
git ls-files | grep -E 'pycache|infection.log|\.cache'     # expect: empty
git status --ignored --short
gh run list --repo Al-Falaq/al-falaq --limit 10
gh api repos/Al-Falaq/al-falaq/actions/runs/<id>/jobs --jq '.jobs[] | .steps[] | "\(.name) \(.started_at) \(.completed_at)"'
```

---

## Appendix B — full metrics table

| Metric | Value | Source |
|---|---|---|
| `src/` files / lines | 92 / 10,284 | `find src -name '*.php' \| xargs wc -l` |
| `src/` public / private methods | 178 / 83 | `grep -c 'function'` |
| Interfaces / enums / traits | 16 / 7 / 7 | `grep -l '^interface\|^enum\|^trait'` |
| Docblocks in `src/` | 449 | `grep -c '/\*\*'` |
| `tests/` files / lines | 53 / 9,448 | `find tests -name '*.php'` |
| Test methods / assertions | 436 / 49,724 | `composer test-unit` |
| Golden CSVs / rows | 25 / 5,140 | `tests/Goldens/*.csv` |
| Line coverage | **97.2%** (floor 95) | `composer test` |
| Mutation MSI / covered MSI | **90% / 91%** CI, 91% / 93% local (floor 85) | `composer mutation` |
| PHPStan | **max + strict-rules, 0 errors** | `composer stan` |
| php-cs-fixer `@PER` | **0 of 156 files** need fixing | `composer cs` |
| Bench single-day p50 | 2.373 ms (printed budget 2 ms; gate 50 ms) | `composer bench` |
| Bench year | 862 ms (budget 1,000 ms) | `composer bench` |
| Bench peak memory | 6.0 MB (budget 16 MB) | `composer bench` |
| Docs pages built / broken internal links | 58 / **0** | `docs_site/dist` |
| Docs-site npm advisories | 3 moderate (build-time) | `npm audit --omit=dev` |
| Tracked files | 291 | `git ls-files \| wc -l` |
| Tracked build artifacts | **0** | `git ls-files \| grep -E 'pycache\|infection.log\|\.cache'` |
| Commits / authors | 204 / 1 + Dependabot | `git log` |
| Tags | `v0.1.0`, `v0.1.1` | `git tag` |
| Open issues | 4 (all deferred post-1.0.0 with named vehicles) | GitHub API |

---

*Report generated 2026-10-22 against `ebcc3aa`. No source file was modified in the course of this review.*
