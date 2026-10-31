# Tier 3 Hygiene Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Land the remaining Tier-3 findings from `REPO_HYGIENE_REPORT.md` as one pull request: tidy the last code-style residue, give the shared value objects direct tests, document the Asr clamp invariant, make the published changelog a generated artefact of the repository changelog, gate docs-site dependencies, and explicitly scope the contributor `docs/` tree against the published `docs/user/` tree.

**Architecture:** Seven individually-revertable changes. Tasks 1–3 are local hygiene/coverage work with no behaviour change. Task 4 makes `CHANGELOG.md` the single source for release history and adds a `--check` gate used by `composer ci`, the static CI job, and the release workflow. Task 5 closes the npm advisories before adding the audit gate, so the new gate starts green. Task 6 chooses *explicit scoping* over merging the two doc trees: `docs/` remains the contributor-facing, error-message-linked record, while `docs/user/` remains the published user guide; a map plus arch tests make that boundary executable. Task 7 is the final documentation and changelog sync.

**Tech Stack:** PHP 8.2+ (no runtime dependencies), PHPUnit 11, PHPStan max + strict-rules, php-cs-fixer `@PER`, Infection, Astro Starlight docs site, npm, plain-Python/PHP tooling under `tools/`.

**Plan location note:** the `writing-plans` skill defaults to `docs/superpowers/plans/`, but this repository gitignores `/docs/superpowers/` ("development-only brainstorming artefacts — never merged"). The plan must be committed with the work, so it follows the Tier-1/Tier-2 precedent at `docs/plans/2026-10-22-tier1-hygiene.md` and `docs/plans/2026-10-25-tier2-hygiene.md`.

**Branch:** `fix/tier3-hygiene-2026-10-31` (split from `main` at `acf29ab`, the Tier-2 merge).

**Baseline (verified on `main` at `acf29ab`):** 471 tests / 50,103 assertions, 25 arch tests, 0 PHPStan errors (142 files), 0 php-cs-fixer findings (158 files), `composer arch` green. `npm audit --omit=dev` in `docs_site/` currently reports **2 vulnerabilities: 1 high (`undici` 8.11.2) and 1 moderate (`postcss-selector-parser` 6.1.4)**.

**Frozen artefacts:** `docs/user/v0.1.0/**` and `docs/user/v0.1.1/**` are released snapshots. Only current `docs/user/*.md` files change. Do not regenerate or hand-edit the snapshots.

---

## Re-evaluation: what “Tier 3” means after Tier 1 + Tier 2

Tier 1 and Tier 2 are merged at `acf29ab`. They closed more than their own action lists: two Tier-3 items were fixed as drive-bys, and two Tier-3 items were replaced by stronger gates. The table below is the authoritative re-evaluation; the rest of this plan only covers the rows marked **open** or **partial**.

| Report action | Finding | State on `acf29ab` | Evidence | Treatment |
|---|---|---|---|---|
| 14 | P13 — duplicate class docblock paragraph | **Closed** | `MeanSolarCalculator` now has one “Default SolarDayEngine adapter” paragraph; Tier-2 Task 4 removed the duplicate. | No action. |
| 14 | P14 — blank-line gaps left by deleted code | **Open** | 9 runs of 2+ blank lines in 3 files: `CrescentEphemeris.php` (3), `MeanSolarCalculator.php` (4), `CrescentCalculator.php` (2). | Task 1. |
| 14 | P15 — inline FQCNs instead of imports | **Open** | 11 lines in 3 files: 9 code-position occurrences (`PrayerTimesCalculator.php` 5, `SunnahCalculator.php` 2, `UndefinedScheduleException.php` 2) plus 2 docblock `@param` lines in `UndefinedScheduleException.php`. | Task 1. |
| 15 | P20 — two unsynchronised changelogs | **Open, worse** | `CHANGELOG.md` is 278 lines; `docs/user/changelog.md` is 54 lines and its `[Unreleased]` section still says “No changes yet”. The 0.2.0 entries are absent. `pre-release.yml` validates `CHANGELOG.md` only. | Task 4. |
| 16 | P22 — no npm audit gate / no npm Dependabot | **Open, now high** | `npm audit --omit=dev` reports `undici` 8.11.2 (high) and `postcss-selector-parser` 6.1.4 (moderate). `.github/dependabot.yml` covers `github-actions` only; `docs.yml` has no audit step. | Task 5. |
| 17 | P17/P18 — AGENTS wording drift on traits/services | **Closed** | `AGENTS.md:76-90` now documents the real trait/service exceptions; `ArchShapeTest::testTraitsExposeOnlyPrivateHelpers()`, `testServicesExposeDefaultFactory()`, and `testConventionAllowListsDoNotRot()` enforce them. | No action. |
| 18 | Direct unit tests for `Minutes`, `Angle::toRadians()`, `Coordinates::__toString()`, `CivilDate::compareTo()` | **Partial** | `Minutes::roundedHalfUp()`, `Angle::normalized()`, `Coordinates::equals()`, and `CivilDate::equals()` already have direct coverage in `tests/SharedKernel/SharedKernelTest.php`. `Minutes::add()`, `Angle::toRadians()`, `Coordinates::__toString()`, and `CivilDate::compareTo()` have no direct assertions. | Task 2. |
| 19 | Comment explaining the Asr clamp order | **Open** | `PrayerTimesCalculator::calculate()` clamps Asr up to Dhuhr, then down to Maghrib, with no explanation. The empty-adjustment ordering sweeps prove `dhuhr <= asr <= maghrib` for their ±65° grid but do not document the clamp order or its precondition. | Task 3. |
| 20 | P24 — `docs/` vs `docs/user/` overlap and stale claims | **Open, worse** | No `docs/README.md`; 8 top-level `docs/*.md` pages overlap 8 user pages; `docs/migration.md` still labels the shipped 0.1.0 architecture batch as “Unreleased”, directly contradicting `docs/user/migration.md`. `src/` cites `docs/prayer.md` and `docs/polar.md` in error messages, so the tree is load-bearing. | Task 6. |

### Closed by Tier 1 / Tier 2 — do not redo

- Tier 1 closed P1 (bench budget), P2 (declination recomputation), P3 (local mutation timeout), P4 (Kaaba single owner), P5 (T2 golden grid), P6 (API reference), and action 13 (wiring test parses `docs/integrations.md`).
- Tier 2 closed P7 (`NightResolver` portions), P8 (Hijri fallback catch), P10 (`ClockView`), P11 (`SolarPosition`/`position()`), P13 (duplicate docblock), P16 (`CalculationParameters` withers), P19 (Moon/Hijri/Timetable/Sunnah wiring recipes), and P21 (six arch gates). It also fixed the P17/P18 wording and added the P13 drive-by.
- P12 (`MeanSolarCalculator::declination()`/`equationOfTime()` public on an `@internal` class) remains deliberately out of scope: Tier 2 kept them as the pin-parity oracle. Do not reopen it here.
- P23 (`Mabims1992Evaluator` null-age branch skipping finiteness checks) is **not** actionable through the public API: `CrescentGeometry` is `final readonly` and its constructor rejects every non-finite numeric field, so the evaluator cannot receive `INF`/`NAN` on that path. Fixing the evaluator would be defensive duplication of a constructor guard. Record it as “unreachable by construction” and leave it out of Tier 3.

### New facts found during this re-evaluation

