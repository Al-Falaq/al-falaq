# Tier 1 Hygiene Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Land the six Tier-1 findings from `REPO_HYGIENE_REPORT.md` as one pull request: remove the redundant solar-theory recomputation, make the benchmark stop printing a budget it does not meet, let `composer mutation` run locally, give the Kaaba coordinates a single owner, complete the public-API reference (and gate it), and widen the four T2-method prayer goldens from 2 dates to the 6-date grid.

**Architecture:** Six independent, individually-revertable changes that share one theme — *make the repository's claims true*. Two are code changes with observable behaviour (the solar triplet, the Kaaba constant), two are truth-restoring changes to tooling and configuration (the benchmark gate, the Composer timeout), and two are documentation-plus-gate changes (the API reference, the golden grid). Every behavioural change is protected by the existing 5,133-row golden suite; every new claim is protected by a new machine check so it cannot silently rot again.

**Tech Stack:** PHP 8.2+ (no runtime dependencies), PHPUnit 11, PHPStan max + strict-rules, php-cs-fixer `@PER`, Infection, Astro Starlight docs site, plain-Python tooling under `tools/`.

**Plan location note:** the `writing-plans` skill defaults to `docs/superpowers/plans/`, but this repository gitignores `/docs/superpowers/` ("Development-only brainstorming artifacts — never merged"). The plan must be committed with the work, so it lives at `docs/plans/2026-10-22-tier1-hygiene.md`.

**Branch:** `fix/tier1-hygiene-2026-10-22` (split from `main` at `ebcc3aa`).

---

## File Structure

| File | Responsibility | Task |
|---|---|---|
| `src/Astro/Services/MeanSolarCalculator.php` | Owns the declination formula; the solar triplet must reuse the values it already holds | 1 |
| `tests/Astro/MeanSolarCalculatorTest.php` | Structural guard: the triplet must not recompute declination from the Julian Day | 1 |
| `examples/bench.php` | Performance tripwire: prints the enforced gate, labels the PRD reference honestly | 2 |
| `composer.json` | `config.process-timeout: 0` so `composer mutation` matches CI locally | 3 |
| `CONTRIBUTING.md`, `AGENTS.md` | Document the mutation-gate runtime | 3 |
| `src/SharedKernel/ValueObjects/Kaaba.php` | **Create.** Single owner of the Kaaba coordinates (cross-context domain fact) | 4 |
| `src/Qibla/Contracts/GeodesyCalculator.php` | Public constants now reference `Kaaba` instead of holding literals | 4 |
| `src/Prayer/Strategies/MakkahHandler.php` | Uses `Kaaba::coordinates()` instead of inline literals | 4 |
| `tests/SharedKernel/KaabaTest.php` | **Create.** Pins the shared constants and the drift guard | 4 |
| `docs/user/api-reference.md` | Complete the public-API inventory | 5 |
| `AGENTS.md`, `CONTEXT.md` | Arch-group lists (the reference gate is a new arch group) | 5 |
| `tests/ArchApiReferenceTest.php` | **Create.** Gate: every public class must appear in the API reference | 5 |
| `tests/Goldens/prayer_muis.csv` | 4 new official rows from the MUIS 2025 timetable | 6 |
| `tests/Goldens/prayer_turkey.csv`, `prayer_kemenag.csv`, `prayer_brunei.csv` | 4 new drift-gate rows each | 6 |
| `tests/Goldens/PrayerGoldensTest.php` | Allow-list entries + fully-empty-row expectation update | 6 |
| `tools/gen_t2_drift_goldens.php` | **Create.** Merge-mode generator for the T2 drift rows | 6 |
| `docs/prayer.md`, `docs/user/citations.md`, `CHANGELOG.md`, `ARCHITECTURE.md`, `README.md` | Documentation sync | 7 |

**Frozen artefacts that must NOT be touched:** `docs/user/v0.1.0/**` and `docs/user/v0.1.1/**` are released snapshots. Only `docs/user/*.md` (current) changes.

---

## Task 1: Remove the redundant declination recomputation

**Finding:** report P2. `MeanSolarCalculator::solarTriplet()` computes `$century`, `$lambda` and `$epsilonApp`, then calls `$this->declination($julianDay)`, which recomputes all three from the Julian Day before doing its one `asin`. Measured cost: `declination()` is 0.0113 ms against 0.057 ms for a full triplet — **19.8% of every triplet is pure recomputation**. A single `calculate()` runs roughly 22–28 triplets.

**Files:**
- Modify: `src/Astro/Services/MeanSolarCalculator.php:40-47` (`declination()`), `:434-455` (`solarTriplet()`)
- Test: `tests/Astro/MeanSolarCalculatorTest.php`

### Step 1: Write the failing test

The behaviour is unchanged by this refactor, so the failing test must assert the *structural* invariant that is currently violated. A wall-clock assertion was rejected: it is machine-dependent and would be flaky in CI. A source-level assertion matches the idiom already used by `tests/ArchShapeTest.php` and `tests/ArchLayeringTest.php`, which scan source text.

Append to `tests/Astro/MeanSolarCalculatorTest.php`:

```php
    /**
     * The solar triplet must not recompute the declination from the Julian Day.
     *
     * declination() derives the century, apparent longitude, and apparent
     * obliquity from the JD; solarTriplet() already holds the last two, so
     * calling declination() there recomputes all three for every triplet
     * (~20% of the engine's trig cost, measured). declinationFrom() is the
     * single owner of the formula: declination() derives its inputs from the
     * JD, the triplet reuses the values it already has.
     */
    public function testSolarTripletDoesNotRecomputeDeclinationFromTheJulianDay(): void
    {
        $path = __DIR__ . '/../../src/Astro/Services/MeanSolarCalculator.php';
        $source = (string) file_get_contents($path);

        self::assertStringContainsString(
            'private function declinationFrom(',
            $source,
            'MeanSolarCalculator must declare declinationFrom() as the single owner of the declination formula.',
        );

        self::assertStringNotContainsString(
            "'dec' => \$this->declination(",
            $source,
            'solarTriplet() must reuse the apparent longitude and obliquity it already computed, not call declination().',
        );

        self::assertSame(
            1,
            substr_count($source, 'rad2deg(asin(sin(deg2rad($epsilonApp)) * sin(deg2rad($lambda))))'),
            'The declination formula must exist exactly once in the file; a second copy would drift.',
        );
    }
```

### Step 2: Run the test to verify it fails

Run: `vendor/bin/phpunit --no-coverage --filter testSolarTripletDoesNotRecomputeDeclinationFromTheJulianDay`

Expected: **FAIL** — `MeanSolarCalculator must declare declinationFrom() as the single owner of the declination formula.`

### Step 3: Implement the minimal change

In `src/Astro/Services/MeanSolarCalculator.php`, replace the body of `declination()`:

```php
    public function declination(float $julianDay): float
    {
        $century = $this->julianCentury($julianDay);

        return $this->declinationFrom(
            $this->apparentLongitude($century),
            $this->apparentObliquity($century),
        );
    }
```

Add the private helper directly after `declination()`:

```php
    /**
     * Declination from apparent solar longitude and apparent obliquity.
     *
     * Single owner of the declination formula: declination() derives its
     * inputs from the Julian Day, while solarTriplet() already holds both
     * and would otherwise recompute them for every triplet.
     *
     * @param float $lambda Apparent solar longitude in degrees.
     * @param float $epsilonApp Apparent obliquity of the ecliptic in degrees.
     * @return float Declination in degrees.
     */
    private function declinationFrom(float $lambda, float $epsilonApp): float
    {
        return rad2deg(asin(sin(deg2rad($epsilonApp)) * sin(deg2rad($lambda))));
    }
```

In `solarTriplet()`, replace the returned array:

```php
        return ['ra' => $rightAscension, 'dec' => $this->declinationFrom($lambda, $epsilonApp), 'st' => $siderealTime];
```

**The `solarTriplet()` docblock must change too.** It currently reads *"Declination reuses the public method for a single source of truth."* — after this refactor that is stale: the triplet no longer calls the public method, and the single source of truth is the formula, not the method. Replace it with:

```
     * Declination reuses the apparent longitude and obliquity computed
     * above, via the shared declinationFrom() formula owner.
```

### Step 4: Run the test to verify it passes

Run: `vendor/bin/phpunit --no-coverage --filter testSolarTripletDoesNotRecomputeDeclinationFromTheJulianDay`
Expected: **PASS**

### Step 5: Verify no behaviour changed

Run: `vendor/bin/phpunit --no-coverage`
Expected: **OK (437 tests, 49727 assertions)** — one more test than the 436 baseline, three more assertions (the new test makes three), and **all 5,133 golden rows still green**. The goldens are the behavioural proof: `declinationFrom()` receives byte-identical inputs to the old `declination()` computed internally, because both derive `$lambda` and `$epsilonApp` from the same `$century` via the same pure functions.

Run: `composer stan` → Expected: **No errors**
Run: `composer cs` → Expected: **Found 0 of 156 files that can be fixed** (the baseline; this task edits two existing files and adds none)

### Step 6: Measure the win