1. **The docs-site advisory is no longer “3 moderate”.** `undici` 8.11.2 is a high-severity advisory; adding `--audit-level=moderate` before updating the lock would fail immediately. Task 5 updates first, then gates.
2. **The published changelog has missed an entire unreleased release train.** `docs/user/changelog.md` does not mention the 0.2.0 `ClockView` removal, the `TWILIGHT_ANGLE` break, or any Tier-1/Tier-2 change. This is stronger evidence for generation than the original report had.
3. **`docs/migration.md` has a factual contradiction, not just overlap.** It calls the architecture batch “Unreleased” while `docs/user/migration.md` correctly places it under 0.1.0. Task 6 fixes the label and points readers at the live user guide.
4. **The repo is green at a larger baseline than the report.** 471 tests / 50,103 assertions, 25 arch tests, 142 PHPStan files, 158 CS-fixer files. Tier 3 must not lower any of those.

---

## Scope and decisions

### In scope

- P14/P15 code tidy, plus arch gates so the tidy cannot rot.
- Direct unit tests for the four uncovered value-object primitives.
- The Asr clamp-order comment.
- P20 single-source changelog generation and release/CI gates.
- P22 npm audit gate, npm Dependabot entry, and the dependency refresh needed to start green.
- P24 explicit documentation scope map, stale `docs/migration.md` heading, cross-links, and arch gates.

### Out of scope

- P12 (the pin-parity oracle methods) — deliberately kept by Tier 2.
- P23 (unreachable evaluator guard) — see above.
- Any new runtime dependency, golden-vector change, public API change, or framework adapter.
- Rewriting historical records: `REPO_HYGIENE_REPORT.md`, `docs/plans/2026-10-22-tier1-hygiene.md`, and `docs/plans/2026-10-25-tier2-hygiene.md` keep their original statements.
- Re-freezing or editing `docs/user/v0.1.0/**` and `docs/user/v0.1.1/**`.

### Decision 1 — Keep `docs/` and `docs/user/` separate; make the boundary explicit

Merging the trees would either publish contributor internals or break the error-message links that point at `docs/prayer.md` and `docs/polar.md`. `docs/integrations.md` is also already executable and gated by `tests/Wiring/`, and the API reference gate covers the public surface. The right move is to declare the ownership boundary, fix the one stale page, link each contributor page to its user counterpart, and add tests that keep the map and error-message links honest.

### Decision 2 — Generate `docs/user/changelog.md` from `CHANGELOG.md`

The manual copy has already drifted through an entire release train. Generation removes the second edit site; `--check` makes drift a build failure. `CHANGELOG.md` remains the only file a contributor edits.

### Decision 3 — Update npm dependencies before adding the audit gate

The gate must be added to a green tree, not used as a discovery tool for an already-known high advisory. `npm audit fix --package-lock-only` in a scratch copy cleanly moves `undici` to 8.12.3 and `postcss-selector-parser` to 6.1.5, after which `npm audit --omit=dev --audit-level=moderate` exits 0. Task 5 applies that update first.

### Decision 4 — Add arch gates for the two tidy rules

The blank-line runs and inline FQCNs are exactly the kind of residue that returns after a refactor. The gates are cheap, the current tree will pass after Task 1, and they match the repository’s existing “make the claim executable” style.

---

## File Structure

| File | Responsibility | Task |
|---|---|---|
| `src/Prayer/Services/PrayerTimesCalculator.php` | Import `MeanSolarCalculator`; explain the Asr clamp order | 1, 3 |
| `src/Prayer/Services/SunnahCalculator.php` | Import `ValidationException` and drop the two inline FQCNs | 1 |
| `src/Prayer/Exceptions/UndefinedScheduleException.php` | Use the already-imported `Coordinates`/`CivilDate` names | 1 |
| `src/Astro/Services/CrescentEphemeris.php`, `src/Astro/Services/MeanSolarCalculator.php`, `src/Moon/Services/CrescentCalculator.php` | Collapse the deleted-code blank-line runs | 1 |
| `tests/ArchShapeTest.php` | Gate inline source FQCNs, consecutive blank lines, and the scanner's own behaviour | 1 |
| `tests/SharedKernel/SharedKernelTest.php` | Direct tests for `Minutes::add()`, `Angle::toRadians()`, `Coordinates::__toString()`, `CivilDate::compareTo()` | 2 |
| `tools/sync_changelog.php` | **Create.** Generate `docs/user/changelog.md` from `CHANGELOG.md`; `--check` for CI/release | 4 |
| `composer.json` | Add `changelog` / `changelog:check`; include the check in `ci` | 4 |
| `docs/user/changelog.md` | Regenerated; never hand-edited | 4, 7 |
| `.github/workflows/test.yml` | Run `changelog:check` in the static CI job | 4 |
| `.github/workflows/pre-release.yml` | Verify the published changelog is current before releasing | 4 |
| `docs_site/package-lock.json` | Refresh `undici` and `postcss-selector-parser` | 5 |
| `docs_site/package.json` | Add a local `audit` script | 5 |
| `.github/workflows/docs.yml` | Run the docs-site audit in the build job | 5 |
| `.github/dependabot.yml` | Add the `npm` / `/docs_site` update entry | 5 |
| `docs/README.md` | **Create.** Contributor-vs-user scope map and editing rules | 6 |
| `docs/migration.md` | Rename the stale “Unreleased” batch; link the live user migration guide | 6 |
| `docs/{prayer,qibla,moon,hijri,sunnah,polar,migration,integrations}.md` | Add “See also” links to their user-facing counterparts | 6 |
| `tests/ArchDocsTest.php` | Gate error-message doc targets, the docs map, and map links | 6 |
| `AGENTS.md`, `ARCHITECTURE.md`, `CONTEXT.md`, `README.md` | Document the new gates, changelog workflow, and docs scope | 7 |
| `CHANGELOG.md` | `[Unreleased]` entry for the Tier-3 work | 7 |

---

## Task 1: Tidy inline FQCNs and blank-line gaps (P14, P15)

**Finding:** the report found 7 inline FQCN occurrences in 3 files; the current tree has 11 lines: 9 in code position (`PrayerTimesCalculator.php` 5, `SunnahCalculator.php` 2, `UndefinedScheduleException.php` 2) plus two `@param` lines in `UndefinedScheduleException.php`. The tokenizer gate below catches the 9 code occurrences; the two docblock lines are fixed in Step 3 but are not gated, because `AGENTS.md` permits FQCNs in docblocks when a file has no import. The blank-line runs are the residue of the `SharesSolarTheory` refactor; php-cs-fixer does not collapse them.

**Files:**
- Modify: `src/Prayer/Services/PrayerTimesCalculator.php:1-32`, `:166-177`, `:200-210`
- Modify: `src/Prayer/Services/SunnahCalculator.php:1-12`, `:60-77`
- Modify: `src/Prayer/Exceptions/UndefinedScheduleException.php:54-67`
- Modify: `src/Astro/Services/CrescentEphemeris.php:414`, `:497`, `:523`
- Modify: `src/Astro/Services/MeanSolarCalculator.php:396`, `:426`, `:473`, `:567`
- Modify: `src/Moon/Services/CrescentCalculator.php:259`, `:281`
- Test: `tests/ArchShapeTest.php` (append before `testConventionAllowListsDoNotRot()`)

- [ ] **Step 1: Write the failing arch gates**

Append these methods to `tests/ArchShapeTest.php`. The test class already extends `ArchSupport`, so `srcPhpFiles()` is available. The helper is deliberately a separate method so its scanner behaviour can be asserted independently.

```php
    /**
     * Source must import its own names, never inline a fully-qualified name.
     *
     * The tokenizer sees `use`/`namespace` as T_NAME_QUALIFIED and docblocks
     * as T_DOC_COMMENT, so only a real code reference (`new \AlFalaq\...`)
     * trips the gate.
     */
    public function testNoInlineSourceFqcns(): void
    {
        $violations = [];
        $checked = 0;

        foreach (self::srcPhpFiles() as $path) {
            $checked++;

            foreach (self::inlineSourceFqcns((string) file_get_contents($path)) as $found) {
                $violations[] = sprintf('%s:%d: inline %s; import it instead.', $path, $found['line'], $found['name']);
            }
        }

        self::assertGreaterThanOrEqual(90, $checked, 'The source scan found too few files; the gate would pass vacuously.');
        self::assertSame([], $violations);
    }

    /**
     * Scanner regression probe: imports, namespaces, and docblocks are not
     * inline references.
     */
    public function testInlineFqcnScannerIgnoresImportsAndComments(): void
    {
        $code = <<<'PHP'
<?php
namespace Probe;

use AlFalaq\AlFalaq\Imported;

/** \AlFalaq\AlFalaq\Docblock is prose, not code. */
final class Probe
{
    public function make(): object
    {
        return new \AlFalaq\AlFalaq\Inline();
    }
}
PHP;

        self::assertSame(
            ['\\AlFalaq\\AlFalaq\\Inline'],
            array_column(self::inlineSourceFqcns($code), 'name'),
        );
    }

    /**
     * Every src file uses at most one consecutive blank line.
     */
    public function testNoConsecutiveBlankLines(): void
    {
        $violations = [];
        $checked = 0;

        foreach (self::srcPhpFiles() as $path) {
            $checked++;
            $code = (string) file_get_contents($path);

            if (preg_match_all('/(?:\r?\n[ \t]*){3,}/', $code, $matches, PREG_OFFSET_CAPTURE) === false) {
                continue;
            }

            foreach ($matches[0] as [$match, $offset]) {
                $line = substr_count(substr($code, 0, (int) $offset), "\n") + 1;
                $violations[] = sprintf('%s:%d: %d consecutive blank lines.', $path, $line, substr_count($match, "\n") - 1);
            }
        }

        self::assertGreaterThanOrEqual(90, $checked, 'The source scan found too few files; the gate would pass vacuously.');
        self::assertSame([], $violations);
    }

    /**
     * Inline FQCNs in a source string (comments and imports excluded).
     *
     * @param string $code PHP source.
     * @return list<array{name: string, line: int}> Fully-qualified names in code position.
     */
    private static function inlineSourceFqcns(string $code): array
    {
        $names = [];

        foreach (token_get_all($code) as $token) {
            if (is_array($token)
                && $token[0] === T_NAME_FULLY_QUALIFIED
                && str_starts_with($token[1], '\\AlFalaq\\AlFalaq\\')
            ) {
                $names[] = ['name' => $token[1], 'line' => $token[2]];
            }
        }

        return $names;
    }
```

- [ ] **Step 2: Run the new gates to verify they fail**

Run:

```bash
vendor/bin/phpunit --no-coverage --filter 'testNoInlineSourceFqcns|testInlineFqcnScannerIgnoresImportsAndComments|testNoConsecutiveBlankLines' tests/ArchShapeTest.php
```

Expected: **FAIL**. `testInlineFqcnScannerIgnoresImportsAndComments` passes; `testNoInlineSourceFqcns` names the 9 code-position occurrences; `testNoConsecutiveBlankLines` names the 9 runs in `CrescentEphemeris.php`, `MeanSolarCalculator.php`, and `CrescentCalculator.php`.

- [ ] **Step 3: Replace the inline FQCNs with imports**

In `src/Prayer/Services/PrayerTimesCalculator.php`, add the missing import beside the other engine/strategy imports:

```php
use AlFalaq\AlFalaq\Astro\Services\MeanSolarCalculator;
```

Then replace both `new \AlFalaq\AlFalaq\Astro\Services\MeanSolarCalculator()` calls (in `default()` and `defaultFor()`) with:

```php
new MeanSolarCalculator(),
```

The same `default()` method also inlines `MiddleOfNightCorrection`, `NearestLatitudeHandler`, and `SeasonAdjustedTwilight`. Those three classes are already imported and are used by short name in `defaultFor()`, so replace the three inline references with their short names as well.

In `src/Prayer/Services/SunnahCalculator.php`, add:

```php
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;
```

Then replace both `\AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException` occurrences with `ValidationException`.

In `src/Prayer/Exceptions/UndefinedScheduleException.php`, the file already imports `CivilDate` and `Coordinates`. Replace the two docblock `@param \AlFalaq\AlFalaq\SharedKernel\ValueObjects\...` lines and the two `polar()` signature types with the short imported names (the constructor already uses them):

```php
     * @param Coordinates $coordinates Observer location.
     * @param CivilDate $date Civil date with no sunrise/sunset.
```

```php
        Coordinates $coordinates,
        CivilDate $date,
```

- [ ] **Step 4: Collapse the blank-line runs**

In each file below, replace every run of two or more blank lines with exactly one blank line. Do not touch docblock content, string literals, or method bodies; these are only gaps between methods.

- `src/Astro/Services/CrescentEphemeris.php`: after `greenwichSiderealDegrees()` / before `correctedHourAngle()`, after `bodyAltitude()`, and at the end of the class after `finiteGeo()`.
- `src/Astro/Services/MeanSolarCalculator.php`: after `isSolarDefined()`, after `closestAngle()`, after `solarAltitude()`, and after `transitHours()`.
- `src/Moon/Services/CrescentCalculator.php`: after `evaluateGeometry()` and at the end of the class.

Verify with:

```bash
for f in $(find src -name '*.php'); do
  awk '/^[[:space:]]*$/{c++;next}{if(c>=2)print FILENAME": "c" blanks before line "FNR;c=0} END{if(c>=2)print FILENAME": "c" trailing blanks from line "FNR-c+1}' "$f"
done
```

Expected: no output. The per-file loop reports runs that reach end-of-file too, and it does not leak the blank-line count from one file into the next; the arch gate uses the same `(?:\r?\n[ \t]*){3,}` shape, so the manual check and the gate agree.

- [ ] **Step 5: Run the gates and the full arch group**

Run:

```bash
composer arch
composer cs
composer stan
```

Expected: all green. The new shape gates pass, CS-fixer still reports 0 of 158 files, PHPStan still reports 0 errors.

- [ ] **Step 6: Commit**

```bash
git add src/Prayer/Services/PrayerTimesCalculator.php \
  src/Prayer/Services/SunnahCalculator.php \
  src/Prayer/Exceptions/UndefinedScheduleException.php \
  src/Astro/Services/CrescentEphemeris.php \
  src/Astro/Services/MeanSolarCalculator.php \
  src/Moon/Services/CrescentCalculator.php \
  tests/ArchShapeTest.php
git commit -m "refactor: import inline FQCNs and collapse deleted-code gaps"
```

---

## Task 2: Direct value-object tests (action 18)

**Finding:** four public primitives have no direct assertions. They are exercised indirectly through the pipeline, so line coverage is high, but a regression in the primitive's own contract would only surface as a distant golden mismatch. These are characterization tests for existing behaviour; they pass immediately, which is expected for coverage backfill.

**Files:**
- Modify: `tests/SharedKernel/SharedKernelTest.php` (append before the final `}`)

- [ ] **Step 1: Add the tests**

Append these methods to `tests/SharedKernel/SharedKernelTest.php`. The file already imports `Angle`, `CivilDate`, `Coordinates`, `Minutes`, `ValidationException`, and `PHPUnit\Framework\TestCase`, so no new imports are needed.