Run: `composer bench`
Expected: the single-day median drops from the 2.373 ms baseline by roughly 0.25–0.32 ms (≈0.25 ms × 22–28 triplets). Record the measured number in the Task 2 commit message — Task 2 needs it.

### Step 7: Commit

```bash
git add src/Astro/Services/MeanSolarCalculator.php tests/Astro/MeanSolarCalculatorTest.php
git commit -m "perf(astro): stop recomputing the declination per solar triplet

solarTriplet() already holds the apparent longitude and obliquity but
called declination(), which recomputed the century, longitude and
obliquity from the Julian Day before doing its one asin. Measured at
19.8% of a triplet's trig cost, ~25 triplets per prayer schedule.

declinationFrom() is now the single owner of the formula: declination()
derives its inputs from the JD, the triplet reuses what it has. Output
is bit-identical; all 5,133 golden rows stay green."
```

---

## Task 2: Make the benchmark stop printing a budget it does not meet

**Finding:** report P1. `examples/bench.php:46` printed `M5 budget p50 < 2ms` while measuring 2.373 ms. The enforced gate was 50 ms — 25× looser than the printed number — so the overshoot was invisible. The repository's documented intent (CHANGELOG, "Harness truth fixes") is that the tripwires are loose and the PRD numbers are headroom references; the defect is that a *printed budget* was routinely exceeded.

**Files:**
- Modify: `examples/bench.php:13-14`, `:45-55`
- Test: `tests/Astro/MeanSolarCalculatorTest.php` is **not** the right home; the bench is the harness. Verification is `composer bench` itself plus `composer lint`/`composer cs` (the bench file is covered by both).

### Step 1: Record the failing observation

Run: `composer bench`
Expected (before this task, after Task 1): a line that still reads `M5 budget p50 < 2ms` while the enforced gate in the code is 50 ms. The observation *is* the failure: the printed contract and the enforced contract disagree.

### Step 2: Implement the change

In `examples/bench.php`, replace the header comment:

```php
// Performance smoke harness (CI enforces only the loose 50ms/30000ms tripwires; the printed PRD budgets are headroom references, gated later).
// Usage: php examples/bench.php [--budget]
```

with:

```php
// Performance tripwire harness.
//
// The ENFORCED gates are the contract: a breach fails the build (--budget).
// The PRD figures are hardware- AND load-dependent headroom references and
// are printed on their own line, explicitly NOT gated. Measured on one
// machine, the same script reports a single-day median of 2.4ms, 6-7.6ms
// and 39.5ms under different load — a 16x spread — so an absolute gate
// anywhere near the PRD 2ms figure would be flaky rather than meaningful.
// The tripwires stay loose on purpose: the single-day one sits ~7-20x above
// the measured median on a quiet machine but only ~1.3x above it under heavy
// load, so they catch a gross regression rather than measuring speed.
//
// Usage: php examples/bench.php [--budget]
```

Replace the `printf()` and the gate:

```php
printf(
    "bench: single-day median %.3f ms (tripwire %.0f ms); year %.0f ms (tripwire %.0f ms); peak %.1f MB (not gated)\n",
    $singleMs,
    $singleTripwireMs,
    $yearMs,
    $yearTripwireMs,
    $peakMb,
);
printf(
    "bench: PRD reference (NOT gated, hardware- and load-dependent): single-day p50 < 2ms; year < 1000ms; peak < 16MB\n",
);

if ($budget && ($singleMs > $singleTripwireMs || $yearMs > $yearTripwireMs)) {
    fwrite(STDERR, "bench budget exceeded\n");
    exit(1);
}
```

And define the tripwires near the top of the script, after `$budget = ...`:

```php
$singleTripwireMs = 50.0;   // enforced tripwire (unchanged)
$yearTripwireMs = 30000.0;  // enforced tripwire (unchanged)
```

**Why the enforced tripwires stay at 50 ms / 30,000 ms.** A review of this task measured the same script on the same machine under different load: **2.373 ms, 5.98–7.59 ms, and 39.5 ms** for the single-day median, and 862 ms → 2,841–3,788 ms for the year. That is a **16× spread from load alone**. An absolute gate anywhere near the PRD 2 ms figure would be flaky — it would fail a busy CI runner and a busy laptop, not a genuine regression.

The gate is therefore left where it is, and the **defect being fixed is the false claim**, not the gate value: the harness printed "M5 budget p50 < 2ms" as a budget while enforcing 50 ms, and the PRD figure is not met on ordinary hardware at all.

**Honest statement of the margin.** The worst observed single-day median (39.5 ms) sits only **1.27×** below the 50 ms gate; against the typical 2.4–7.6 ms readings the margin is 7×–20×. The gate is loose against a quiet machine and tight against a heavily loaded one, which is the correct trade for a tripwire whose job is to catch an order-of-magnitude regression, not to measure performance. A reader who sees "16× spread" should know the gate is 1.27× above the worst reading — that is why the harness prints the tripwire it enforces and nothing else.

This is also why the PRD figures are printed on a separate line marked "NOT gated", and why memory is printed as "(not gated)" — the gate checks the single-day median and the year only, so calling 16 MB a "tripwire" would reintroduce exactly the printed-versus-enforced disagreement this task removes.

### Step 3: Verify

Run: `composer bench`
Expected: two lines — `bench: single-day median <N> ms (tripwire 50 ms); year <N> ms (tripwire 30000 ms); peak <N> MB (not gated)` and the `bench: PRD reference (NOT gated, hardware- and load-dependent): …` line. Exit code 0.

Run: `composer lint && composer cs` → Expected: pass, **0 files** need fixing.

### Step 4: Commit

```bash
git add examples/bench.php
git commit -m "fix(bench): stop printing a budget the harness does not enforce

The bench printed 'M5 budget p50 < 2ms' while enforcing a 50ms gate,
and the PRD figure is not met on ordinary hardware at all: the same
script on the same machine measured 2.4ms, 6-7.6ms and 39.5ms for
the single-day median under different load, a 16x spread.

The printed contract and the enforced contract now agree. The tripwires
(50ms / 30,000ms, unchanged) are printed as the contract, memory is
printed as not gated because the gate never checked it, and the PRD
figures are printed on their own line marked NOT gated and
hardware- and load-dependent."
```

---

## Task 3: Let `composer mutation` run locally

**Finding:** report P3. `composer mutation` dies locally with *"The process 'infection --threads=4' exceeded the timeout of 300 seconds"*. Composer's default `process-timeout` is a hard 300 s wall clock — confirmed in `Composer\Config.php` (`'process-timeout' => 300`) with no output-based reset in Composer 2.9.7 or Symfony `Process`. CI survives only because `shivammathur/setup-php` exports `COMPOSER_PROCESS_TIMEOUT: 0`, which is visible in the CI log. A contributor following the documented workflow gets a spurious failure.

**Files:**
- Modify: `composer.json` (`config` block)
- Modify: `CONTRIBUTING.md`, `AGENTS.md`

### Step 1: Reproduce the failure

Run: `composer mutation`
Expected: fails after 300 seconds with `The process "infection --threads=4" exceeded the timeout of 300 seconds.`

### Step 2: Implement the change

In `composer.json`, add `process-timeout` to the `config` block:

```json
    "config": {
        "sort-packages": true,
        "process-timeout": 0,
        "platform": {
            "php": "8.2.0"
        },
        "allow-plugins": {
            "infection/extension-installer": true
        }
    },
```

`0` disables the script timeout, which is exactly what CI does through `COMPOSER_PROCESS_TIMEOUT: 0` — so local and CI behaviour now match. The scripts that need this are `mutation` (a deliberate multi-minute gate) and `test` (the coverage gate, which runs the whole suite under pcov and has been observed to exceed 300 s on a slow machine); `lint`, `cs` and `stan` finish in seconds.

In `CONTRIBUTING.md`, append:

```markdown
## Mutation gate

`composer mutation` runs the full Infection suite (2,377 mutants, ~7 minutes
on a developer laptop). The repository sets `config.process-timeout: 0` in
`composer.json` so the script is not killed by Composer's default 300-second
process timeout; CI sets the same value through `COMPOSER_PROCESS_TIMEOUT`.
Run it before opening a pull request that changes `src/` behaviour.
```

In `AGENTS.md`, append to the "Development Workflow" section:

```markdown
- `composer mutation` — Infection gate (MSI ≥ 85, ~7 min). `composer.json`
  sets `config.process-timeout: 0` so the script survives Composer's default
  300 s process timeout; CI sets the same via `COMPOSER_PROCESS_TIMEOUT`.
```

### Step 3: Verify

Run: `composer validate --strict` → Expected: `./composer.json is valid`
Run: `COMPOSER_PROCESS_TIMEOUT=0 php vendor/bin/infection --threads=4 --no-progress --min-msi=0 --min-covered-msi=0`
Expected: completes with `Mutation Score Indicator (MSI): 91%` and `Covered Code MSI: 93%` (baseline on this machine; CI reported 90% / 91%). Both are above the 85% floor configured in `infection.json.dist`.

Run: `composer mutation` → Expected: completes instead of timing out.

### Step 4: Commit