```php
    public function testMinutesAddShiftsForwardAcrossMidnight(): void
    {
        $base = new \DateTimeImmutable('2025-12-31 23:59:00', new \DateTimeZone('UTC'));
        $moved = Minutes::add($base, 2);

        self::assertSame('2026-01-01 00:01:00', $moved->format('Y-m-d H:i:s'));
        self::assertSame('2025-12-31 23:59:00', $base->format('Y-m-d H:i:s'));
    }

    public function testMinutesAddShiftsBackwardAcrossYearBoundary(): void
    {
        $base = new \DateTimeImmutable('2025-01-01 00:01:00', new \DateTimeZone('UTC'));
        $moved = Minutes::add($base, -2);

        self::assertSame('2024-12-31 23:59:00', $moved->format('Y-m-d H:i:s'));
    }

    public function testMinutesAddPreservesCallerZone(): void
    {
        $base = new \DateTimeImmutable('2025-03-01 12:00:00', new \DateTimeZone('Asia/Kuala_Lumpur'));
        $moved = Minutes::add($base, 90);

        self::assertSame('Asia/Kuala_Lumpur', $moved->getTimezone()->getName());
        self::assertSame('2025-03-01 13:30:00', $moved->format('Y-m-d H:i:s'));
    }

    public function testAngleToRadians(): void
    {
        self::assertEqualsWithDelta(0.0, Angle::fromDegrees(0.0)->toRadians(), 1e-12);
        self::assertEqualsWithDelta(M_PI / 2.0, Angle::fromDegrees(90.0)->toRadians(), 1e-12);
        self::assertEqualsWithDelta(M_PI, Angle::fromDegrees(180.0)->toRadians(), 1e-12);
        self::assertEqualsWithDelta(2.0 * M_PI, Angle::fromDegrees(360.0)->toRadians(), 1e-12);
        self::assertEqualsWithDelta(-M_PI / 2.0, Angle::fromDegrees(-90.0)->toRadians(), 1e-12);
    }

    public function testCoordinatesToStringUsesSixDecimals(): void
    {
        self::assertSame('3.139000,101.686900', (string) Coordinates::fromDegrees(3.139, 101.6869));
        self::assertSame('-33.868800,151.209300', (string) Coordinates::fromDegrees(-33.8688, 151.2093));
    }

    public function testCivilDateCompareToOrdersByYearMonthDay(): void
    {
        $base = CivilDate::fromYmd(2025, 1, 15);

        self::assertSame(-1, $base->compareTo(CivilDate::fromYmd(2025, 1, 16)));
        self::assertSame(1, $base->compareTo(CivilDate::fromYmd(2025, 1, 14)));
        self::assertSame(-1, $base->compareTo(CivilDate::fromYmd(2025, 2, 1)));
        self::assertSame(1, $base->compareTo(CivilDate::fromYmd(2024, 12, 31)));
        self::assertSame(0, $base->compareTo(CivilDate::fromYmd(2025, 1, 15)));

        $dates = [
            CivilDate::fromYmd(2025, 2, 1),
            CivilDate::fromYmd(2024, 12, 31),
            CivilDate::fromYmd(2025, 1, 15),
        ];
        usort($dates, static fn (CivilDate $a, CivilDate $b): int => $a->compareTo($b));

        self::assertSame(
            ['2024-12-31', '2025-01-15', '2025-02-01'],
            array_map(static fn (CivilDate $date): string => $date->isoformat(), $dates),
        );
    }
```

- [ ] **Step 2: Run the tests**

Run:

```bash
vendor/bin/phpunit --no-coverage --filter 'testMinutesAdd|testAngleToRadians|testCoordinatesToString|testCivilDateCompareToOrdersByYearMonthDay' tests/SharedKernel/SharedKernelTest.php
```

Expected: all pass. These are characterization tests, not red-first tests: the methods already exist and are correct.

- [ ] **Step 3: Run the full unit suite**

Run:

```bash
composer test-unit
```

Expected: 0 failures; the test count rises from 471 to at least 477 (the exact count may be higher if the filter above matches existing methods).

- [ ] **Step 4: Commit**

```bash
git add tests/SharedKernel/SharedKernelTest.php
git commit -m "test(shared-kernel): cover the remaining value-object primitives"
```

---

## Task 3: Document the Asr clamp order (action 19)

**Finding:** `PrayerTimesCalculator::calculate()` clamps Asr up to Dhuhr before Maghrib exists, then clamps it down to Maghrib once it does. The empty-adjustment ordering sweeps (`tests/Sweep/PrayerOrderingTest.php`, ±65° grid) prove `dhuhr <= asr <= maghrib` for that grid, but the code does not explain the two-step order or its precondition — a future refactor could “simplify” the second clamp and silently change which bound wins. The comment must not claim more than the tests prove: with the full ±60-minute adjustment envelope, high-latitude caller offsets can invert `dhuhr` and `maghrib`, and the current code lets the Maghrib clamp win.

**Files:**
- Modify: `src/Prayer/Services/PrayerTimesCalculator.php:283-296`

- [ ] **Step 1: Add the comment**

Above the first `if ($asr < $dhuhr)` block, add:

```php
        // Asr ordering: clamp up to Dhuhr first, then down to Maghrib once it
        // exists. The second clamp is the final authority, so the schedule
        // always keeps asr <= maghrib; it preserves dhuhr <= asr whenever
        // dhuhr <= maghrib, which the empty-adjustment ordering sweeps assert
        // for the supported grid. Caller offsets are bounded to ±60 minutes
        // but can still invert the anchors at high latitude, and in that case
        // the Maghrib clamp wins by design.
```

- [ ] **Step 2: Run the ordering sweeps and the full unit suite**

Run:

```bash
vendor/bin/phpunit --no-coverage --filter 'Ordering|PrayerOrdering' tests/Sweep
composer test-unit
```

Expected: all pass. The comment is not behaviour-changing.

- [ ] **Step 3: Commit**

```bash
git add src/Prayer/Services/PrayerTimesCalculator.php
git commit -m "docs(prayer): explain the Asr clamp order"
```

---

## Task 4: Single-source the published changelog (action 15 / P20)

**Finding:** `CHANGELOG.md` is canonical and release-gated; `docs/user/changelog.md` is hand-maintained and has already missed the entire 0.2.0 train. The current page is 54 lines, the repository file is 278. Generation removes the second edit site; `--check` makes drift a build failure.

**Files:**
- Create: `tools/sync_changelog.php`
- Modify: `composer.json`
- Regenerate: `docs/user/changelog.md`
- Modify: `.github/workflows/test.yml`, `.github/workflows/pre-release.yml`
- Modify: `README.md`, `AGENTS.md`, `CHANGELOG.md`

- [ ] **Step 1: Create the generator**

Create `tools/sync_changelog.php` with this content. It reads the release sections (from the first `## [` heading onward), drops undated `- TBD` placeholders such as the current `## [1.0.0] - TBD` planning section, and composes the Starlight frontmatter, the current user-facing intro, and the stable footer.