```bash
git add composer.json CONTRIBUTING.md AGENTS.md
git commit -m "fix(tooling): let composer mutation run locally

Composer's default 300s process timeout killed the Infection run at
~1,500 of 2,377 mutants. CI only survives because setup-php exports
COMPOSER_PROCESS_TIMEOUT=0, so local and CI behaviour differed and a
contributor got a spurious failure. composer.json now sets
config.process-timeout: 0, matching CI, and both docs record the
multi-minute runtime."
```

---

## Task 4: Give the Kaaba coordinates a single owner

**Finding:** report P4. The Kaaba coordinates exist in two places: `Qibla\Contracts\GeodesyCalculator::MAKKAH_LATITUDE / MAKKAH_LONGITUDE` (public API, documented) and as inline literals in `Prayer\Strategies\MakkahHandler::resolve()`. No test asserts they match, so they can drift silently. `Prayer` must not depend on `Qibla` (sibling bounded contexts), so the shared fact is lifted into `SharedKernel`.

**Files:**
- Create: `src/SharedKernel/ValueObjects/Kaaba.php`
- Modify: `src/Qibla/Contracts/GeodesyCalculator.php:18-27`
- Modify: `src/Prayer/Strategies/MakkahHandler.php:28-35`
- Test: `tests/SharedKernel/KaabaTest.php`

### Step 1: Write the failing test

Create `tests/SharedKernel/KaabaTest.php`:

```php
<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\SharedKernel;

use AlFalaq\AlFalaq\Prayer\Strategies\MakkahHandler;
use AlFalaq\AlFalaq\Qibla\Contracts\GeodesyCalculator;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Kaaba;
use PHPUnit\Framework\TestCase;

/**
 * The Kaaba coordinates are a cross-context domain fact: Qibla aims at
 * them, the Prayer polar fallback substitutes them. One owner, no drift.
 */
final class KaabaTest extends TestCase
{
    public function testCoordinatesAreThePinnedKaabaPosition(): void
    {
        self::assertSame(21.4225241, Kaaba::LATITUDE);
        self::assertSame(39.8261818, Kaaba::LONGITUDE);
    }

    public function testCoordinatesFactoryMatchesTheConstants(): void
    {
        $coordinates = Kaaba::coordinates();

        self::assertSame(Kaaba::LATITUDE, $coordinates->latitude);
        self::assertSame(Kaaba::LONGITUDE, $coordinates->longitude);
    }

    /**
     * The public Qibla constants keep their pinned values.
     *
     * GeodesyCalculator::MAKKAH_LATITUDE is documented public API; the
     * shared owner must not move it.
     */
    public function testQiblaConstantsStillHoldThePinnedValues(): void
    {
        self::assertSame(21.4225241, GeodesyCalculator::MAKKAH_LATITUDE);
        self::assertSame(39.8261818, GeodesyCalculator::MAKKAH_LONGITUDE);
    }

    /**
     * Drift guard: the polar fallback must resolve to the shared owner.
     */
    public function testMakkahHandlerResolvesToTheSharedKaabaCoordinates(): void
    {
        $resolved = (new MakkahHandler())->resolve(
            Coordinates::fromDegrees(60.0, 10.0),
            CivilDate::fromYmd(2025, 6, 21),
            static fn(): bool => true,
        );

        self::assertTrue($resolved['coordinates']->equals(Kaaba::coordinates()));
    }
}
```

### Step 2: Run the test to verify it fails

Run: `vendor/bin/phpunit --no-coverage tests/SharedKernel/KaabaTest.php`
Expected: **ERROR** — `Class "AlFalaq\AlFalaq\SharedKernel\ValueObjects\Kaaba" not found`.

### Step 3: Create the shared owner

Create `src/SharedKernel/ValueObjects/Kaaba.php`:

```php
<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\SharedKernel\ValueObjects;

/**
 * The Kaaba position: the single owner of the cross-context coordinate fact.
 *
 * Two bounded contexts need it — Qibla aims at it, and the Prayer polar
 * fallback substitutes it for an undefined location. Owning it in
 * SharedKernel keeps the two contexts siblings (no Prayer → Qibla edge)
 * and makes drift impossible: the Qibla contract constants reference these
 * values, so the literals exist in exactly one place.
 */
final class Kaaba
{
    /**
     * Kaaba latitude in degrees (pin Constants.py:8; goldens pin these digits).
     */
    public const LATITUDE = 21.4225241;

    /**
     * Kaaba longitude in degrees (pin Constants.py:8; goldens pin these digits).
     */
    public const LONGITUDE = 39.8261818;

    private function __construct() {}

    /**
     * The Kaaba position as a validated coordinates value object.
     *
     * @return Coordinates The Kaaba coordinates (WGS84 degrees).
     */
    public static function coordinates(): Coordinates
    {
        return Coordinates::fromDegrees(self::LATITUDE, self::LONGITUDE);
    }
}
```

**Note on the class shape.** `Kaaba` is a `final class` with a private constructor and static members, exactly like `SharedKernel\ValueObjects\Minutes` — the existing precedent for a stateless static-only holder in that namespace. It is deliberately *not* `readonly`: it holds no instance state. The `@return` tag must name `Coordinates`, not `self`, because the native return type is `Coordinates` and PHPStan max rejects a mismatched `@return`.

**Note on the class location.** `SharedKernel\ValueObjects\` is the right home: the value is a coordinate pair, and `SharedKernel` is the only layer both `Prayer` and `Qibla` may depend on. Putting it in `Qibla\Contracts\` would force a `Prayer → Qibla` sibling edge that the architecture diagram does not have.

### Step 4: Point the Qibla contract at the shared owner

In `src/Qibla/Contracts/GeodesyCalculator.php`, replace the three constant blocks with:

```php
    /**
     * Kaaba latitude in degrees (pin Constants.py:8; goldens pin these digits).
     */
    public const MAKKAH_LATITUDE = Kaaba::LATITUDE;

    /**
     * Kaaba longitude in degrees (pin Constants.py:8; goldens pin these digits).
     */
    public const MAKKAH_LONGITUDE = Kaaba::LONGITUDE;

    /**
     * Makkah-self distance in km below which direction clamps to 0.0 (§5.3).
     */
    public const SELF_DISTANCE_KM = 0.001;
```

and add the import:

```php
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Kaaba;
```

`SELF_DISTANCE_KM` deliberately stays on the Qibla contract: it is a Qibla clamping policy, not a fact about the Kaaba.

### Step 5: Point the polar handler at the shared owner

In `src/Prayer/Strategies/MakkahHandler.php`, replace the `resolve()` body:

```php
    public function resolve(Coordinates $coordinates, CivilDate $date, callable $isSolvable): array
    {
        // Pin Constants.MAKKAH full precision (21.4225241, 39.8261818); 4dp display would be ~11 m off — kept verbatim via the shared Kaaba owner.
        return [
            'coordinates' => Kaaba::coordinates(),
            'date' => $date,
        ];
    }
```

and add the import:

```php
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Kaaba;
```

### Step 6: Run the test to verify it passes

Run: `vendor/bin/phpunit --no-coverage tests/SharedKernel/KaabaTest.php`
Expected: **OK (4 tests)**

### Step 7: Verify the whole suite and the gates

Run: `vendor/bin/phpunit --no-coverage` → Expected: **OK (441 tests)**
Run: `composer stan` → Expected: **No errors** — note that `tests/ArchSupport.php`, `ArchShapeTest.php`, `ArchDocsTest.php` and `ArchLayeringTest.php` are listed in `phpstan.neon`'s `excludePaths`, but `tests/SharedKernel/KaabaTest.php` is **not**, so it is analysed at level max.
Run: `composer cs` → Expected: **Found 0 of 158 files** need fixing (the baseline is 156; this task adds `src/SharedKernel/ValueObjects/Kaaba.php` and `tests/SharedKernel/KaabaTest.php`).
Run: `composer arch` → Expected: pass — `SharedKernel\ValueObjects\Kaaba` is a domain value object, so the layering rules are satisfied, and `Qibla\Contracts\GeodesyCalculator` importing `SharedKernel` is a domain → domain edge.

### Step 8: Commit

```bash
git add src/SharedKernel/ValueObjects/Kaaba.php src/Qibla/Contracts/GeodesyCalculator.php src/Prayer/Strategies/MakkahHandler.php tests/SharedKernel/KaabaTest.php
git commit -m "fix(prayer): give the Kaaba coordinates a single owner

The Kaaba position lived in two places: the public Qibla contract
constants and inline literals in the Prayer polar fallback, with no
test asserting they matched. Kaaba in SharedKernel now owns the fact;
the Qibla constants reference it, so Prayer keeps no sibling edge to
Qibla and the literals exist exactly once. A test pins the constants
and the drift guard."
```

---

## Task 5: Complete the public-API reference and gate it

**Finding:** report P6. `docs/user/api-reference.md` claims to map "the entire public surface" but omits ~26 public classes — including `Prayer\Services\NightResolver` (a service in the *public* namespace), `Moon\Services\EphemerisConjunctionSearch`, `SharedKernel\ValueObjects\JulianDay`, `Prayer\Enums\Prayer`, `Prayer\Contracts\TwilightModel`, `Prayer\ValueObjects\NightPortions`, and every strategy implementation. Without a gate the page drifts again on the next added class.

**Files:**
- Modify: `docs/user/api-reference.md`
- Create: `tests/ArchApiReferenceTest.php`
- Modify: `AGENTS.md`, `ARCHITECTURE.md`, `CONTEXT.md` (arch-group lists)

### Step 1: Write the failing test

Create `tests/ArchApiReferenceTest.php`:

```php
<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Public-API reference gate: the documented inventory must cover every
 * public class-like.
 *
 * The reference page is the map of the public surface; when a class is
 * added and the page is not updated, the page silently stops being a map.
 * This test makes the omission fail the build.
 *
 * Internal namespaces are exempt: Astro/ is the @internal engine and
 * Hijri/Data/ holds generated tables, neither of which is public API.
 */
#[Group('arch')]
final class ArchApiReferenceTest extends TestCase
{
    /**
     * Every public class-like appears in the API reference.
     */
    public function testEveryPublicClassIsDocumented(): void
    {
        $reference = (string) file_get_contents(dirname(__DIR__) . '/docs/user/api-reference.md');
        $prefix = 'AlFalaq\\AlFalaq\\';
        $missing = [];

        foreach (self::publicClasses() as $class) {
            $relative = substr($class, strlen($prefix));

            if (! str_contains($reference, $relative)) {
                $missing[] = $relative;
            }
        }

        self::assertSame([], $missing, sprintf(
            'Public classes missing from docs/user/api-reference.md: %s',
            implode(', ', $missing),
        ));
    }

    /**
     * Public class-likes in src/, excluding the internal namespaces.
     *
     * @return list<class-string> Fully-qualified class names.
     */
    private static function publicClasses(): array
    {
        $root = dirname(__DIR__);
        $prefix = 'AlFalaq\\AlFalaq\\';
        $classes = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root . '/src', \FilesystemIterator::SKIP_DOTS),
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relative = substr($file->getPathname(), strlen($root . '/src/'), -4);

            if (str_starts_with($relative, 'Astro/') || str_starts_with($relative, 'Hijri/Data/')) {
                continue;
            }

            $fqcn = $prefix . str_replace('/', '\\', $relative);

            if (class_exists($fqcn) || interface_exists($fqcn) || trait_exists($fqcn) || enum_exists($fqcn)) {
                $classes[] = $fqcn;
            }
        }

        sort($classes);

        return $classes;
    }
}
```

**Why `list<class-string>` and no reflection.** `phpstan.neon` excludes `ArchSupport.php`, `ArchShapeTest.php`, `ArchDocsTest.php` and `ArchLayeringTest.php` from analysis, but a **new** arch test file is analysed at level max. `new \ReflectionClass($class)` with a `list<string>` fails PHPStan ("expects class-string<T of object>|T of object, string given"). Typing the helper as `list<class-string>` fixes it, and the short name is then derived with `substr()`/`strrpos()` so no reflection is needed at all.

**Why the namespace-relative FQCN and not the short name.** Matching on the short name is satisfiable by accident: the page already contains the substring `Prayer` (in `PrayerTimesCalculator`), `Angle` (in `AngleIsha`) and `Fajr` — so a class could be "documented" without an entry. The namespace-relative FQCN (`Prayer\Enums\Prayer`) is unambiguous.

**Wildcard rows.** The page currently compresses some classes into wildcard rows (`Prayer\Contracts\*Interface`, `Moon\Strategies\*Evaluator`, `SharedKernel\Exceptions\*`). An exact-FQCN gate cannot see through a wildcard, so **Step 3 expands those rows into explicit entries**. The gate stays exact and cannot be satisfied by an over-broad wildcard.

### Step 2: Run the test to verify it fails

Run: `vendor/bin/phpunit --no-coverage --group arch`
Expected: **FAIL** — `Public classes missing from docs/user/api-reference.md: Prayer\Services\NightResolver, Moon\Services\EphemerisConjunctionSearch, ...` (the classes the page does not currently name).

### Step 3: Complete the reference page

Rewrite `docs/user/api-reference.md` so it names every public class-like by its namespace-relative FQCN. There are **82** public class-likes after Task 4 adds `SharedKernel\ValueObjects\Kaaba` (81 today). Keep the existing structure (a table per context) and the existing wording; **add** the missing rows and **expand** the wildcard rows.

The complete list the page must name, grouped by context:

**SharedKernel** — 15 entries
`SharedKernel\Contracts\ClockView`, `SharedKernel\Contracts\DomainException`,
`SharedKernel\Exceptions\AlFalaqException`, `SharedKernel\Exceptions\AstronomicalException`,
`SharedKernel\Exceptions\ConfigurationException`, `SharedKernel\Exceptions\InvalidCoordinatesException`,
`SharedKernel\Exceptions\ValidationException`,
`SharedKernel\Traits\HasWithersGuard`, `SharedKernel\Traits\ValidatesFinite`,
`SharedKernel\ValueObjects\Angle`, `SharedKernel\ValueObjects\CivilDate`,
`SharedKernel\ValueObjects\Coordinates`, `SharedKernel\ValueObjects\JulianDay`,
`SharedKernel\ValueObjects\Kaaba`, `SharedKernel\ValueObjects\Minutes`

**Prayer** — 39 entries
`Prayer\Contracts\HighLatitudeCorrection`, `Prayer\Contracts\IshaRule`, `Prayer\Contracts\MaghribRule`,
`Prayer\Contracts\NextPrayerResolverInterface`, `Prayer\Contracts\PolarCircleHandler`,
`Prayer\Contracts\PrayerTimesCalculatorInterface`, `Prayer\Contracts\TimetableServiceInterface`,
`Prayer\Contracts\TwilightModel`,
`Prayer\Enums\CalculationMethod`, `Prayer\Enums\HighLatitudeRule`, `Prayer\Enums\Madhab`,
`Prayer\Enums\PolarCircleRule`, `Prayer\Enums\Prayer`,
`Prayer\Exceptions\UndefinedScheduleException`,
`Prayer\Services\NextPrayerResolver`, `Prayer\Services\NightResolver`,
`Prayer\Services\PrayerTimesCalculator`, `Prayer\Services\SunnahCalculator`, `Prayer\Services\TimetableService`,
`Prayer\Strategies\AngleIsha`, `Prayer\Strategies\AngleMaghrib`, `Prayer\Strategies\IntervalIsha`,
`Prayer\Strategies\MakkahHandler`, `Prayer\Strategies\MiddleOfNightCorrection`,
`Prayer\Strategies\MinutesAfterSunsetMaghrib`, `Prayer\Strategies\NearestDayHandler`,
`Prayer\Strategies\NearestLatitudeHandler`, `Prayer\Strategies\SeasonAdjustedTwilight`,
`Prayer\Strategies\SeventhOfNightCorrection`, `Prayer\Strategies\ThrowHandler`,
`Prayer\Strategies\TwilightAngleCorrection`,
`Prayer\Traits\NormalizesCoordinatesInput`,
`Prayer\ValueObjects\CalculationParameters`, `Prayer\ValueObjects\DisplaySchedule`,
`Prayer\ValueObjects\NextPrayer`, `Prayer\ValueObjects\NightPortions`,
`Prayer\ValueObjects\PrayerAdjustments`, `Prayer\ValueObjects\PrayerSchedule`,
`Prayer\ValueObjects\SunnahTimes`

**Qibla** — 6 entries
`Qibla\Contracts\GeodesyCalculator`, `Qibla\Services\QiblaCalculator`,
`Qibla\Strategies\SphericalCalculator`, `Qibla\Strategies\Wgs84KarneyCalculator`,
`Qibla\Traits\ClampsMakkahSelf`, `Qibla\ValueObjects\QiblaResult`

**Moon** — 10 entries
`Moon\Contracts\ConjunctionSearch`, `Moon\Contracts\CrescentEvaluator`,
`Moon\Services\CrescentCalculator`, `Moon\Services\EphemerisConjunctionSearch`,
`Moon\Strategies\Mabims1992Evaluator`, `Moon\Strategies\NeoMabims2021Evaluator`,
`Moon\Strategies\OdehEvaluator`, `Moon\Strategies\YallopEvaluator`,
`Moon\ValueObjects\CrescentGeometry`, `Moon\ValueObjects\CrescentVerdict`

**Hijri** — 12 entries
`Hijri\Contracts\HijriCalendar`, `Hijri\Contracts\OffsetStore`,
`Hijri\Enums\HijriMonth`, `Hijri\Enums\MabimsCountry`,
`Hijri\Exceptions\HijriOutOfRangeException`,
`Hijri\Services\GregorianToHijriConverter`,
`Hijri\Strategies\MabimsCalendar`, `Hijri\Strategies\TabularCalendar`, `Hijri\Strategies\UmmAlQuraCalendar`,
`Hijri\Traits\LooksUpMonthStarts`, `Hijri\Traits\ShiftsHijriDays`,
`Hijri\ValueObjects\HijriDate`

Content for the newly named classes (one table row each, in the page's existing `Class | Key members` shape):

- `SharedKernel\ValueObjects\JulianDay` | `fromCivilDate(CivilDate): self`; `fromFloat(float): self`; `toFloat(): float`
- `SharedKernel\ValueObjects\Kaaba` | `LATITUDE = 21.4225241`, `LONGITUDE = 39.8261818`; `coordinates(): Coordinates` — the single owner of the Kaaba position (the Qibla contract constants reference it)
- `SharedKernel\Contracts\DomainException` | Marker interface every domain exception implements — catch this for everything.
- `SharedKernel\Contracts\ClockView` | `now(): DateTimeImmutable` — an optional edge port for host apps; the domain never reads the clock.
- `SharedKernel\Traits\HasWithersGuard`, `SharedKernel\Traits\ValidatesFinite` | Stateless helpers used by the value objects.
- `Prayer\Contracts\IshaRule` | Driven port for the Isha rule: `angle(): ?float`, `minutesAfterMaghrib(): ?int`, `validate(): void`
- `Prayer\Contracts\MaghribRule` | Driven port for the Maghrib rule: `angle(): ?float`, `minutesAfterSunset(): ?int`, `validate(): void`
- `Prayer\Enums\Prayer` | `IMSAK, FAJR, SUNRISE, SYURUK, ISHRAQ, DHUHA, DHUHR, ASR, MAGHRIB, ISHA` (schedule order)
- `Prayer\Contracts\TwilightModel` | `seasonalSeconds(int $dayOfYear, int $year, float $latitude): array{morning: int, evening: int}` — the MSC seasonal caps
- `Prayer\Contracts\HighLatitudeCorrection` | `portions(float $fajrAngle, ?float $ishaAngle): NightPortions`
- `Prayer\Contracts\PolarCircleHandler` | `resolve(Coordinates, CivilDate, callable): array{coordinates, date}`
- `Prayer\ValueObjects\NightPortions` | `fajrPortion`, `ishaPortion` — the night split the high-latitude rules use
- `Prayer\Strategies\MiddleOfNightCorrection`, `Prayer\Strategies\SeventhOfNightCorrection`, `Prayer\Strategies\TwilightAngleCorrection` | `HighLatitudeCorrection` implementations
- `Prayer\Strategies\NearestLatitudeHandler`, `Prayer\Strategies\NearestDayHandler`, `Prayer\Strategies\MakkahHandler`, `Prayer\Strategies\ThrowHandler` | `PolarCircleHandler` implementations
- `Prayer\Strategies\SeasonAdjustedTwilight` | `TwilightModel` implementation (MSC seasonal caps)
- `Prayer\Services\NightResolver` | The step that owns the fajr/isha resolution (seasonal / NONE-raw / portions). Exposed for introspection; not a driving port.
- `Prayer\Traits\NormalizesCoordinatesInput` | Helper that accepts a `Coordinates` value object or a `[lat, lon]` pair
- `Qibla\Strategies\SphericalCalculator`, `Qibla\Strategies\Wgs84KarneyCalculator` | The two `GeodesyCalculator` implementations (spherical default; WGS84/Karney opt-in)
- `Qibla\Traits\ClampsMakkahSelf` | Helper that clamps the bearing to 0.0 below `GeodesyCalculator::SELF_DISTANCE_KM`
- `Moon\Services\EphemerisConjunctionSearch` | The `ConjunctionSearch` implementation (30-day hourly ARCL-minimum moon-age search)
- `Moon\Strategies\YallopEvaluator`, `Moon\Strategies\OdehEvaluator`, `Moon\Strategies\Mabims1992Evaluator`, `Moon\Strategies\NeoMabims2021Evaluator` | The four visibility criteria
- `Hijri\Traits\LooksUpMonthStarts`, `Hijri\Traits\ShiftsHijriDays` | Helpers shared by the month-start calendars: `LooksUpMonthStarts` contributes `fromGregorian`/`toGregorian`/`monthLength`, `ShiftsHijriDays` contributes `shift`

Also update the page's opening sentence so it matches reality — it now covers the whole public surface, and the arch gate enforces that.

Do **not** touch `docs/user/v0.1.0/api-reference.md` or `docs/user/v0.1.1/api-reference.md` — they are frozen release snapshots.

### Step 4: Run the test to verify it passes

Run: `vendor/bin/phpunit --no-coverage --group arch`
Expected: **PASS** — all four arch test classes green.

### Step 5: Update the arch-group lists

In `AGENTS.md`, in the "Testing Instructions" section, change:

```
- Layout mirrors `src/` as contexts land (M1: `SharedKernel`, `Sweep`,
  `Wiring`, `Prayer`, `Astro`, `Goldens`); `tests/Goldens/` holds pinned-Python parity vectors +
  `GoldenLoader.php` (load), `GoldenAsserts.php` (assert), `Comparator.php`
  (minute equality); arch groups (`tests/Arch{Shape,Docs,Layering}Test.php`,
  shared `tests/ArchSupport.php`) enforce layering via reflection (`#[Group('arch')]`).