```php
<?php

declare(strict_types=1);

// Single-source the published changelog: CHANGELOG.md is canonical, the
// Starlight page is generated from its release sections. Usage:
//   php tools/sync_changelog.php          # write docs/user/changelog.md
//   php tools/sync_changelog.php --check  # fail if the page is stale
$root = dirname(__DIR__);
$sourcePath = $root . '/CHANGELOG.md';
$targetPath = $root . '/docs/user/changelog.md';
$argv = $_SERVER['argv'] ?? [];
$check = in_array('--check', $argv, true);

$source = file_get_contents($sourcePath);

if ($source === false) {
    fwrite(STDERR, "sync_changelog: cannot read {$sourcePath}.\n");
    exit(1);
}

$source = str_replace(["\r\n", "\r"], "\n", $source);
$releaseStart = strpos($source, '## [');

if ($releaseStart === false) {
    fwrite(STDERR, "sync_changelog: {$sourcePath} has no '## [' release heading.\n");
    exit(1);
}

$releases = rtrim(substr($source, $releaseStart));

// `## [1.0.0] - TBD` is a planning placeholder, not a published release.
// Keep Unreleased and every dated release; drop TBD sections.
$sections = preg_split('/(?=^## \[)/m', $releases, -1, PREG_SPLIT_NO_EMPTY);

if ($sections === false) {
    fwrite(STDERR, "sync_changelog: cannot split {$sourcePath} into release sections.\n");
    exit(1);
}

$sections = array_values(array_filter(
    $sections,
    static fn (string $section): bool => preg_match('/^## \[[^\]]+\] - TBD\b/', $section) !== 1,
));
$releases = implode('', $sections);

if (! str_contains($releases, '## [Unreleased]') || substr_count($releases, '## [') < 2) {
    fwrite(STDERR, "sync_changelog: {$sourcePath} must contain Unreleased plus at least one dated release.\n");
    exit(1);
}

$frontmatter = <<<'MD'
---
title: Changelog
description: Release history for al-falaq — what shipped, what changed, what to do.
---

<!-- Generated by tools/sync_changelog.php from CHANGELOG.md. Do not edit by hand. -->

# Changelog

All notable changes to this project are documented here, following [Keep a Changelog](https://keepachangelog.com/en/1.0.0/) and [Semantic Versioning](https://semver.org/spec/v2.0.0.html). Dates are release dates; the `Unreleased` section tracks the main branch.

Versioned snapshots of the whole documentation site are kept per release — use the version switcher at the top of the page to read the docs as they were for any shipped version.
MD;

$footer = <<<'MD'
## How versioning works here

- **Payload contracts are versioned.** `PrayerSchedule::all()` keys and order, `QiblaResult` shape, `CrescentGeometry` fields, and `HijriDate` shape do not change without a minor bump.
- **Documentation is snapshotted per release.** When a version tag ships, the current user docs are frozen under `docs/user/vX.Y.Z/` with a `docs_site/src/content/versions/vX.Y.Z.json` sidebar snapshot, and `docs_site/versions.json` gains the entry. The `Latest` docs always track `main`.
- **Goldens move with code.** Every numeric behaviour is pinned against golden vectors; intentional divergence updates docs and goldens together, with provenance cited.

## See also

- [Migration](migration/) — upgrade instructions per change
- [Citations](citations/) — sources behind the numbers
MD;

$output = rtrim($frontmatter) . "\n\n" . rtrim($releases) . "\n\n" . $footer . "\n";

if ($check) {
    $existing = is_file($targetPath)
        ? str_replace(["\r\n", "\r"], "\n", (string) file_get_contents($targetPath))
        : '';

    if ($existing !== $output) {
        fwrite(STDERR, "sync_changelog: docs/user/changelog.md is stale; run `composer changelog`.\n");
        exit(1);
    }

    echo "sync_changelog: docs/user/changelog.md is in sync.\n";
    exit(0);
}

if (file_put_contents($targetPath, $output) === false) {
    fwrite(STDERR, "sync_changelog: cannot write {$targetPath}.\n");
    exit(1);
}

echo "sync_changelog: wrote docs/user/changelog.md.\n";
```

- [ ] **Step 2: Regenerate the page and inspect it**

Run:

```bash
php tools/sync_changelog.php
git diff -- docs/user/changelog.md
```

Expected: the page gains the `[Unreleased]` 0.2.0 entries, the detailed 0.1.1/0.1.0 sections, and the generated-file notice, and it does **not** contain the `## [1.0.0] - TBD` planning placeholder. The frontmatter stays the same; the footer remains. No snapshot under `docs/user/v0.1.0/` or `docs/user/v0.1.1/` changes.

- [ ] **Step 3: Add the Composer scripts and the CI gate**

In `composer.json`, add these scripts (keep the existing order where practical):

```json
        "changelog": "php tools/sync_changelog.php",
        "changelog:check": "php tools/sync_changelog.php --check",
```

Change the `ci` script from:

```json
        "ci": ["@lint", "@cs", "@stan", "@test"]
```

to:

```json
        "ci": ["@lint", "@cs", "@stan", "@changelog:check", "@test"]
```

`composer ci` is the local gate; GitHub Actions runs the individual commands, so add the check to the static job in `.github/workflows/test.yml` after `composer install`:

```yaml
      - run: composer changelog:check
```

Without that step, a stale generated page would pass CI and only fail at release time.

- [ ] **Step 4: Add the release-gate check**

In `.github/workflows/pre-release.yml`, the `github-release` job currently checks out and then runs `Verify the tag is releasable`. Add a PHP setup step after the checkout, using the same pinned action as the other jobs:

```yaml
      - name: Setup PHP
        uses: shivammathur/setup-php@eb7c497e18156a6bbabfef1d3a82760b9eda3962 # 2.40.0
        with:
          php-version: '8.4'
          coverage: none
```

Then add this command at the start of the `Verify the tag is releasable` shell block, before the `CHANGELOG.md` header check:

```bash
          php tools/sync_changelog.php --check
```

The release job runs on a tag, so a stale generated page now blocks publishing even if the tag was pushed without a fresh CI run.

- [ ] **Step 5: Document the workflow**

In `README.md` → `## Docs`, add:

```markdown
- `docs/user/changelog.md` is generated from `CHANGELOG.md`. After editing the
  changelog, run `composer changelog`; CI runs `composer changelog:check` and
  fails if the published page is stale.
```

In `README.md` → `### Freezing docs for a release`, add a step 0:

```markdown
0. Update `CHANGELOG.md` (including the exact dated `## [X.Y.Z] - YYYY-MM-DD`
   header), run `composer changelog`, and commit both files before tagging.
```

In `AGENTS.md`, change the canonical gate line and add a workflow note:

```markdown
- Canonical gate: `composer ci` = `lint + cs + stan + changelog:check + test`
```

```markdown
- `composer changelog` regenerates `docs/user/changelog.md` from `CHANGELOG.md`;
  `composer changelog:check` fails when the published page is stale (also in the
  release gate). Never hand-edit the generated page.
```

- [ ] **Step 6: Prove the check catches drift**

Run the happy path first:

```bash
composer changelog:check
```

Expected: `sync_changelog: docs/user/changelog.md is in sync.`

Then prove the failure path without keeping the edit:

```bash
cp docs/user/changelog.md /tmp/opencode/changelog.good
printf '\nstale\n' >> docs/user/changelog.md
composer changelog:check
test $? -eq 1
cp /tmp/opencode/changelog.good docs/user/changelog.md
composer changelog:check
```

Expected: the middle command exits 1 with the stale message; the final command is green again.

- [ ] **Step 7: Commit**

```bash
git add tools/sync_changelog.php composer.json docs/user/changelog.md \
  .github/workflows/test.yml .github/workflows/pre-release.yml README.md AGENTS.md