```

to:

```
- Layout mirrors `src/` as contexts land (M1: `SharedKernel`, `Sweep`,
  `Wiring`, `Prayer`, `Astro`, `Goldens`); `tests/Goldens/` holds pinned-Python parity vectors +
  `GoldenLoader.php` (load), `GoldenAsserts.php` (assert), `Comparator.php`
  (minute equality); arch groups (`tests/Arch{Shape,Docs,Layering,ApiReference}Test.php`,
  shared `tests/ArchSupport.php`) enforce layering via reflection (`#[Group('arch')]`).
```

In `ARCHITECTURE.md`, the `tests/` bullet wraps across lines 159-161:

```
  compares minutes), and the arch groups
  (`tests/Arch{Shape,Docs,Layering}Test.php` + `ArchSupport.php`: layering and
  shape rules, `#[Group('arch')]`).
```

becomes:

```
  compares minutes), and the arch groups
  (`tests/Arch{Shape,Docs,Layering,ApiReference}Test.php` + `ArchSupport.php`:
  layering, shape and public-API-reference rules, `#[Group('arch')]`).
```

In `CONTEXT.md`, line 45 reads:

```
  arch groups (`Arch{Shape,Docs,Layering}Test.php` + `ArchSupport.php`),
```

(note: no `tests/` prefix — keep it that way) and becomes:

```
  arch groups (`Arch{Shape,Docs,Layering,ApiReference}Test.php` + `ArchSupport.php`),
```

### Step 6: Verify

Run: `vendor/bin/phpunit --no-coverage` → Expected: **OK (442 tests)**
Run: `composer cs` → Expected: **Found 0 of 159 files** need fixing (the baseline is 156; Tasks 4 and 5 add three files).

### Step 7: Commit

```bash
git add docs/user/api-reference.md tests/ArchApiReferenceTest.php AGENTS.md ARCHITECTURE.md CONTEXT.md
git commit -m "docs(api): complete the public-API reference and gate it