git commit -m "docs(changelog): generate the published changelog from CHANGELOG.md"
```

The `CHANGELOG.md` bullet for this change is added in Task 7, after all tasks are complete, so the generated page is regenerated once with the final content.

---

## Task 5: Gate docs-site dependencies (action 16 / P22)

**Finding:** the original report saw 3 moderate advisories; the current audit also reports a high-severity `undici` advisory. The docs site is build-time only, but CI executes those packages, so the lockfile needs the same hygiene as the Composer lock. The fix is a lockfile refresh followed by an audit gate and an npm Dependabot entry.

**Files:**
- Modify: `docs_site/package-lock.json`
- Modify: `docs_site/package.json`
- Modify: `.github/workflows/docs.yml`
- Modify: `.github/dependabot.yml`
- Modify: `AGENTS.md`, `CHANGELOG.md`

- [ ] **Step 1: Refresh the vulnerable transitive packages**

Run:

```bash
cd docs_site
npm audit fix --package-lock-only
npm ci
npm audit --omit=dev --audit-level=moderate
```

Expected: the lockfile moves `undici` from 8.11.2 to 8.12.3 and `postcss-selector-parser` from 6.1.4 to 6.1.5; `npm audit` exits 0 with `found 0 vulnerabilities`. This exact pair was verified in a scratch copy before writing this plan. Do not use `--force`; no direct dependency needs a major bump.

- [ ] **Step 2: Add a local audit script**

In `docs_site/package.json`, add to `scripts`:

```json
    "audit": "npm audit --omit=dev --audit-level=moderate"
```

Keep the existing scripts; the resulting block should be:

```json
  "scripts": {
    "dev": "astro dev",
    "build": "astro build",
    "build:logo": "node scripts/build-logo.mjs",
    "preview": "astro preview",
    "astro": "astro",
    "audit": "npm audit --omit=dev --audit-level=moderate"
  },
```

- [ ] **Step 3: Add the audit step to the docs workflow**

In `.github/workflows/docs.yml`, after the `Install dependencies` step and before `Build docs`, add:

```yaml
      - name: Audit dependencies
        run: npm run audit
        working-directory: docs_site
```

The build job still runs on every `docs_site/**`, `docs/user/**`, and `src/**` change, so a vulnerable lockfile fails before the site is deployed.

- [ ] **Step 4: Add the npm Dependabot entry**

Append this entry to `.github/dependabot.yml` (after the existing `github-actions` entry):

```yaml
  # The docs site is a separate npm package. Its dependencies are build-time
  # only (never shipped in the Composer package), but they still execute in
  # CI, so they get the same weekly refresh and the audit gate in docs.yml.
  - package-ecosystem: npm
    directory: /docs_site
    schedule:
      interval: weekly
    open-pull-requests-limit: 5
    commit-message:
      prefix: chore
    groups:
      docs-site:
        patterns:
          - '*'
```

- [ ] **Step 5: Document the gate**

In `AGENTS.md` → `## Development Workflow`, extend the CI paragraph:

```markdown
- Docs-site dependencies are audit-gated in `docs.yml`
  (`npm audit --omit=dev --audit-level=moderate`); Dependabot tracks both
  `github-actions` and the `npm` package in `docs_site/`.
```

- [ ] **Step 6: Verify**

Run:

```bash
cd docs_site
npm run audit
npm run build
```

Expected: audit exits 0; the site builds with 0 broken internal links.

- [ ] **Step 7: Commit**

```bash
git add docs_site/package-lock.json docs_site/package.json \
  .github/workflows/docs.yml .github/dependabot.yml AGENTS.md
git commit -m "chore(docs): audit docs-site dependencies and track them with Dependabot"
```

---

## Task 6: Scope contributor vs published documentation (action 20 / P24)

**Finding:** `docs/` and `docs/user/` overlap on eight topics with no ownership rule, and `docs/migration.md` still calls the 0.1.0 architecture batch “Unreleased”. `docs/` is load-bearing: `InvalidCoordinatesException` points at `docs/prayer.md`, and `UndefinedScheduleException` points at `docs/polar.md`. The correct fix is explicit scoping, not a merge.

**Files:**
- Create: `docs/README.md`
- Modify: `docs/migration.md`
- Modify: `docs/prayer.md`, `docs/qibla.md`, `docs/moon.md`, `docs/hijri.md`, `docs/sunnah.md`, `docs/polar.md`, `docs/integrations.md`
- Test: `tests/ArchDocsTest.php`
- Modify: `ARCHITECTURE.md`, `CONTEXT.md`, `AGENTS.md`, `CHANGELOG.md`

- [ ] **Step 1: Create the docs map**

Create `docs/README.md` with this content. The relative links are deliberate: the gate parses them and resolves them from `docs/`.

```markdown
# Documentation map

This repository has two documentation audiences. Keep each fact in the tree
that owns it, and link to the other tree instead of repeating it.

## Audiences

| Tree | Audience | Canonical for | Published? |
|---|---|---|---|
| `docs/` | contributors and maintainers | algorithm appendices, provenance, internal invariants, error-message targets | no |
| `docs/user/` | library users | installation, usage, API reference, migration, changelog, citations | yes (Astro Starlight) |
| `docs/plans/` | maintainers | historical implementation plans | no |
| `ARCHITECTURE.md`, `UQ_PROVENANCE.md` | contributors | architecture and table provenance | repo only |

## Page map

| Contributor page | Canonical for | User-facing counterpart |
|---|---|---|
| [prayer.md](prayer.md) | prayer algorithm appendix and method freeze | [user/prayer-times.md](user/prayer-times.md), [user/calculation-methods.md](user/calculation-methods.md), [user/high-latitude.md](user/high-latitude.md) |
| [qibla.md](qibla.md) | spherical and WGS84 geodesy internals | [user/qibla.md](user/qibla.md) |
| [moon.md](moon.md) | crescent geometry and criteria internals | [user/moon-sighting.md](user/moon-sighting.md) |
| [hijri.md](hijri.md) | calendar algorithms and table provenance | [user/hijri-converter.md](user/hijri-converter.md) |
| [sunnah.md](sunnah.md) | night-fraction internals | [user/sunnah-times.md](user/sunnah-times.md) |
| [polar.md](polar.md) | polar fallback internals | [user/polar-regions.md](user/polar-regions.md) |
| [migration.md](migration.md) | M1-M5 architecture migration | [user/migration.md](user/migration.md) |
| [integrations.md](integrations.md) | executable plain-`new` wiring | [user/integration.md](user/integration.md) |

## Editing rules

- New user-facing page: add it under `docs/user/`, wire it into
  `docs_site/astro.config.mjs`, and follow the release snapshot process.
- New contributor note: add it under `docs/` and add a row to this map.
- Never state the same fact in both trees. `docs/` links to `docs/user/` for
  usage; `docs/user/` does not link to internals.
- Error messages cite `docs/*.md`; `tests/ArchDocsTest` fails if a cited page
  disappears.
- `docs/plans/` is historical: plans record decisions at a point in time and
  are not updated after execution.
```

- [ ] **Step 2: Fix the stale migration page**

In `docs/migration.md`:

1. Change the title from `# migration (M1-M5)` to `# Architecture migration (M1-M5)`.
2. Replace the heading `## Unreleased — architecture deepening (breaking surface)` with `## 0.1.0 — architecture deepening (M1-M5)`. These changes shipped in 0.1.0; they are not unreleased.
3. Add this note immediately after the title:

```markdown
> This is the contributor-facing record of the M1-M5 architecture rounds.
> For the current user-facing upgrade guide, including the 0.2.0 `ClockView`
> removal and the `TWILIGHT_ANGLE` break, see
> [`docs/user/migration.md`](user/migration.md).
```

Leave the historical M5 and M4 sections below it; they are accurate records. Do not copy the 0.2.0 user migration into this file.

- [ ] **Step 3: Add “See also” links to every contributor page**

At the bottom of each page, add a single cross-link block (relative to `docs/`):

- `docs/prayer.md` → `user/prayer-times.md`, `user/calculation-methods.md`, `user/high-latitude.md`
- `docs/qibla.md` → `user/qibla.md`
- `docs/moon.md` → `user/moon-sighting.md`
- `docs/hijri.md` → `user/hijri-converter.md`
- `docs/sunnah.md` → `user/sunnah-times.md`
- `docs/polar.md` → `user/polar-regions.md`
- `docs/migration.md` → `user/migration.md`
- `docs/integrations.md` → `user/integration.md`

Use this shape (adjust the list per page):

```markdown
## See also

- [Prayer Times](user/prayer-times.md) — the published user guide
- [Calculation Methods](user/calculation-methods.md)
- [High Latitude Rules](user/high-latitude.md)
```

- [ ] **Step 4: Add the docs gates**

Append these methods to `tests/ArchDocsTest.php`. The class already extends `ArchSupport`, so `srcPhpFiles()` is available.

```php
    /**
     * Every `docs/*.md` path cited by the code exists.
     *
     * The error messages are why docs/ cannot be renamed freely:
     * InvalidCoordinatesException cites docs/prayer.md and
     * UndefinedScheduleException cites docs/polar.md.
     */
    public function testErrorMessagesCiteExistingDocs(): void
    {
        $references = [];

        foreach (self::srcPhpFiles() as $path) {
            preg_match_all('/docs\/[a-z0-9-]+\.md/', (string) file_get_contents($path), $matches);

            foreach ($matches[0] as $reference) {
                $references[$reference] = true;
            }
        }

        self::assertGreaterThanOrEqual(3, count($references), 'The docs-reference scan found too few citations.');

        foreach (array_keys($references) as $reference) {
            self::assertTrue(
                is_file(dirname(__DIR__) . '/' . $reference),
                sprintf('%s is cited by src/ but does not exist.', $reference),
            );
        }
    }

    /**
     * Every top-level contributor page is listed in the docs map.
     */
    public function testContributorDocsAreMapped(): void
    {
        $mapPath = dirname(__DIR__) . '/docs/README.md';
        self::assertFileExists($mapPath);
        $map = (string) file_get_contents($mapPath);
        $pages = glob(dirname(__DIR__) . '/docs/*.md') ?: [];
        $pages = array_values(array_filter(
            $pages,
            static fn (string $path): bool => basename($path) !== 'README.md',
        ));

        self::assertGreaterThanOrEqual(8, count($pages), 'The docs scan found too few contributor pages.');

        foreach ($pages as $path) {
            $file = basename($path);
            self::assertStringContainsString(
                '](' . $file . ')',
                $map,
                sprintf('docs/README.md does not map %s.', $file),
            );
        }
    }

    /**
     * Every relative Markdown link in the docs map resolves.
     */
    public function testDocsMapLinksResolve(): void
    {
        $mapPath = dirname(__DIR__) . '/docs/README.md';
        self::assertFileExists($mapPath);
        $map = (string) file_get_contents($mapPath);
        preg_match_all('/\]\(([^)#]+\.md)(?:#[^)]+)?\)/', $map, $matches);
        $targets = array_values(array_unique($matches[1]));

        self::assertGreaterThanOrEqual(8, count($targets), 'The docs map found too few links.');

        foreach ($targets as $target) {
            if (str_starts_with($target, 'http://') || str_starts_with($target, 'https://')) {
                continue;
            }

            self::assertTrue(
                is_file(dirname(__DIR__) . '/docs/' . $target),
                sprintf('docs/README.md link target %s does not exist.', $target),
            );
        }
    }