The page claimed to map the entire public surface but omitted ~26
public classes, including NightResolver in the public Services
namespace, EphemerisConjunctionSearch, JulianDay, the Prayer enum and
every strategy implementation. The page now names them all, and a new
arch gate fails the build when a public class is added without a
reference entry, so the inventory cannot silently rot again."
```

---

## Task 6: Widen the four T2-method prayer goldens to the 6-date grid

**Finding:** report P5. The pin-parity methods carry 48 golden rows each (6 dates × 8 cities). The four T2-provisional methods — Turkey, Kemenag, MUIS, Brunei — carry **2 rows each** (2 dates × 1 city), i.e. 24× less coverage, and they are precisely the methods with documented no-fit residuals and the largest uncertainty.

**What the official sources allow (measured 2026-10-22):**

| Method | Official source | Reachable? | Consequence |
|---|---|---|---|
| MUIS | MUIS "Muslim Prayer Timetable 2025" CSV, `data.gov.sg` dataset `d_e81ea2337599b674c4f645c1af93e0dc` (full year, Jan–Dec 2025) | **Yes** — full-year CSV downloaded | 4 new **official** rows |
| Turkey | Diyanet Istanbul monthly tables | **No** — only November-2025 Wayback captures exist; `vakithesaplama…?yil=2025` returns 403 | 4 **drift-gate** rows |
| Kemenag | Bimas Islam `getShalatbln` AJAX | **No** — endpoint returns 404; the site was restructured | 4 **drift-gate** rows |
| Brunei | KHEU "Waktu Sembahyang" list on `mora.gov.bn` | **No** — host does not resolve | 4 **drift-gate** rows |

This mirrors the precedent already recorded for Tehran and Morocco (`NEEDS_CONTEXT` — no obtainable official table → drift-gated golden, never claimed as parity).

### Step 1: Write the failing test

There is no new test to write: the golden test suite *is* the test. Add the MUIS rows first (Step 2) and the suite fails until the allow-list is updated.

Before touching any CSV, record the current baseline:

Run: `vendor/bin/phpunit --no-coverage tests/Goldens/PrayerGoldensTest.php`
Expected: **OK (6 tests)** — the baseline.

### Step 2: Add the four official MUIS rows

Rewrite `tests/Goldens/prayer_muis.csv` with all six rows. The measured per-marker comparison against the official MUIS 2025 timetable (Asia/Singapore, fixed +08:00 in 2025) is:

| date | fajr | sunrise/syuruk | dhuhr | asr | maghrib | isha | filled |
|---|---|---|---|---|---|---|---|
| 2025-01-15 | exact | exact | exact | exact | exact | **−1** | 6/10 |
| 2025-03-02 | −1 | −1 | −1 | −1 | **−2** | −1 | 0/10 |
| 2025-03-20 | −1 | −1 | −1 | −1 | **−2** | −1 | 0/10 |
| 2025-06-21 | **−1** | exact | exact | exact | exact | exact | 6/10 (existing) |
| 2025-09-22 | exact | −1 | −1 | −1 | −1 | −1 | 1/10 |
| 2025-12-15 | exact | exact | exact | exact | exact | exact | 7/10 (existing) |

**Sign convention.** The delta is **ours − published**, so a negative delta means our output is *earlier* than the published value: `−1` means the official table is one minute **later** than we compute, and `−2` (maghrib, both March dates) means two minutes later. The allow-list reasons and `docs/prayer.md` must use this direction, or "no-fit −1" will be read as "we are one minute early" by a reader who assumes the opposite.

The two existing rows (2025-06-21 and 2025-12-15) are reproduced byte-for-byte by this measurement, which validates the local→UTC mapping (Singapore has observed no DST since 1982, so +08:00 is fixed across 2025).

The new file content:

```csv
method,date,latitude,longitude,imsak,fajr,sunrise,syuruk,ishraq,dhuha,dhuhr,asr,maghrib,isha,source
muis,2025-01-15,1.3521,103.8198,,2025-01-14T21:50:00+00:00,2025-01-14T23:13:00+00:00,2025-01-14T23:13:00+00:00,,,2025-01-15T05:15:00+00:00,2025-01-15T08:39:00+00:00,2025-01-15T11:16:00+00:00,,takwim:data.gov.sg@d_e81ea2337599b674c4f645c1af93e0dc@2026-10-22 Asia/Singapore/+08:00 MUIS-published-2025-timetable
muis,2025-03-02,1.3521,103.8198,,,,,,,,,,,takwim:data.gov.sg@d_e81ea2337599b674c4f645c1af93e0dc@2026-10-22 Asia/Singapore/+08:00 MUIS-published-2025-timetable
muis,2025-03-20,1.3521,103.8198,,,,,,,,,,,takwim:data.gov.sg@d_e81ea2337599b674c4f645c1af93e0dc@2026-10-22 Asia/Singapore/+08:00 MUIS-published-2025-timetable
muis,2025-06-21,1.3521,103.8198,,,2025-06-20T23:01:00+00:00,2025-06-20T23:01:00+00:00,,,2025-06-21T05:08:00+00:00,2025-06-21T08:33:00+00:00,2025-06-21T11:13:00+00:00,2025-06-21T12:28:00+00:00,takwim:data.gov.sg@d_e81ea2337599b674c4f645c1af93e0dc@2026-10-22 Asia/Singapore/+08:00 MUIS-published-2025-timetable
muis,2025-09-22,1.3521,103.8198,,2025-09-21T21:38:00+00:00,,,,,,,,,takwim:data.gov.sg@d_e81ea2337599b674c4f645c1af93e0dc@2026-10-22 Asia/Singapore/+08:00 MUIS-published-2025-timetable
muis,2025-12-15,1.3521,103.8198,,2025-12-14T21:35:00+00:00,2025-12-14T22:58:00+00:00,2025-12-14T22:58:00+00:00,,,2025-12-15T05:01:00+00:00,2025-12-15T08:25:00+00:00,2025-12-15T11:01:00+00:00,2025-12-15T12:16:00+00:00,takwim:data.gov.sg@d_e81ea2337599b674c4f645c1af93e0dc@2026-10-22 Asia/Singapore/+08:00 MUIS-published-2025-timetable
```

**Line endings.** `tests/Goldens/prayer_muis.csv` is currently **CRLF** (like the other three T2 files; only tehran/morocco/uoif are LF). Keep it CRLF — write the file with CRLF line endings so the diff stays a clean addition rather than a whole-file rewrite. `fgetcsv` and `GoldenLoader` handle either, but the per-file convention is consistent endings.

Notes on the row shapes:
- `imsak`, `ishraq` and `dhuha` are empty in every row: MUIS publishes no imsak, ishraq or dhuha. The existing rows already carry this convention (`'unpublished by MUIS table'`).
- `syuruk` carries the same instant as `sunrise` — it is the sunrise alias, matching every other golden file.
- `2025-03-02` and `2025-03-20` are **fully empty** rows: every published marker is a ±1–2 min residual against our output (our value is 1 min early on every marker and 2 min early on maghrib). A fully empty row is the golden model's "known unresolvable" category and is owned by `testOnlyDocumentedRowsAreEmpty` — see Step 3. The rows stay in the file so the disagreement stays **loud** instead of silently absent.
- The `source` tag on the two pre-existing rows is updated to name the dataset id and the 2026-10-22 access date, because both rows were re-verified against that exact CSV in this task.

### Step 3: Update the golden test expectations

In `tests/Goldens/PrayerGoldensTest.php`:

**3a. Add the allow-list entries** for the non-empty rows that have emptied cells. Insert into `ALLOWED_EMPTY_CELLS` after the existing MUIS entries:

```php
        // MUIS 2025-01-15 (official MUIS 2025 timetable): the published Isyak
        // is one minute later than our output; every other published marker
        // matches exactly.
        'muis|2025-01-15|1.3521|103.8198|imsak' => 'unpublished by MUIS table',
        'muis|2025-01-15|1.3521|103.8198|ishraq' => 'unpublished by MUIS table',
        'muis|2025-01-15|1.3521|103.8198|dhuha' => 'unpublished by MUIS table',
        'muis|2025-01-15|1.3521|103.8198|isha' => 'no-fit -1 single-cell rounding (docs/prayer.md)',
        // MUIS 2025-09-22 (official MUIS 2025 timetable): only Subuh matches
        // exactly; the other published markers are one minute later.
        'muis|2025-09-22|1.3521|103.8198|imsak' => 'unpublished by MUIS table',
        'muis|2025-09-22|1.3521|103.8198|sunrise' => 'no-fit -1 single-cell rounding (docs/prayer.md)',
        'muis|2025-09-22|1.3521|103.8198|syuruk' => 'no-fit -1 single-cell rounding (docs/prayer.md)',
        'muis|2025-09-22|1.3521|103.8198|ishraq' => 'unpublished by MUIS table',
        'muis|2025-09-22|1.3521|103.8198|dhuha' => 'unpublished by MUIS table',
        'muis|2025-09-22|1.3521|103.8198|dhuhr' => 'no-fit -1 single-cell rounding (docs/prayer.md)',
        'muis|2025-09-22|1.3521|103.8198|asr' => 'no-fit -1 single-cell rounding (docs/prayer.md)',
        'muis|2025-09-22|1.3521|103.8198|maghrib' => 'no-fit -1 single-cell rounding (docs/prayer.md)',
        'muis|2025-09-22|1.3521|103.8198|isha' => 'no-fit -1 single-cell rounding (docs/prayer.md)',
```

**3b. Extend the fully-empty-row expectation.** The two March MUIS rows are fully empty, so they join the whitelist owned by `testOnlyDocumentedRowsAreEmpty`. The whitelist is compared with `assertSame`, which is **order-sensitive**, and the loop walks `expectedFiles()` in order — `prayer_muis.csv` comes before `prayer_tehran.csv`, so the MUIS entries come first.

Replace:

```php
        self::assertSame(['tehran|2025-06-21|69.6496|18.9553'], $empty);
```

with:

```php
        self::assertSame([
            'muis|2025-03-02|1.3521|103.8198',
            'muis|2025-03-20|1.3521|103.8198',
            'tehran|2025-06-21|69.6496|18.9553',
        ], $empty);
```

and extend that test's docblock to name the second category:

```php
    /**
     * Known fully-empty rows, each with its documented reason.
     *
     * Two categories exist:
     *
     * 1. Unreachable geometry — Tehran AngleMaghrib is unreachable during
     *    the Tromsoe polar day, so the row cannot be computed at all.
     * 2. A documented no-fit against the official table — on both Ramadan
     *    dates of 2025 every marker MUIS publishes is a ±1–2 minute
     *    residual against our output (our value is 1 min early on every
     *    marker and 2 min early on maghrib),
     *    so no cell can be pinned to the minute. The rows stay in the file
     *    so the disagreement is loud rather than silently absent.
     *
     * Any other empty row means lost golden coverage and must fail loudly
     * instead of being skipped.
     */
```

**3c. Update the class docblock** of `PrayerGoldensTest`. The sentence about the fully-empty rows becomes false once the two MUIS March rows are added — there are now **three** fully-empty rows, not one. Replace:

```
 * cell fails the build. The single fully-empty Tehran polar-day row is not
 * allow-listed: it is owned separately by testOnlyDocumentedRowsAreEmpty.