```

- [ ] **Step 5: Update the repository-level docs map references**

In `ARCHITECTURE.md`, replace the supporting-directory bullet for `docs/` with:

```markdown
- `docs/` — contributor-facing algorithm appendices and internals
  (`prayer.md`, `qibla.md`, `moon.md`, `hijri.md`, `sunnah.md`, `polar.md`,
  `migration.md`), `integrations.md` (executable wiring recipes), and
  `README.md` (the scope map). The published user guide lives in
  `docs/user/`; `docs/plans/` holds historical implementation plans.
```

Update the opening sentence from “for per-feature guides, see `docs/`” to:

```markdown
For installation and usage, see `README.md` and `docs/user/`; for contributor
algorithm appendices, see `docs/`; for wiring snippets, see
`docs/integrations.md`.
```

In `CONTEXT.md`, replace the docs bullet in `## Repo map` with:

```markdown
- Docs: `ARCHITECTURE.md` (layering), `docs/README.md` (contributor/user
  scope map), `docs/` contributor guides + `integrations.md` (plain-`new`
  wiring), `docs/user/` published user guide, `AGENTS.md`,
  `UQ_PROVENANCE.md` (M4).
```

In `AGENTS.md` → `## Additional Notes`, add:

```markdown
- `docs/` is contributor-facing (algorithm appendices, internals,
  error-message targets); `docs/user/` is the published user guide. Keep
  `docs/README.md` current; new top-level `docs/*.md` pages must be added to
  its map.
```

In `README.md` → `## Docs`, replace the `docs/` bullet with:

```markdown
- `docs/` — contributor-facing algorithm appendices and error-message targets;
  see [`docs/README.md`](docs/README.md) for the map to the published user guide
- `docs/user/` — the published user guide (versioned, built by `docs_site/`)
```

- [ ] **Step 6: Run the arch gates and build the docs site**

Run:

```bash
composer arch
cd docs_site && npm run build
```

Expected: all arch groups pass; the site builds with no broken internal links.

- [ ] **Step 7: Commit**

```bash
git add docs/README.md docs/migration.md docs/prayer.md docs/qibla.md \
  docs/moon.md docs/hijri.md docs/sunnah.md docs/polar.md docs/integrations.md \
  tests/ArchDocsTest.php ARCHITECTURE.md CONTEXT.md AGENTS.md README.md
git commit -m "docs: scope contributor and published documentation"
```

---

## Task 7: Sync the repository docs and run the full gate

**Purpose:** update the changelog for all Tier-3 work, regenerate the published changelog once, and run every gate against the final tree.

**Files:**
- Modify: `CHANGELOG.md`
- Regenerate: `docs/user/changelog.md`
- Modify: `README.md`, `AGENTS.md`, `ARCHITECTURE.md`, `CONTEXT.md` (only if a final wording fix is needed)

- [ ] **Step 1: Add the Tier-3 changelog bullets**

Edit `CHANGELOG.md` under the existing `## [Unreleased]` headings. Do not add a second `### Added` / `### Changed` / `### Fixed` heading; append to the existing lists.

Under `### Added`, add:

```markdown
- `docs/README.md` now defines the contributor-vs-published documentation
  scope and maps every `docs/*.md` page to its user-facing counterpart;
  `tests/ArchDocsTest` gates the map and the docs paths cited by error
  messages.
- `docs/user/changelog.md` is generated from `CHANGELOG.md` by
  `tools/sync_changelog.php`; `composer changelog:check` runs in CI and the
  release gate, so the published changelog can no longer drift.
- Direct unit tests cover `Minutes::add()`, `Angle::toRadians()`,
  `Coordinates::__toString()`, and `CivilDate::compareTo()`.
```

Under `### Changed`, add:

```markdown
- Docs-site dependencies are audit-gated (`npm audit --omit=dev
  --audit-level=moderate`) and tracked by Dependabot; `undici` and
  `postcss-selector-parser` were refreshed to clear the current advisories.
- `docs/migration.md` is scoped as the M1-M5 architecture record; the live
  0.2.0 upgrade guide remains `docs/user/migration.md`.
- `PrayerTimesCalculator::calculate()` documents the Asr clamp order: the
  Maghrib clamp is the final authority, so `asr <= maghrib` always holds and
  `dhuhr <= asr` holds whenever `dhuhr <= maghrib` (the empty-adjustment
  ordering sweeps assert that for the supported grid). High-latitude caller
  offsets can invert the anchors, in which case the Maghrib clamp wins by
  design.
- The `composer ci` gate now includes `changelog:check`; `AGENTS.md` records
  the docs scope and the npm audit gate.
```

Under `### Fixed`, add:

```markdown
- Inline source FQCNs and deleted-code blank-line runs were removed; new arch
  gates reject both patterns so they cannot regrow.
```

- [ ] **Step 2: Regenerate the published changelog**

Run:

```bash
composer changelog
composer changelog:check
```

Expected: `docs/user/changelog.md` now contains the Tier-3 bullets under `[Unreleased]`; the check reports “in sync”.

- [ ] **Step 3: Run the complete gate**

Run:

```bash
composer validate --strict
composer ci
composer arch
```

Expected:

- `composer validate --strict` passes.
- `composer ci`: lint passes, CS-fixer reports 0 findings, PHPStan reports 0 errors, `changelog:check` is in sync, tests pass with coverage at least 95% (expect about 97%).
- `composer arch`: all arch groups pass (25 baseline + the 6 new shape/docs gates).

- [ ] **Step 4: Verify the docs site and the npm gate**

Run:

```bash
cd docs_site
npm ci
npm run audit
npm run build
```

Expected: audit exits 0; build succeeds with 0 broken internal links. The generated changelog page renders under `/changelog/`.

- [ ] **Step 5: Verify the frozen snapshots and the working tree**

Run:

```bash
git diff --stat main -- docs/user/v0.1.0 docs/user/v0.1.1
git status --short
grep -rn 'AlFalaq\\AlFalaq\\' --include='*.php' src | grep -vE ':(use |namespace )' || true
for f in $(find src -name '*.php'); do
  awk '/^[[:space:]]*$/{c++;next}{if(c>=2)print FILENAME": "c" blanks before line "FNR;c=0} END{if(c>=2)print FILENAME": "c" trailing blanks from line "FNR-c+1}' "$f"
done
```

Expected:

- The snapshot diff is empty.
- `git status` shows only the intended Tier-3 files.
- The inline-FQCN grep prints nothing: all 11 lines now use imported short names, and the arch gate is authoritative.
- The blank-line scan prints nothing (it also covers trailing runs at end-of-file).

- [ ] **Step 6: Commit**

```bash
git add CHANGELOG.md docs/user/changelog.md README.md AGENTS.md \
  ARCHITECTURE.md CONTEXT.md
git commit -m "docs: sync the tier-3 changes"
```

---

## Final Verification

- [ ] `composer validate --strict` passes.
- [ ] `composer lint` passes.
- [ ] `composer cs` reports 0 of 158+ files.
- [ ] `composer stan` reports no errors.
- [ ] `composer test-unit` passes; test count is at least 483 (471 baseline + 6 value-object tests + 6 arch tests; exact count may differ if a test is split).
- [ ] `composer test` passes with coverage at least 95%.
- [ ] `composer arch` passes (25 baseline + 6 new gates).
- [ ] `composer changelog:check` passes.
- [ ] `cd docs_site && npm ci && npm run audit` exits 0.
- [ ] `cd docs_site && npm run build` succeeds with 0 broken internal links.
- [ ] `git diff --stat main -- docs/user/v0.1.0 docs/user/v0.1.1` is empty.
- [ ] No `src/` inline FQCN or consecutive blank-line runs remain.
- [ ] `docs/README.md` maps all eight top-level contributor pages and every relative link resolves.
- [ ] `composer bench` still passes the enforced 50 ms / 30,000 ms tripwires (no performance code changed; run it once for completeness).

---

## Commit sequence

1. `refactor: import inline FQCNs and collapse deleted-code gaps` — Task 1.
2. `test(shared-kernel): cover the remaining value-object primitives` — Task 2.
3. `docs(prayer): explain the Asr clamp order` — Task 3.
4. `docs(changelog): generate the published changelog from CHANGELOG.md` — Task 4.
5. `chore(docs): audit docs-site dependencies and track them with Dependabot` — Task 5.
6. `docs: scope contributor and published documentation` — Task 6.
7. `docs: sync the tier-3 changes` — Task 7.

The commits are individually revertable. Task 2 is independent of both, but Tasks 1 and 3 both edit `src/Prayer/Services/PrayerTimesCalculator.php` (different regions: imports/`default()` vs the `calculate()` clamp), so keep them in that order rather than running them in parallel. Task 4's generator is independent of Tasks 5–6. Task 5 only touches the docs-site toolchain. Task 6 only touches documentation and arch tests. Task 7 is the final sync and must come last.

---

## Appendix A — Re-evaluation commands

Run from the repository root:

```bash
# Baseline
composer test-unit                 # 471 tests / 50,103 assertions at acf29ab
composer arch                      # 25 tests
composer cs                        # 0 of 158 files
composer stan                      # 0 errors / 142 files

# P14 blank-line runs
for f in $(find src -name '*.php'); do
  awk '/^[[:space:]]*$/{c++;next}{if(c>=2)print FILENAME": "c" blanks before line "FNR;c=0} END{if(c>=2)print FILENAME": "c" trailing blanks from line "FNR-c+1}' "$f"
done

# P15 inline FQCNs (namespace/use excluded)
grep -rn 'AlFalaq\\AlFalaq\\' --include='*.php' src | grep -vE ':(use |namespace )'

# P20 changelog drift
wc -l CHANGELOG.md docs/user/changelog.md
grep -n 'Unreleased\|0.1.1\|0.1.0' CHANGELOG.md docs/user/changelog.md

# P22 npm advisories
cd docs_site && npm audit --omit=dev --audit-level=moderate

# P24 docs overlap
ls docs/*.md
ls docs/user/*.md
grep -rn 'docs/[a-z-]*\.md' src tests --include='*.php'
```

## Appendix B — Current finding anchors (as of `acf29ab`)

| Finding | Anchor |
|---|---|
| P14 `CrescentEphemeris` gaps | after `greenwichSiderealDegrees()`, after `bodyAltitude()`, end of `finiteGeo()` |
| P14 `MeanSolarCalculator` gaps | after `isSolarDefined()`, after `closestAngle()`, after `solarAltitude()`, after `transitHours()` |
| P14 `CrescentCalculator` gaps | after `evaluateGeometry()`, end of class |
| P15 `PrayerTimesCalculator` | `default()` and `defaultFor()` `new \AlFalaq\...\MeanSolarCalculator()` |
| P15 `SunnahCalculator` | two `\AlFalaq\...\ValidationException` throws |
| P15 `UndefinedScheduleException` | docblock `@param` and `polar()` signature |
| P20 | `docs/user/changelog.md:12-14` says “No changes yet”; `CHANGELOG.md:8-80` has the 0.2.0 entries |
| P22 | `docs_site/package-lock.json` `undici` 8.11.2, `postcss-selector-parser` 6.1.4 |
| P24 | `docs/migration.md:3` says “Unreleased — architecture deepening”; `docs/user/migration.md:10` says “Unreleased — 0.2.0” and `:32` says “0.1.0” |
| Asr clamp | `PrayerTimesCalculator::calculate()` lines 284-296 |