```

with:

```
 * cell fails the build. The three fully-empty rows are not allow-listed:
 * they are owned separately by testOnlyDocumentedRowsAreEmpty.
```

and replace the sentence about the expected file set:

```
 * Expected file set is explicit: 18 present (11 pin + 4
 * provisional + tehran/morocco/uoif drift-gated by tools/gen_self_prayer_goldens.php:
```

with:

```
 * Expected file set is explicit: 18 present (11 pin + 4
 * provisional + tehran/morocco/uoif drift-gated by tools/gen_self_prayer_goldens.php;
 * turkey/kemenag/brunei drift rows by tools/gen_t2_drift_goldens.php:
```

### Step 4: Run the golden test

Run: `vendor/bin/phpunit --no-coverage tests/Goldens/PrayerGoldensTest.php`
Expected: **OK (6 tests)** — every non-empty cell matches to the minute, every empty cell is allow-listed, and the fully-empty whitelist matches exactly.

### Step 5: Add the T2 drift-gate generator

Create `tools/gen_t2_drift_goldens.php`:

```php
<?php

declare(strict_types=1);

/**
 * Regenerate the drift-gated rows of the three T2 prayer goldens that have
 * no obtainable official table for the remaining grid dates.
 *
 * Turkey, Kemenag and Brunei ship official-table rows for two dates
 * (2025-06-21 and 2025-12-15) taken from the published timetables. The
 * official monthly tables for the remaining grid dates could not be
 * retrieved on 2026-10-22 (Diyanet has no archived monthly tables for
 * those months; the Kemenag `getShalatbln` endpoint is gone; the KHEU
 * `mora.gov.bn` host does not resolve), and no obtainable archive exists
 * for them — the same situation recorded for Tehran and Morocco.
 *
 * So the remaining grid dates are drift-gated: our own output, recorded as
 * a regression guard and explicitly NOT a parity claim. The `source` tag
 * says so on every row.
 *
 * MUIS is NOT handled by this tool: its 2025 timetable is published in full
 * on data.gov.sg, so every MUIS row is an official `takwim:` row, hand-pinned
 * rather than generated.
 *
 * Merge semantics: existing `takwim:` rows are preserved verbatim (they
 * are the official claim and this tool never touches them); `drift:` rows
 * are regenerated. Re-running is idempotent and byte-stable.
 *
 * Regenerate and diff-review with: php tools/gen_t2_drift_goldens.php
 */

require __DIR__ . '/../vendor/autoload.php';

use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Enums\Prayer;
use AlFalaq\AlFalaq\Prayer\Services\PrayerTimesCalculator;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\AstronomicalException;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

/** The dates the official tables do not cover for the T2 methods. */
$driftDates = ['2025-01-15', '2025-03-02', '2025-03-20', '2025-09-22'];

/** Each T2 method's own city, as published in its official table. */
$cities = [
    CalculationMethod::TURKEY->value => [41.0082, 28.9784],
    CalculationMethod::KEMENAG->value => [-6.2000, 106.8500],
    CalculationMethod::BRUNEI->value => [4.9031, 114.9398],
];

$markers = array_map(static fn(Prayer $p): string => $p->value, Prayer::cases());

$sourceTag = 'drift:no-official-table-retrievable-2026-10-22;not-parity;drift-gate;'
    . 'diyanet-no-archived-monthly-tables;kemenag-getShalatbln-404;kheu-mora-host-unresolved;'
    . 'takwim-rows-preserved';

foreach ($cities as $methodValue => [$lat, $lon]) {
    $method = CalculationMethod::from($methodValue);
    $path = __DIR__ . '/../tests/Goldens/prayer_' . $method->value . '.csv';

    $raw = (string) file_get_contents($path);

    if ($raw === '') {
        throw new RuntimeException('Empty golden file ' . $path);
    }

    // The three files this tool touches are CRLF (the LF drift files
    // tehran/morocco/uoif belong to gen_self_prayer_goldens.php and are
    // never read here). Keep whatever the target file already uses.
    $eol = str_contains($raw, "\r\n") ? "\r\n" : "\n";
    $lines = explode($eol, rtrim($raw, "\r\n"));
    $headerLine = array_shift($lines);

    if ($headerLine === null) {
        throw new RuntimeException('Empty golden file ' . $path);
    }

    // Keep the official rows verbatim, drop any previously generated drift
    // rows. The official rows are kept as RAW LINES, not re-serialised
    // arrays: fputcsv re-quotes a field that contains a space, which would
    // rewrite the official `takwim:` source tags and turn a four-row diff
    // into a whole-file diff.
    $kept = [];

    foreach ($lines as $line) {
        if ($line === '') {
            continue;
        }

        $fields = str_getcsv($line, ',', '"', '');
        $source = $fields === [] ? '' : (string) ($fields[count($fields) - 1] ?? '');

        if (str_starts_with($source, 'drift:')) {
            continue;
        }

        $kept[] = $line;
    }

    $params = $method->defaultParameters();
    $generated = [];

    foreach ($driftDates as $datestr) {
        [$y, $m, $d] = array_map('intval', explode('-', $datestr));
        $row = [$method->value, $datestr, sprintf('%.4f', $lat), sprintf('%.4f', $lon)];

        try {
            $schedule = PrayerTimesCalculator::default()->calculate(
                Coordinates::fromDegrees($lat, $lon),
                CivilDate::fromYmd($y, $m, $d),
                $params,
            );

            foreach ($markers as $marker) {
                $row[] = $schedule->timeFor(Prayer::from($marker))->format('Y-m-d\TH:i:s+00:00');
            }
        } catch (AstronomicalException $e) {
            throw new RuntimeException(sprintf(
                'Unresolvable drift row %s %s: %s',
                $method->value,
                $datestr,
                $e->getMessage(),
            ));
        }

        $row[] = $sourceTag;
        $generated[] = csvLine($row);
    }

    $out = $headerLine . $eol;

    foreach ($kept as $line) {
        $out .= $line . $eol;
    }

    foreach ($generated as $line) {
        $out .= $line . $eol;
    }

    $tmpPath = $path . '.tmp';

    if (file_put_contents($tmpPath, $out) === false) {
        throw new RuntimeException('Cannot write ' . $tmpPath);
    }

    if (! rename($tmpPath, $path)) {
        throw new RuntimeException('Cannot replace ' . $path);
    }

    echo 'wrote ' . $path . PHP_EOL;
}

/**
 * One CSV record as a single line, without a trailing newline.
 *
 * fputcsv() quotes a field that contains a space; the source tag is
 * deliberately written without spaces so the generated rows stay unquoted,
 * matching the hand-maintained takwim rows they sit next to.
 *
 * @param list<string> $fields Record fields.
 * @return string The record as one line.
 */
function csvLine(array $fields): string
{
    $stream = fopen('php://memory', 'r+');

    if ($stream === false) {
        throw new RuntimeException('Cannot open the in-memory stream.');
    }

    if (fputcsv($stream, $fields, ',', '"', '') === false) {
        fclose($stream);

        throw new RuntimeException('Cannot format the CSV record.');
    }

    rewind($stream);
    $line = (string) stream_get_contents($stream);
    fclose($stream);

    return rtrim($line, "\r\n");
}
```

### Step 6: Generate the drift rows

Run: `php tools/gen_t2_drift_goldens.php`
Expected:
```
wrote .../tests/Goldens/prayer_turkey.csv
wrote .../tests/Goldens/prayer_kemenag.csv
wrote .../tests/Goldens/prayer_brunei.csv
```

Run it a second time and confirm byte-stability:
Run: `php tools/gen_t2_drift_goldens.php && git diff --stat tests/Goldens/`
Expected: no further change (the generator is idempotent).

### Step 7: Verify the golden suite

Run: `vendor/bin/phpunit --no-coverage tests/Goldens/PrayerGoldensTest.php`
Expected: **OK (6 tests)** — the drift rows match our output exactly (they are generated from it), the official rows still match to the minute, and the allow-list is exact.

Run: `vendor/bin/phpunit --no-coverage` → Expected: **OK (442 tests)**

Run: `composer lint && composer cs` → Expected: pass. The php-cs-fixer finder covers `tools/`, so the new generator is formatted and counted: **Found 0 of 160 files** need fixing (the baseline is 156; Tasks 4, 5 and 6 add four files).

### Step 8: Record the source data

The MUIS CSV must be reproducible. Download it with:

```bash
curl -sS "https://api-open.data.gov.sg/v1/public/api/datasets/d_e81ea2337599b674c4f645c1af93e0dc/poll-download" -o /tmp/muis.json
URL=$(python3 -c "import json;print(json.load(open('/tmp/muis.json'))['data']['url'])")
curl -sS "$URL" -o /tmp/muis2025.csv
```

The dataset id `d_e81ea2337599b674c4f645c1af93e0dc` is recorded in every MUIS row's `source` tag, so the exact artefact is identifiable without a copy in the repository.

### Step 9: Commit

```bash
git add tests/Goldens/prayer_muis.csv tests/Goldens/prayer_turkey.csv tests/Goldens/prayer_kemenag.csv tests/Goldens/prayer_brunei.csv tests/Goldens/PrayerGoldensTest.php tools/gen_t2_drift_goldens.php
git commit -m "test(goldens): widen the four T2 prayer goldens to the 6-date grid

Turkey, Kemenag, MUIS and Brunei carried 2 golden rows each against
48 for every pin-parity method, and they are the methods with the
documented no-fit residuals.

MUIS gains four OFFICIAL rows from the published MUIS 2025 timetable
(data.gov.sg d_e81ea2337599b674c4f645c1af93e0dc, retrieved
2026-10-22): 2025-01-15 and 2025-09-22 pin six and one markers
exactly, and both Ramadan dates are recorded as fully-empty rows
because every published marker is a +/-1-2 minute residual (our value
is 1 min early on every marker, 2 min early on maghrib).

Turkey, Kemenag and Brunei gain four DRIFT-GATE rows each: their
official monthly tables for those dates could not be retrieved on
2026-10-22 (no archived Diyanet monthly tables, the Kemenag
getShalatbln endpoint returns 404, the KHEU mora.gov.bn host does not
resolve) and no obtainable archive exists, the same situation already
recorded for Tehran and Morocco. The source tag says 'not-parity' on
every drift row."
```

---

## Task 7: Documentation sync

**Files:**
- Modify: `docs/prayer.md`, `docs/user/citations.md`, `CHANGELOG.md`, `ARCHITECTURE.md`, `README.md`

### Step 1: Update `docs/prayer.md`

The sentence at `docs/prayer.md:86-88` becomes false once the two MUIS March rows are added — there are now **three** fully-empty rows, not one. Replace:

```
  the build (issue #29). The single fully-empty Tehran polar-day row is not
  allow-listed: it is owned separately by
  `testOnlyDocumentedRowsAreEmpty`.
```

with:

```
  the build (issue #29). The three fully-empty rows (the Tehran polar-day
  row and both MUIS Ramadan rows) are not allow-listed: they are owned
  separately by `testOnlyDocumentedRowsAreEmpty`.
```

Then, in the same bullet, after the sentence about cells unpublished by the official tables, add:

```markdown
  The MUIS 2025 rows are re-pinned against the published MUIS 2025
  timetable (`data.gov.sg` dataset `d_e81ea2337599b674c4f645c1af93e0dc`,
  retrieved 2026-10-22): 2025-01-15 pins six markers exactly and
  2025-09-22 pins one, while both Ramadan dates (2025-03-02, 2025-03-20)
  are fully-empty rows because every marker MUIS publishes is a ±1–2 min
  residual — our output is 1 min early on every marker and 2 min early on
  maghrib. Turkey, Kemenag and Brunei carry drift-gate rows for the four
  grid dates their official tables could not cover (no archived Diyanet
  monthly tables for those months, the Kemenag `getShalatbln` endpoint
  404s, the KHEU `mora.gov.bn` host does not resolve) — drift gates, never
  claimed as parity.
```

### Step 2: Update `docs/user/citations.md`

Add the MUIS dataset to the sources list:

```markdown
- **MUIS prayer timetable 2025** — Majlis Ugama Islam Singapura, via
  data.gov.sg dataset `d_e81ea2337599b674c4f645c1af93e0dc` (retrieved
  2026-10-22). Subuh, Syuruk, Zohor, Asar, Maghrib, Isyak for the whole
  of 2025, Singapore local time (+08:00, no DST).
```

### Step 3: Update `CHANGELOG.md`

Replace the `## [Unreleased]` body:

```markdown
## [Unreleased]

### Changed

- Prayer engine: the solar triplet no longer recomputes the declination from
  the Julian Day — it reuses the apparent longitude and obliquity it already
  holds. `declinationFrom()` is now the single owner of the formula. Output
  is unchanged (all 5,133 golden rows stay green); the redundant work was
  measured at 19.8% of a solar triplet's trig cost.
- Bench harness: the printed contract and the enforced contract now agree.
  The tripwires are printed as the contract (single-day 50 ms, year 30,000 ms,
  unchanged) and the PRD figures are printed on their own line as
  hardware- and load-dependent references that are explicitly not gated.
- Golden coverage: Turkey, Kemenag, MUIS and Brunei move from 2 golden rows
  to the 6-date grid. MUIS gains four official rows from the published MUIS
  2025 timetable; Turkey, Kemenag and Brunei gain drift-gate rows for the
  dates their official tables could not cover.
- The Kaaba coordinates get a single owner (`SharedKernel\ValueObjects\Kaaba`);
  the public `GeodesyCalculator` constants now reference it, so the Prayer
  polar fallback and the Qibla geodesy cannot drift apart.
- `docs/user/api-reference.md` now names the whole public surface, and a new
  arch gate (`tests/ArchApiReferenceTest.php`) fails the build when a public
  class is added without a reference entry.

### Fixed

- The bench harness printed a budget it did not meet and enforced a gate 25x
  looser than that budget. The printed contract and the enforced contract now
  agree.
- `composer mutation` could not run locally: Composer's default 300-second
  process timeout killed the Infection run at roughly 1,500 of 2,377 mutants.
  `composer.json` now sets `config.process-timeout: 0`, matching the
  `COMPOSER_PROCESS_TIMEOUT=0` that CI already exports.
```

Keep the section order `Added` → `Changed` → `Fixed`. The `pre-release` workflow
only requires a `## [X.Y.Z] - YYYY-MM-DD` header at tag time, so the Unreleased
body is free-form.

### Step 4: Update `ARCHITECTURE.md` and `README.md`

In `ARCHITECTURE.md`, in the `src/SharedKernel/` bullet, add `Kaaba` to the value-object list:

```
- `src/SharedKernel/` — cross-context foundation. `ValueObjects/`
  (`Coordinates`, `CivilDate`, `Angle`, `Minutes`, `JulianDay`, `Kaaba`),
```

and in the `tools/` bullet, add the new generator:

```
  `gen_self_prayer_goldens.php`
  (drift-gated Tehran/Morocco/UOIF goldens, regenerate + diff-review in place),
  `gen_t2_drift_goldens.php` (drift-gated Turkey/Kemenag/Brunei rows; the
  official-table rows are preserved verbatim),
```

In `README.md`, line 5 is a single-line Status paragraph. Make the **minimal inline edit**: replace the substring

```
drift-gated tehran/morocco/uoif;
```

with

```
drift-gated tehran/morocco/uoif and turkey/kemenag/brunei;
```

and leave the rest of the line untouched — the paragraph is one long line, so do not reflow it and do not introduce a soft line break inside it. The wording must keep the drift gates labelled as such — they are regression guards, never parity claims.

### Step 5: Verify

Run: `composer lint && composer cs && composer stan && vendor/bin/phpunit --no-coverage`
Expected: all green.

Run: `composer validate --strict` → Expected: valid.

### Step 6: Commit

```bash
git add docs/prayer.md docs/user/citations.md CHANGELOG.md ARCHITECTURE.md README.md
git commit -m "docs: sync the public guides with the tier-1 changes"
```

---

## Final Verification

Run, in order:

```bash
composer validate --strict
composer lint
composer cs
composer stan
composer test-unit
composer test
composer arch
composer bench
composer mutation
```

Expected: every gate green. `composer test` must report coverage at or above the 95% floor (the baseline was 97.2%). `composer mutation` must report MSI ≥ 85% (baseline 91%). `composer bench` must print the new tripwire line and exit 0.

Then confirm the working tree is clean and the branch is ahead of `main` by the expected number of commits.

---

## Out of Scope

These were Tier 2 and Tier 3 in `REPO_HYGIENE_REPORT.md` and are deliberately **not** in this plan:

- `CalculationParameters` wither boilerplate collapse (Tier 2, #9).
- `GregorianToHijriConverter::maghrib()` catch widening (Tier 2, #7).
- `NightResolver` seasonal-path comment and eager `$portions` (Tier 2, #8).
- Deleting the vestigial `ClockView` and `SolarPosition` (Tier 2, #10).
- The six missing arch assertions (Contracts-are-interfaces, `*Interface`
  suffix, one-class-per-file, ValueObjects-readonly, Traits-private,
  Services-`default()`) (Tier 2, #11).
- `docs/integrations.md` recipes for Moon, Hijri, Timetable and Sunnah
  (Tier 2, #12).
- The changelog duplication between `CHANGELOG.md` and
  `docs/user/changelog.md` (Tier 3, #15).
- `npm` dependabot entry and audit gate (Tier 3, #16).
- The `AGENTS.md` wording for traits-with-public-methods and
  services-without-`default()` (Tier 3, #17).
- Direct unit tests for `Minutes`, `Angle::toRadians()`, `Coordinates::__toString()`
  and `CivilDate::compareTo()` (Tier 3, #18).
- The `calculate()` clamp-order comment (Tier 3, #19).
- Merging or scoping `docs/` against `docs/user/` (Tier 3, #20).

`REPO_HYGIENE_REPORT.md` is a point-in-time snapshot and is **not** edited by this plan; the CHANGELOG is where "what changed" is recorded.
