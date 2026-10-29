# Tier 2 Hygiene Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Land the seven Tier-2 findings from `REPO_HYGIENE_REPORT.md` as one pull request: widen the Hijri maghrib fallback, stop `NightResolver` computing portions it discards, collapse the `CalculationParameters` wither boilerplate, delete the two vestigial seams (`ClockView`, `SolarPosition`/`position()`), make the six unenforced `AGENTS.md` conventions machine-checked, and make `docs/integrations.md` a runnable, test-executed wiring guide for every feature.

**Architecture:** Seven individually-revertable changes that share one theme — *remove claims the code does not honour, then gate the claim*. Tasks 1–5 are independent; Task 6 builds on Task 5's `default()` factories; Task 7 is the final documentation sync. Three are small code changes protected by new tests: one behavioural fix (the Hijri catch) and two behaviour-preserving refactors (the night path and the wither collapse). Two are deletions whose absence is proven by a new reverse API-reference gate and the full test suite. One is a set of arch gates plus the two trivial `default()` factories that make those gates true. One turns the wiring docs into the executable source of truth: the test extracts and evaluates every fenced PHP block instead of re-implementing it.

**Tech Stack:** PHP 8.2+ (no runtime dependencies), PHPUnit 11, PHPStan max + strict-rules, php-cs-fixer `@PER`, Infection, Astro Starlight docs site, plain-Python tooling under `tools/`.

**Plan location note:** the `writing-plans` skill defaults to `docs/superpowers/plans/`, but this repository gitignores `/docs/superpowers/` ("Development-only brainstorming artifacts — never merged"). The plan must be committed with the work; it follows the Tier-1 precedent at `docs/plans/2026-10-22-tier1-hygiene.md`.

**Branch:** `fix/tier2-hygiene-2026-10-25` (split from `main` at `7150a52`).

**Baseline (verified on `main` at `7150a52`):** 443 tests / 49,926 assertions, 0 PHPStan errors, 0 php-cs-fixer findings, coverage 97.2% against a 95 floor, `composer arch` green.

**Frozen artefacts:** `docs/user/v0.1.0/**` and `docs/user/v0.1.1/**` are released snapshots. Only current `docs/user/*.md` files change.

---

## File Structure

| File | Responsibility | Task |
|---|---|---|
| `src/Hijri/Services/GregorianToHijriConverter.php` | Catch the whole `AstronomicalException` family, not one subclass | 1 |
| `tests/Hijri/GregorianToHijriConverterTest.php` | Fallback guard + non-swallow guard | 1 |
| `src/Prayer/Services/NightResolver.php` | Resolve portions only on the portions mode | 2 |
| `tests/Prayer/NightResolverTest.php` | Prove the seasonal path never asks for portions | 2 |
| `src/Prayer/ValueObjects/CalculationParameters.php` | One private `copyWith()` construction site | 3 |
| `tests/Prayer/CalculationParametersTest.php` | Reflection net: every wither changes exactly one field | 3 |
| `src/SharedKernel/Contracts/ClockView.php` | **Delete** (never implemented or referenced) | 4 |
| `src/Astro/ValueObjects/SolarPosition.php` | **Delete** (only caller was its own test) | 4 |
| `src/Astro/Services/MeanSolarCalculator.php` | Drop `position()` and the duplicate class-docblock paragraph | 4 |
| `tests/Astro/MeanSolarCalculatorTest.php` | Drop the `position()` assertions | 4 |
| `tests/ArchApiReferenceTest.php` | Reverse gate: every documented class has a source file | 4 |
| `docs/user/api-reference.md` | Remove the `ClockView` row; add `default()` to two service rows | 4, 7 |
| `tests/ArchShapeTest.php` | Six convention gates + file-counter self-test + allow-list rot guard | 5 |
| `tests/ArchSupport.php` | Tokenizer helper for one-class-per-file | 5 |
| `src/Prayer/Services/NextPrayerResolver.php` | Add `default()` | 5 |
| `src/Prayer/Services/TimetableService.php` | Add `default()` | 5 |
| `AGENTS.md` | Conventions wording matches what the gates enforce | 5 |
| `docs/integrations.md` | Self-contained recipes for every feature | 6 |
| `tests/Wiring/CalculatorWiringTest.php` | Execute the doc blocks; delete the re-implemented snippets | 6 |
| `ARCHITECTURE.md` | Remove `ClockView`/`SolarPosition`; record the new gate inventory | 4, 7 |
| `CHANGELOG.md` | `[Unreleased]` entry | 7 |

---

## Task 1: Widen the Hijri maghrib fallback catch (P8)

**Finding:** `GregorianToHijriConverter::maghrib()` catches only `UndefinedScheduleException`, which is a subclass of `AstronomicalException`. `PrayerTimesCalculator::calculate()` also throws plain `AstronomicalException` for "Unable to compute Asr/Isha/Maghrib". Those escape the intended `maghribAt()` fallback, so a strict high-latitude config that has a defined maghrib but an undefined Asr fails the conversion for no reason.

**Files:**
- Modify: `src/Hijri/Services/GregorianToHijriConverter.php:12`, `:129-137`
- Test: `tests/Hijri/GregorianToHijriConverterTest.php` (append before the final `}`; update the stale white-night comment)

- [ ] **Step 1: Write the failing test**

Append these two methods to `tests/Hijri/GregorianToHijriConverterTest.php`. The file already imports `AstronomicalException`, `ValidationException`, `PrayerSchedule`, `DateTimeImmutable`, `DateTimeZone`, `TabularCalendar`, `Coordinates`, `CivilDate`, `CalculationMethod`, `PrayerTimesCalculatorInterface`, and `GregorianToHijriConverter`, so no new imports are needed.

```php
    /**
     * A plain AstronomicalException (Asr/Isha undefined) takes the
     * maghrib-only fallback too: #15 is "schedule undefined", not "the
     * pipeline threw one particular subclass".
     *
     * @return void
     */
    public function testSunsetRollFallsBackOnPlainAstronomicalException(): void
    {
        $utc = new DateTimeZone('UTC');
        $calculator = new class implements PrayerTimesCalculatorInterface {
            public function calculate(Coordinates $coordinates, CivilDate $date, CalculationParameters $parameters): PrayerSchedule
            {
                throw new AstronomicalException('Unable to compute Asr: the afternoon shadow length is undefined.');
            }

            public function maghribAt(Coordinates $coordinates, CivilDate $date, CalculationParameters $parameters): DateTimeImmutable
            {
                return new DateTimeImmutable($date->isoformat() . ' 10:00:00', new DateTimeZone('UTC'));
            }
        };

        $converter = new GregorianToHijriConverter($calculator);
        $calendar = new TabularCalendar();

        self::assertSame(
            $calendar->fromGregorian(CivilDate::fromYmd(2025, 3, 2))->isoformat(),
            $converter->convert(
                new DateTimeImmutable('2025-03-01 12:00:00', $utc),
                $calendar,
                $this->kualaLumpur(),
                CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters(),
                true,
            )->isoformat(),
        );
    }

    /**
     * Validation errors are not "schedule undefined": they must escape.
     *
     * A future widening to AlFalaqException (or Throwable) would make a bad
     * caller configuration look like a polar fallback; this pins the edge.
     *
     * @return void
     */
    public function testSunsetRollDoesNotSwallowValidationErrors(): void
    {
        $calculator = new class implements PrayerTimesCalculatorInterface {
            public function calculate(Coordinates $coordinates, CivilDate $date, CalculationParameters $parameters): PrayerSchedule
            {
                throw new ValidationException('Invalid parameters.');
            }

            public function maghribAt(Coordinates $coordinates, CivilDate $date, CalculationParameters $parameters): DateTimeImmutable
            {
                return new DateTimeImmutable($date->isoformat() . ' 10:00:00', new DateTimeZone('UTC'));
            }
        };

        $converter = new GregorianToHijriConverter($calculator);

        $this->expectException(ValidationException::class);

        $converter->convert(
            new DateTimeImmutable('2025-03-01 12:00:00', new DateTimeZone('UTC')),
            new TabularCalendar(),
            $this->kualaLumpur(),
            CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters(),
            true,
        );
    }
```

- [ ] **Step 2: Run the tests to verify the red/green split**

Run:

```bash
vendor/bin/phpunit --no-coverage --filter 'testSunsetRollFallsBackOnPlainAstronomicalException|testSunsetRollDoesNotSwallowValidationErrors' tests/Hijri/GregorianToHijriConverterTest.php
```

Expected: `testSunsetRollFallsBackOnPlainAstronomicalException` **FAILS** with `AstronomicalException: Unable to compute Asr…` (the fallback was never reached). `testSunsetRollDoesNotSwallowValidationErrors` **PASSES** — it is a regression guard, not a red test.

- [ ] **Step 3: Widen the catch**

In `src/Hijri/Services/GregorianToHijriConverter.php`, replace the now-unused import:

```php
use AlFalaq\AlFalaq\Prayer\Exceptions\UndefinedScheduleException;
```

with:

```php
use AlFalaq\AlFalaq\SharedKernel\Exceptions\AstronomicalException;
```

Then replace the catch block:

```php
        } catch (UndefinedScheduleException) {
            // Strict high-latitude configs may leave the full schedule
            // undefined while maghrib itself is defined; resolve maghrib
            // alone rather than failing the conversion. Throws the same
            // exception when maghrib itself is undefined (e.g. polar night).
            return $this->calculator->maghribAt($coordinates, $date, $parameters);
        }
```

with:

```php
        } catch (AstronomicalException) {
            // Any astronomical "schedule undefined" — polar day/night, no
            // Asr, no Isha — falls back to maghrib alone, which is defined
            // whenever the sun sets. Validation/configuration errors are not
            // AstronomicalException and still surface.
            return $this->calculator->maghribAt($coordinates, $date, $parameters);
        }
```

- [ ] **Step 4: Update the stale white-night comment in the existing fallback test**

The widened catch invalidates the parenthetical in `testSunsetRolloverFallsBackToMaghribOnly()`. In `tests/Hijri/GregorianToHijriConverterTest.php`, replace:

```php
        // (White-nights latitudes stay fully defined or fail with a plain
        // AstronomicalException, which still propagates by design.)
```

with:

```php
        // (A plain AstronomicalException from the full schedule takes the
        // same fallback; an undefined maghrib from maghribAt() still
        // propagates.)
```

- [ ] **Step 5: Run the tests to verify both pass**

Run:

```bash
vendor/bin/phpunit --no-coverage tests/Hijri/GregorianToHijriConverterTest.php
```

Expected: all tests pass, including the existing `testSunsetRolloverFallsBackToMaghribOnly` and `testUpperBoundaryFallsBackToMaghribOnly`.

- [ ] **Step 6: Commit**

```bash
git add src/Hijri/Services/GregorianToHijriConverter.php tests/Hijri/GregorianToHijriConverterTest.php
git commit -m "fix(hijri): fall back to maghrib for every undefined schedule"
```

---

## Task 2: Stop `NightResolver` computing discarded portions (P7)

**Finding:** `NightResolver::resolve()` computes `$portions` before it knows the mode. For `SEASONAL_TWILIGHT` and for the MSC method, `$useSeasonalSafe` is always true, so the `elseif ($portions === null)` and `else` branches are unreachable and the eagerly-computed portions are never read. The comment at lines 64-67 describes a "non-seasonal fallback path" for `SEASONAL_TWILIGHT` that cannot exist.

**Files:**
- Modify: `src/Prayer/Services/NightResolver.php:64-86`
- Test: `tests/Prayer/NightResolverTest.php` (append before `stubSolar()`)

- [ ] **Step 1: Write the failing test**

Add these imports to `tests/Prayer/NightResolverTest.php`:

```php
use AlFalaq\AlFalaq\Prayer\Contracts\HighLatitudeCorrection;
use AlFalaq\AlFalaq\Prayer\ValueObjects\NightPortions;
```

Append this method before the `stubSolar()` helper:

```php
    /**
     * The seasonal arm must not compute fallback portions it never reads.
     *
     * The counter also proves the test is not vacuous: the same resolver
     * constructed with MIDDLE_OF_NIGHT does ask for portions.
     *
     * @return void
     */
    public function testSeasonalPathNeverComputesFallbackPortions(): void
    {
        $utc = new \DateTimeZone('UTC');
        $day = new SolarTime(
            new \DateTimeImmutable('2025-06-21 12:00:00', $utc),
            new \DateTimeImmutable('2025-06-21 03:00:00', $utc),
            new \DateTimeImmutable('2025-06-21 21:00:00', $utc),
        );
        $correction = new class implements HighLatitudeCorrection {
            public bool $called = false;

            public function portions(float $fajrAngle, ?float $ishaAngle): NightPortions
            {
                $this->called = true;

                return new NightPortions(0.5, 0.5);
            }
        };
        $resolver = new NightResolver(
            self::stubSolar($day, new \DateTimeImmutable('2025-06-21 01:00:00', $utc)),
            $correction,
            new SeasonAdjustedTwilight(),
        );
        $coordinates = Coordinates::fromDegrees(3.1390, 101.6869);
        $date = CivilDate::fromYmd(2025, 6, 21);
        $base = CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters();

        $resolver->resolve(
            $coordinates,
            $date,
            0.0,
            $base->withHighLatitudeRule(HighLatitudeRule::SEASONAL_TWILIGHT),
            $day,
            36000,
        );
        self::assertFalse($correction->called, 'SEASONAL_TWILIGHT must not compute fallback portions.');

        $resolver->resolve(
            $coordinates,
            $date,
            0.0,
            $base->withHighLatitudeRule(HighLatitudeRule::MIDDLE_OF_NIGHT),
            $day,
            36000,
        );
        self::assertTrue($correction->called, 'The portions path must still compute portions.');
    }
```

- [ ] **Step 2: Run the test to verify it fails**

Run:

```bash
vendor/bin/phpunit --no-coverage --filter testSeasonalPathNeverComputesFallbackPortions tests/Prayer/NightResolverTest.php
```

Expected: **FAIL** — `SEASONAL_TWILIGHT must not compute fallback portions.` (the eager `match` called `portions()`).

- [ ] **Step 3: Reorder mode selection and make portions conditional**

In `src/Prayer/Services/NightResolver.php`, replace this block (the first part of `resolve()`, lines 64-86):

```php
        // NONE skips the portions safe caps (raw angle temps); every other
        // rule resolves fallback portions eagerly. The SEASONAL_TWILIGHT arm
        // is wired to MiddleOfNightCorrection via defaultFor and only
        // consults these portions on the non-seasonal fallback path.
        $portions = match ($parameters->highLatitudeRule) {
            HighLatitudeRule::NONE => null,
            default => $this->highLatitudeCorrection->portions(
                $parameters->fajrAngle,
                $parameters->ishaRule->angle(),
            ),
        };

        $isMsc = $parameters->method === CalculationMethod::MOON_SIGHTING_COMMITTEE;

        // Pin _set_fajr/_set_isha MSC branches (PrayerTimes.py verbatim): the
        // seasonal safe caps apply whenever the MSC method or the
        // SEASONAL_TWILIGHT rule arm is in play (the rule arm is
        // MSC-equivalent by design); the seventh-of-night temp replaces
        // the angle temp only at high latitude.
        // NOTE: the pin gates its temp branch on signed `latitude >= 55`
        // (_set_fajr and _set_isha); keep pin-verbatim here.
        $useSeasonalSafe = $isMsc || $parameters->highLatitudeRule === HighLatitudeRule::SEASONAL_TWILIGHT;
        $useSeventh = $useSeasonalSafe && $coords->latitude >= 55;
```

with:

```php
        $isMsc = $parameters->method === CalculationMethod::MOON_SIGHTING_COMMITTEE;

        // Pin _set_fajr/_set_isha MSC branches (PrayerTimes.py verbatim): the
        // seasonal safe caps apply whenever the MSC method or the
        // SEASONAL_TWILIGHT rule arm is in play (the rule arm is
        // MSC-equivalent by design); the seventh-of-night temp replaces
        // the angle temp only at high latitude.
        // NOTE: the pin gates its temp branch on signed `latitude >= 55`
        // (_set_fajr and _set_isha); keep pin-verbatim here.
        $useSeasonalSafe = $isMsc || $parameters->highLatitudeRule === HighLatitudeRule::SEASONAL_TWILIGHT;
        $useSeventh = $useSeasonalSafe && $coords->latitude >= 55;

        // Three modes: seasonal safe caps (MSC or SEASONAL_TWILIGHT),
        // NONE-raw (no cap), or fallback portions. The seasonal arm never
        // reads portions, so resolve them only for the portions mode.
        $portions = $useSeasonalSafe || $parameters->highLatitudeRule === HighLatitudeRule::NONE
            ? null
            : $this->highLatitudeCorrection->portions(
                $parameters->fajrAngle,
                $parameters->ishaRule->angle(),
            );
```

No other line changes: the downstream `if ($useSeasonalSafe) … elseif ($portions === null) … else …` branches now receive exactly the values their comments claim.

- [ ] **Step 4: Run the test file**

Run:

```bash
vendor/bin/phpunit --no-coverage tests/Prayer/NightResolverTest.php
```

Expected: all tests pass.

- [ ] **Step 5: Commit**

```bash
git add src/Prayer/Services/NightResolver.php tests/Prayer/NightResolverTest.php
git commit -m "refactor(prayer): resolve night portions only on the portions path"
```

---

## Task 3: Collapse the `CalculationParameters` withers (P16)

**Finding:** Fourteen `with*()` methods each repeat the full 15-argument constructor — roughly 400 of the file's 591 lines. A future field addition means fourteen coordinated edits, and a missed edit silently drops a field. The file is `final readonly`, so the `clone`-with pattern is unavailable; a single private `copyWith()` using `null` as the "keep current" sentinel gives one construction site while keeping full static types.

**Files:**
- Modify: `src/Prayer/ValueObjects/CalculationParameters.php:178-591`
- Test: `tests/Prayer/CalculationParametersTest.php`

- [ ] **Step 1: Write the characterization test (passes before and after)**

Add the `DataProvider` import to `tests/Prayer/CalculationParametersTest.php`:

```php
use PHPUnit\Framework\Attributes\DataProvider;
```

Append these two methods before the final `}`. The base is Umm al-Qura because it satisfies every wither's precondition (Ramadan mode is legal, `dhuha` 28 ≥ `ishraq` 15).

```php
    /**
     * Every wither changes exactly its own field and preserves the other 14.
     *
     * This is the counterweight to copyWith(): one construction site can drop
     * a field silently, so the table asserts the full property set before and
     * after and that exactly one key differs.
     *
     * @return iterable<string, array{callable(CalculationParameters): CalculationParameters, string}>
     */
    public static function witherProvider(): iterable
    {
        yield 'withMadhab' => [
            static fn (CalculationParameters $p): CalculationParameters => $p->withMadhab(Madhab::HANAFI),
            'madhab',
        ];
        yield 'withElevationM' => [
            static fn (CalculationParameters $p): CalculationParameters => $p->withElevationM(100.5),
            'elevationM',
        ];
        yield 'withAdjustments' => [
            static fn (CalculationParameters $p): CalculationParameters => $p->withAdjustments(
                PrayerAdjustments::empty()->with(Prayer::FAJR, 3),
            ),
            'adjustments',
        ];
        yield 'withMethodAdjustments' => [
            static fn (CalculationParameters $p): CalculationParameters => $p->withMethodAdjustments(
                PrayerAdjustments::empty()->with(Prayer::FAJR, -3),
            ),
            'methodAdjustments',
        ];
        yield 'withRamadan' => [
            static fn (CalculationParameters $p): CalculationParameters => $p->withRamadan(true),
            'isRamadan',
        ];
        yield 'withFajrAngle' => [
            static fn (CalculationParameters $p): CalculationParameters => $p->withFajrAngle(19.5),
            'fajrAngle',
        ];
        yield 'withIshaRule' => [
            static fn (CalculationParameters $p): CalculationParameters => $p->withIshaRule(new AngleIsha(17.0)),
            'ishaRule',
        ];
        yield 'withMaghribRule' => [
            static fn (CalculationParameters $p): CalculationParameters => $p->withMaghribRule(
                new MinutesAfterSunsetMaghrib(5),
            ),
            'maghribRule',
        ];
        yield 'withHighLatitudeRule' => [
            static fn (CalculationParameters $p): CalculationParameters => $p->withHighLatitudeRule(
                HighLatitudeRule::SEVENTH_OF_NIGHT,
            ),
            'highLatitudeRule',
        ];
        yield 'withPolarCircleRule' => [
            static fn (CalculationParameters $p): CalculationParameters => $p->withPolarCircleRule(
                PolarCircleRule::MAKKAH,
            ),
            'polarCircleRule',
        ];
        yield 'withImsakOffset' => [
            static fn (CalculationParameters $p): CalculationParameters => $p->withImsakOffset(5),
            'imsakOffsetMinutes',
        ];
        yield 'withIshraqOffset' => [
            static fn (CalculationParameters $p): CalculationParameters => $p->withIshraqOffset(10),
            'ishraqOffsetMinutes',
        ];
        yield 'withDhuhaOffset' => [
            static fn (CalculationParameters $p): CalculationParameters => $p->withDhuhaOffset(30),
            'dhuhaOffsetMinutes',
        ];
        yield 'withDhuhrOffset' => [
            static fn (CalculationParameters $p): CalculationParameters => $p->withDhuhrOffset(2),
            'dhuhrOffsetMinutes',
        ];
    }

    /**
     * @param callable(CalculationParameters): CalculationParameters $apply Wither under test.
     * @param string $field Property the wither is expected to change.
     * @return void
     */
    #[DataProvider('witherProvider')]
    public function testWitherChangesExactlyOneField(callable $apply, string $field): void
    {
        $base = CalculationMethod::UMM_AL_QURA->defaultParameters();
        $before = get_object_vars($base);
        $after = get_object_vars($apply($base));

        self::assertSame(array_keys($before), array_keys($after));

        $changed = [];

        foreach ($before as $name => $value) {
            if ($value !== $after[$name]) {
                $changed[] = $name;
            }
        }

        self::assertSame([$field], $changed);
    }
```

- [ ] **Step 2: Run the test to confirm the baseline is green**

Run:

```bash
vendor/bin/phpunit --no-coverage --filter testWitherChangesExactlyOneField tests/Prayer/CalculationParametersTest.php
```

Expected: **PASS** — 14 data-set rows. This is a characterization net for a behaviour-preserving refactor, so the red phase is deliberately skipped; the existing `CalculationParametersTest` is the behavioural suite and stays untouched.

- [ ] **Step 3: Add the single construction site**

In `src/Prayer/ValueObjects/CalculationParameters.php`, insert this method immediately after `create()` (after the closing brace on line 120) and before `assertRamadanCompatible()`:

```php
    /**
     * Copy with selected fields replaced (the only wither construction site).
     *
     * Every field is non-nullable, so `null` is a safe "keep current"
     * sentinel. If a nullable field is ever added, switch that field to an
     * explicit sentinel rather than overloading null.
     *
     * @param CalculationMethod|null $method Replacement method, null keeps current.
     * @param Madhab|null $madhab Replacement Asr school, null keeps current.
     * @param HighLatitudeRule|null $highLatitudeRule Replacement rule, null keeps current.
     * @param PolarCircleRule|null $polarCircleRule Replacement selector, null keeps current.
     * @param float|null $fajrAngle Replacement angle, null keeps current.
     * @param IshaRule|null $ishaRule Replacement rule, null keeps current.
     * @param MaghribRule|null $maghribRule Replacement rule, null keeps current.
     * @param int|null $dhuhrOffsetMinutes Replacement offset, null keeps current.
     * @param int|null $imsakOffsetMinutes Replacement offset, null keeps current.
     * @param int|null $ishraqOffsetMinutes Replacement offset, null keeps current.
     * @param int|null $dhuhaOffsetMinutes Replacement offset, null keeps current.
     * @param float|null $elevationM Replacement elevation, null keeps current.
     * @param bool|null $isRamadan Replacement flag, null keeps current.
     * @param PrayerAdjustments|null $methodAdjustments Replacement layer, null keeps current.
     * @param PrayerAdjustments|null $adjustments Replacement layer, null keeps current.
     * @return self New parameters; this instance is unchanged.
     */
    private function copyWith(
        ?CalculationMethod $method = null,
        ?Madhab $madhab = null,
        ?HighLatitudeRule $highLatitudeRule = null,
        ?PolarCircleRule $polarCircleRule = null,
        ?float $fajrAngle = null,
        ?IshaRule $ishaRule = null,
        ?MaghribRule $maghribRule = null,
        ?int $dhuhrOffsetMinutes = null,
        ?int $imsakOffsetMinutes = null,
        ?int $ishraqOffsetMinutes = null,
        ?int $dhuhaOffsetMinutes = null,
        ?float $elevationM = null,
        ?bool $isRamadan = null,
        ?PrayerAdjustments $methodAdjustments = null,
        ?PrayerAdjustments $adjustments = null,
    ): self {
        return new self(
            $method ?? $this->method,
            $madhab ?? $this->madhab,
            $highLatitudeRule ?? $this->highLatitudeRule,
            $polarCircleRule ?? $this->polarCircleRule,
            $fajrAngle ?? $this->fajrAngle,
            $ishaRule ?? $this->ishaRule,
            $maghribRule ?? $this->maghribRule,
            $dhuhrOffsetMinutes ?? $this->dhuhrOffsetMinutes,
            $imsakOffsetMinutes ?? $this->imsakOffsetMinutes,
            $ishraqOffsetMinutes ?? $this->ishraqOffsetMinutes,
            $dhuhaOffsetMinutes ?? $this->dhuhaOffsetMinutes,
            $elevationM ?? $this->elevationM,
            $isRamadan ?? $this->isRamadan,
            $methodAdjustments ?? $this->methodAdjustments,
            $adjustments ?? $this->adjustments,
        );
    }
```

- [ ] **Step 4: Replace each wither body**

Keep every method's docblock and signature unchanged. Replace only the body with the one shown:

```php
// withMadhab
return $this->copyWith(madhab: $madhab);

// withElevationM
$value = self::validateElevation($meters);

return $this->copyWith(elevationM: $value);

// withAdjustments
return $this->copyWith(adjustments: $adjustments);

// withRamadan
self::assertRamadanCompatible($this->method, $isRamadan);

return $this->copyWith(isRamadan: $isRamadan);

// withFajrAngle
$value = self::validateAngle($angle, 'fajrAngle');

return $this->copyWith(fajrAngle: $value);

// withIshaRule
$ishaRule->validate();

return $this->copyWith(ishaRule: $ishaRule);

// withMaghribRule
$maghribRule->validate();

return $this->copyWith(maghribRule: $maghribRule);

// withHighLatitudeRule
return $this->copyWith(highLatitudeRule: $highLatitudeRule);

// withPolarCircleRule
return $this->copyWith(polarCircleRule: $polarCircleRule);

// withImsakOffset
$value = self::guardOffsetMinutes($minutes, 'imsakOffsetMinutes');

return $this->copyWith(imsakOffsetMinutes: $value);

// withIshraqOffset
$value = self::guardOffsetMinutes($minutes, 'ishraqOffsetMinutes');

if ($this->dhuhaOffsetMinutes < $value) {
    throw new ValidationException(sprintf(
        'Invalid dhuhaOffsetMinutes %d: must be >= ishraqOffsetMinutes %d.',
        $this->dhuhaOffsetMinutes,
        $value,
    ));
}

return $this->copyWith(ishraqOffsetMinutes: $value);

// withDhuhaOffset
$value = self::guardOffsetMinutes($minutes, 'dhuhaOffsetMinutes');

if ($value < $this->ishraqOffsetMinutes) {
    throw new ValidationException(sprintf(
        'Invalid dhuhaOffsetMinutes %d: must be >= ishraqOffsetMinutes %d.',
        $value,
        $this->ishraqOffsetMinutes,
    ));
}

return $this->copyWith(dhuhaOffsetMinutes: $value);

// withDhuhrOffset
$value = self::guardOffsetMinutes($minutes, 'dhuhrOffsetMinutes', 0, 5);

return $this->copyWith(dhuhrOffsetMinutes: $value);

// withMethodAdjustments
return $this->copyWith(methodAdjustments: $methodAdjustments);
```

- [ ] **Step 5: Run the full parameters suite**

Run:

```bash
vendor/bin/phpunit --no-coverage tests/Prayer/CalculationParametersTest.php
```

Expected: all tests pass — the 14-row characterization test plus every existing validation, range, and immutability test.

- [ ] **Step 6: Commit**

```bash
git add src/Prayer/ValueObjects/CalculationParameters.php tests/Prayer/CalculationParametersTest.php
git commit -m "refactor(prayer): give CalculationParameters one wither construction site"
```

---

## Task 4: Delete the vestigial seams and gate the reference both ways (P10, P11)

**Finding:**
- `SharedKernel\Contracts\ClockView` is never implemented, never referenced, and contradicts the library's policy ("the domain never reads the clock; callers pass `$nowUtc` explicitly"). It exists only because the API reference now lists it (Tier 1).
- `Astro\ValueObjects\SolarPosition` has exactly one caller: `MeanSolarCalculator::position()`. `position()` has exactly one caller: its own test. The class is `@internal`, so the docblock's "direct consumers" story cannot be true.

Deleting a documented class needs the reverse of the Tier-1 gate: today `ArchApiReferenceTest` catches a class that is missing from the page, but not a page row whose class was deleted. Both directions are added here.

**Files:**
- Modify: `tests/ArchApiReferenceTest.php`
- Delete: `src/SharedKernel/Contracts/ClockView.php`
- Delete: `src/Astro/ValueObjects/SolarPosition.php`
- Modify: `src/Astro/Services/MeanSolarCalculator.php:9`, `:96-108`, `:19-27`
- Modify: `tests/Astro/MeanSolarCalculatorTest.php:35-38`
- Modify: `docs/user/api-reference.md` (delete the `ClockView` row)
- Modify: `ARCHITECTURE.md:122`

- [ ] **Step 1: Add the reverse gate**

Append this method to `tests/ArchApiReferenceTest.php` after `testEveryPublicClassIsDocumented()`:

```php
    /**
     * Every class named in the reference has a source file.
     *
     * The forward gate catches an undocumented class; this one catches a
     * documented class that no longer exists (a deleted seam left in the
     * table). Both directions are needed for the page to be a map.
     */
    public function testEveryDocumentedClassHasASourceFile(): void
    {
        $reference = (string) file_get_contents(dirname(__DIR__) . '/docs/user/api-reference.md');
        $root = dirname(__DIR__) . '/src/';
        $documented = self::documentedClasses($reference);
        $missing = [];

        foreach (array_keys($documented) as $name) {
            if (! is_file($root . str_replace('\\', '/', $name) . '.php')) {
                $missing[] = $name;
            }
        }

        self::assertGreaterThanOrEqual(
            80,
            count($documented),
            'The reference scan found too few entries; this gate would pass vacuously.',
        );

        self::assertSame([], $missing, sprintf(
            'docs/user/api-reference.md names classes with no source file: %s',
            implode(', ', $missing),
        ));
    }
```

- [ ] **Step 2: Run it to confirm the current page is consistent**

Run:

```bash
vendor/bin/phpunit --no-coverage --filter testEveryDocumentedClassHasASourceFile tests/ArchApiReferenceTest.php
```

Expected: **PASS** (84 documented names, 0 missing). This is the baseline; Step 4 makes it fail on purpose.

- [ ] **Step 3: Delete `ClockView` (class only)**

```bash
git rm src/SharedKernel/Contracts/ClockView.php
```

Leave `docs/user/api-reference.md` untouched for now: the next step runs the new gate against the stale row on purpose.

- [ ] **Step 4: Run the reverse gate to prove it catches the stale row**

Run:

```bash
vendor/bin/phpunit --no-coverage --filter testEveryDocumentedClassHasASourceFile tests/ArchApiReferenceTest.php
```

Expected: **FAIL** — `docs/user/api-reference.md names classes with no source file: SharedKernel\Contracts\ClockView`. The failure is the evidence that a deletion cannot silently leave a dead entry.

- [ ] **Step 5: Remove the stale row, then re-run the gate**

Delete this row from the SharedKernel table in `docs/user/api-reference.md`:

```markdown
| `SharedKernel\Contracts\ClockView` | `now(): DateTimeImmutable` — an optional edge port for host apps; the domain never reads the clock |
```

Update the SharedKernel directory listing in `ARCHITECTURE.md:122` from:

```markdown
  `Contracts/` (`ClockView`, `DomainException`), `Traits/`
```

to:

```markdown
  `Contracts/` (`DomainException`), `Traits/`
```

Run:

```bash
vendor/bin/phpunit --no-coverage --filter testEveryDocumentedClassHasASourceFile tests/ArchApiReferenceTest.php
```

Expected: **PASS**.

- [ ] **Step 6: Delete `SolarPosition` and `position()`**

```bash
git rm src/Astro/ValueObjects/SolarPosition.php
```

In `src/Astro/Services/MeanSolarCalculator.php`:

1. Delete `use AlFalaq\AlFalaq\Astro\ValueObjects\SolarPosition;`.
2. Delete the whole `position()` method (the docblock and the body, lines 96-108).
3. Replace the duplicated class docblock with one paragraph (this also resolves Tier-3 P13, because the duplicate lives in the lines being edited):

```php
/**
 * Mean-sun solar calculator (Meeus transcription, M1).
 *
 * Default SolarDayEngine adapter: the day-level methods (solarTime,
 * hourAngle, afternoon, isSolarDefined) are the port surface, plus
 * declination/equationOfTime for the internal solar theory.
 *
 * Safe to construct for explicit service wiring per docs/integrations.md
 * (userland otherwise goes through Moon/ or the relevant Prayer service,
 * never Astro internals).
 */
```

In `tests/Astro/MeanSolarCalculatorTest.php`, delete these three lines from `testDeclinationAndEotMatchPin()`:

```php
        $pos = $calc->position(2451545.0);
        self::assertEqualsWithDelta(-23.03251608590199, $pos->declinationDeg, 1e-9);
        self::assertEqualsWithDelta(-3.3007602707561516, $pos->equationOfTimeMinutes, 1e-9);
```

The direct `declination()` / `equationOfTime()` assertions above them remain: they are the pin-parity oracle and still exercise both formula owners.

Also update the Astro directory listing in `ARCHITECTURE.md:152` from:

```markdown
  instant-based port) and `ValueObjects/` (`SolarPosition`, `SolarTime`).
```

to:

```markdown
  instant-based port) and `ValueObjects/` (`SolarTime`).
```

- [ ] **Step 7: Prove nothing references the deleted names**

Run:

```bash
grep -rn "ClockView\|SolarPosition\|->position(" --include='*.php' src tests examples || echo "no references"
grep -rn "ClockView\|SolarPosition" --include='*.md' README.md ARCHITECTURE.md CONTEXT.md AGENTS.md docs/*.md docs/user || echo "no doc references"
```

Expected: no references in executable code or live docs. `REPO_HYGIENE_REPORT.md` and both `docs/plans/` files are historical records and are **not** edited — they may still mention the names, which is why the doc grep above scans `docs/*.md` and `docs/user/` instead of `docs/` (a whole-tree grep would always fail on this plan itself).

- [ ] **Step 8: Run the reference gate, static analysis, and the suite**

```bash
vendor/bin/phpunit --no-coverage tests/ArchApiReferenceTest.php tests/Astro/MeanSolarCalculatorTest.php
vendor/bin/phpstan analyse --memory-limit=1G
vendor/bin/phpunit --no-coverage
```

Expected: all green. The public-class count drops from 82 to 81, still above the gate's 80 floor.

- [ ] **Step 9: Commit**

```bash
git add -A src/SharedKernel/Contracts/ClockView.php src/Astro/ValueObjects/SolarPosition.php \
  src/Astro/Services/MeanSolarCalculator.php tests/Astro/MeanSolarCalculatorTest.php \
  tests/ArchApiReferenceTest.php docs/user/api-reference.md ARCHITECTURE.md
git commit -m "refactor: delete the vestigial ClockView and SolarPosition seams"
```

---

## Task 5: Machine-check the six unenforced conventions (P21)

**Finding:** Six `AGENTS.md` rules hold today only by convention:
1. `Contracts/` contains interfaces only.
2. Only service contracts carry the `*Interface` suffix (and they must keep it).
3. One named class-like per file.
4. `ValueObjects/` are `final readonly` (stateless static holders aside).
5. `Traits/` expose only `private` helpers (the two Hijri contract traits aside).
6. `Services/` expose `default()` (dependency-bound services aside).

Two of the six need a code change before the gate can be honest: `NextPrayerResolver` and `TimetableService` have no `default()`. Both are zero-argument constructions, so the factory is trivial.

**Files:**
- Modify: `tests/ArchSupport.php` (tokenizer helper)
- Modify: `tests/ArchShapeTest.php` (six tests + self-test + allow-lists)
- Modify: `src/Prayer/Services/NextPrayerResolver.php`
- Modify: `src/Prayer/Services/TimetableService.php`
- Modify: `AGENTS.md:80-83`

- [ ] **Step 1: Add the tokenizer helper to `ArchSupport`**

Insert these two methods at the end of `tests/ArchSupport.php`, before the final `}`:

```php
    /**
     * Count named class/interface/trait/enum declarations in one file.
     *
     * @param string $path PHP file to scan.
     * @return int Named class-like declarations in the file.
     */
    protected static function classLikeDeclarationCount(string $path): int
    {
        return self::countClassLikeDeclarations((string) file_get_contents($path));
    }

    /**
     * Count named class-like declarations in a code string.
     *
     * Anonymous classes (`new class`) and `::class` constants also emit
     * T_CLASS, so the preceding significant token decides whether the token
     * is a declaration.
     *
     * @param string $code PHP source, including the opening tag.
     * @return int Named class-like declarations.
     */
    protected static function countClassLikeDeclarations(string $code): int
    {
        $significant = [];

        foreach (token_get_all($code) as $token) {
            if (is_array($token)) {
                if (in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }

                $significant[] = $token[0];
            } else {
                $significant[] = $token;
            }
        }

        $count = 0;

        foreach ($significant as $index => $id) {
            if (in_array($id, [T_INTERFACE, T_TRAIT, T_ENUM], true)) {
                $count++;

                continue;
            }

            if ($id !== T_CLASS) {
                continue;
            }

            $previous = $significant[$index - 1] ?? null;

            if ($previous === T_DOUBLE_COLON || $previous === T_NEW) {
                continue;
            }

            $count++;
        }

        return $count;
    }
```

- [ ] **Step 2: Add the six gates and their allow-lists to `ArchShapeTest`**

Insert these constants and tests into `tests/ArchShapeTest.php`, before the final `}`:

```php
    /**
     * Service contracts are the only interfaces allowed an `Interface` suffix.
     *
     * The list is exact in both directions: stripping a suffix and adding one
     * to a driven port both fail.
     */
    private const SERVICE_CONTRACTS = [
        'AlFalaq\\AlFalaq\\Prayer\\Contracts\\NextPrayerResolverInterface',
        'AlFalaq\\AlFalaq\\Prayer\\Contracts\\PrayerTimesCalculatorInterface',
        'AlFalaq\\AlFalaq\\Prayer\\Contracts\\TimetableServiceInterface',
    ];

    /**
     * Services whose construction needs caller data; a zero-arg default()
     * cannot exist. The value names the factory callers use instead.
     */
    private const DEPENDENCY_BOUND_SERVICES = [
        'AlFalaq\\AlFalaq\\Prayer\\Services\\NightResolver' => 'PrayerTimesCalculator::default()',
        'AlFalaq\\AlFalaq\\Prayer\\Services\\SunnahCalculator' => 'SunnahCalculator::fromCalculator()',
    ];

    /**
     * Traits that contribute public contract methods by design.
     */
    private const CONTRACT_TRAITS = [
        'AlFalaq\\AlFalaq\\Hijri\\Traits\\LooksUpMonthStarts',
        'AlFalaq\\AlFalaq\\Hijri\\Traits\\ShiftsHijriDays',
    ];

    public function testContractsContainInterfacesOnly(): void
    {
        $checked = 0;
        $root = str_replace('\\', '/', dirname(__DIR__)) . '/src/';

        foreach (self::srcPhpFiles() as $path) {
            $normalized = str_replace('\\', '/', $path);

            if (! str_contains($normalized, '/Contracts/')) {
                continue;
            }

            $checked++;
            $relative = substr($normalized, strlen($root), -4);
            $fqcn = 'AlFalaq\\AlFalaq\\' . str_replace('/', '\\', $relative);

            self::assertTrue(
                interface_exists($fqcn),
                sprintf('%s must declare an interface; Contracts/ holds ports only.', $fqcn),
            );
        }

        self::assertGreaterThanOrEqual(15, $checked, 'Expected every Contracts/ file to be scanned.');
    }

    public function testOnlyServiceContractsCarryTheInterfaceSuffix(): void
    {
        $found = [];

        foreach (self::srcClasses() as $class) {
            $reflection = new \ReflectionClass($class);

            if (! $reflection->isInterface() || ! str_contains($class, '\\Contracts\\')) {
                continue;
            }

            if (str_ends_with($reflection->getShortName(), 'Interface')) {
                $found[] = $class;
            }
        }

        sort($found);
        $expected = self::SERVICE_CONTRACTS;
        sort($expected);

        self::assertSame($expected, $found);
    }

    public function testOneClassLikePerFile(): void
    {
        $violations = [];

        foreach (self::srcPhpFiles() as $path) {
            $count = self::classLikeDeclarationCount($path);

            if ($count !== 1) {
                $violations[] = sprintf('%s declares %d named class-likes; expected exactly 1.', $path, $count);
            }
        }

        self::assertSame([], $violations);
    }

    public function testClassLikeCounterIgnoresAnonymousClassesAndClassConstants(): void
    {
        $code = <<<'PHP'
<?php
namespace Probe;

enum ProbeEnum { case A; }

interface ProbeInterface {}

trait ProbeTrait {}

final class Probe
{
    public function make(): object
    {
        return new class {};
    }

    public function name(): string
    {
        return Probe::class;
    }
}
PHP;

        self::assertSame(4, self::countClassLikeDeclarations($code));
    }

    public function testValueObjectsAreReadonly(): void
    {
        $checked = 0;
        $readonly = 0;

        foreach (self::srcClasses() as $class) {
            if (! str_contains($class, '\\ValueObjects\\')) {
                continue;
            }

            $checked++;
            $reflection = new \ReflectionClass($class);
            $instanceProperties = array_filter(
                $reflection->getProperties(),
                static fn (\ReflectionProperty $property): bool => ! $property->isStatic(),
            );

            if ($reflection->isReadOnly()) {
                $readonly++;
            }

            self::assertTrue(
                $reflection->isReadOnly() || $instanceProperties === [],
                sprintf(
                    'Value object %s must be readonly; only stateless static holders may stay plain final.',
                    $class,
                ),
            );
        }

        self::assertGreaterThanOrEqual(15, $checked, 'Expected value objects to exist.');
        self::assertGreaterThanOrEqual(
            15,
            $readonly,
            'The readonly check must not pass only through the static-holder carve-out.',
        );
    }

    public function testTraitsExposeOnlyPrivateHelpers(): void
    {
        $checked = 0;
        $violations = [];

        foreach (self::srcClasses() as $class) {
            $reflection = new \ReflectionClass($class);

            if (! $reflection->isTrait()) {
                continue;
            }

            $checked++;
            $contractTrait = in_array($class, self::CONTRACT_TRAITS, true);

            foreach ($reflection->getMethods() as $method) {
                if ($method->isPrivate() || $contractTrait) {
                    continue;
                }

                $violations[] = sprintf(
                    '%s::%s() must be private (or the trait must join CONTRACT_TRAITS with a reason).',
                    $class,
                    $method->getName(),
                );
            }
        }

        self::assertGreaterThanOrEqual(7, $checked, 'Expected traits to exist.');
        self::assertSame([], $violations);
    }

    public function testServicesExposeDefaultFactory(): void
    {
        $checked = 0;
        $violations = [];

        foreach (self::srcClasses() as $class) {
            if (! str_contains($class, '\\Services\\') || str_starts_with($class, 'AlFalaq\\AlFalaq\\Astro\\')) {
                continue;
            }

            $checked++;
            $reflection = new \ReflectionClass($class);

            if ($reflection->hasMethod('default')) {
                $default = $reflection->getMethod('default');

                if ($default->isPublic() && $default->isStatic()) {
                    continue;
                }
            }

            if (array_key_exists($class, self::DEPENDENCY_BOUND_SERVICES)) {
                continue;
            }

            $violations[] = sprintf(
                '%s must expose a public static default() (or join DEPENDENCY_BOUND_SERVICES).',
                $class,
            );
        }

        self::assertGreaterThanOrEqual(9, $checked, 'Expected services to exist.');
        self::assertSame([], $violations);
    }

    public function testConventionAllowListsDoNotRot(): void
    {
        foreach (array_keys(self::DEPENDENCY_BOUND_SERVICES) as $class) {
            self::assertTrue(
                class_exists($class),
                sprintf('DEPENDENCY_BOUND_SERVICES entry %s no longer exists.', $class),
            );
        }

        foreach (self::CONTRACT_TRAITS as $trait) {
            self::assertTrue(
                trait_exists($trait),
                sprintf('CONTRACT_TRAITS entry %s no longer exists.', $trait),
            );
        }

        foreach (self::SERVICE_CONTRACTS as $interface) {
            self::assertTrue(
                interface_exists($interface),
                sprintf('SERVICE_CONTRACTS entry %s no longer exists.', $interface),
            );
        }
    }
```

- [ ] **Step 3: Run the arch group to see the two expected failures**

Run:

```bash
composer arch
```

Expected: **FAIL** in `testServicesExposeDefaultFactory` — the violations list contains `NextPrayerResolver` and `TimetableService`. The other seven new tests pass: the two trait exceptions are allow-listed, `Kaaba`/`Minutes` pass the static-holder carve-out, and every file declares one class-like.

- [ ] **Step 4: Add the two trivial factories**

In `src/Prayer/Services/NextPrayerResolver.php`, insert before `resolve()`:

```php
    /**
     * Build the stateless resolver.
     *
     * @return self Ready to resolve the next prayer.
     */
    public static function default(): self
    {
        return new self();
    }
```

In `src/Prayer/Services/TimetableService.php`, insert before `monthly()`:

```php
    /**
     * Build on the default prayer pipeline.
     *
     * @return self Ready to build monthly timetables.
     */
    public static function default(): self
    {
        return new self();
    }
```

- [ ] **Step 5: Run the arch group to verify all six gates pass**

Run:

```bash
composer arch
```

Expected: **PASS** — all four arch test classes, all green.

- [ ] **Step 6: Align the `AGENTS.md` wording with the gates**

Replace `AGENTS.md:80-83`:

```markdown
- `Services/`: `final`, explicit ctor injection, `default()` factory, single
  command method. `Strategies/`: `final`, one `Contracts/` interface each.
  `ValueObjects/`: `final readonly`, validated ctor, UTC math only.
  `Traits/` (named `Traits/`, not `Concerns/`): stateless, `private` helpers.
```

with:

```markdown
- `Services/`: `final`, explicit ctor injection, `default()` factory, single
  command method. Construction that needs caller data is the documented
  exception: `NightResolver` is wired by `PrayerTimesCalculator::default()`,
  `SunnahCalculator` uses `fromCalculator()`. `Strategies/`: `final`, one
  `Contracts/` interface each. `ValueObjects/`: `final readonly`, validated
  ctor, UTC math only (the stateless static holders `Kaaba` and `Minutes`
  stay plain `final`). `Traits/` (named `Traits/`, not `Concerns/`):
  stateless, `private` helpers (the two Hijri calendar traits contribute
  public `HijriCalendar` methods by design).
```

This supersedes the wording half of Tier-3 P17/P18; the code already matches.

- [ ] **Step 7: Run the docblock gate and the full suite**

```bash
composer arch
vendor/bin/phpstan analyse --memory-limit=1G
vendor/bin/phpunit --no-coverage
```

Expected: all green (the new `default()` methods carry full docblocks).

- [ ] **Step 8: Commit**

```bash
git add tests/ArchSupport.php tests/ArchShapeTest.php \
  src/Prayer/Services/NextPrayerResolver.php src/Prayer/Services/TimetableService.php AGENTS.md
git commit -m "test(arch): enforce the remaining AGENTS.md conventions"
```

---

## Task 6: Make `docs/integrations.md` the executable wiring source (P19 + action 13)

**Finding:** `ARCHITECTURE.md` says `docs/integrations.md` "shows plain-`new` construction for every feature" and that recipes "must be covered by `tests/Wiring/`". In reality the page covers Prayer and Qibla only; Moon, Hijri, Timetable, and Sunnah have no recipe and no wiring test. The existing wiring tests re-implement the snippets instead of executing them, so the doc can drift silently.

The fix is one source of truth: every fenced PHP block in the page is self-contained, and the test extracts and evaluates those exact blocks.

**Files:**
- Modify: `docs/integrations.md` (full replacement)
- Modify: `tests/Wiring/CalculatorWiringTest.php`

- [ ] **Step 1: Write the doc-parsing test (red: sections missing)**

In `tests/Wiring/CalculatorWiringTest.php`, add these imports:

```php
use AlFalaq\AlFalaq\Hijri\ValueObjects\HijriDate;
use AlFalaq\AlFalaq\Moon\ValueObjects\CrescentGeometry;
use AlFalaq\AlFalaq\Prayer\ValueObjects\NextPrayer;
use AlFalaq\AlFalaq\Prayer\ValueObjects\PrayerSchedule;
use AlFalaq\AlFalaq\Prayer\ValueObjects\SunnahTimes;
use AlFalaq\AlFalaq\Qibla\ValueObjects\QiblaResult;
```

Add this constant and methods before the final `}`:

```php
    /**
     * Section heading → variables the snippet must define and their types.
     *
     * @var array<string, array<string, class-string|'array'>>
     */
    private const SECTION_EXPECTATIONS = [
        'Prayer times' => [
            'calculator' => PrayerTimesCalculator::class,
            'schedule' => PrayerSchedule::class,
        ],
        'Prayer times — explicit strategy wiring' => [
            'calculator' => PrayerTimesCalculator::class,
        ],
        'Qibla' => [
            'precise' => QiblaCalculator::class,
            'result' => QiblaResult::class,
        ],
        'Timetable' => [
            'month' => 'array',
        ],
        'Sunnah' => [
            'sunnah' => SunnahTimes::class,
        ],
        'Crescent visibility' => [
            'geometry' => CrescentGeometry::class,
            'verdicts' => 'array',
        ],
        'Hijri conversion' => [
            'hijri' => HijriDate::class,
        ],
        'Clock input (edges only)' => [
            'next' => NextPrayer::class,
        ],
    ];

    /**
     * Every PHP block in docs/integrations.md runs exactly as written.
     *
     * The doc is the source of truth for wiring: extracting and executing its
     * fenced blocks means a renamed constructor, a changed signature, or an
     * unqualified global class in a snippet fails the build. Section headings
     * are the anchors, so moving a snippet to the wrong section fails too.
     */
    public function testDocumentedSnippetsRunAsWritten(): void
    {
        $markdown = (string) file_get_contents(dirname(__DIR__, 2) . '/docs/integrations.md');
        $snippets = self::phpSnippets($markdown);

        self::assertGreaterThanOrEqual(
            count(self::SECTION_EXPECTATIONS),
            count($snippets),
            'Expected at least one PHP snippet per documented wiring recipe.',
        );

        $bySection = [];

        foreach ($snippets as [$section, $code]) {
            $bySection[$section] = array_merge($bySection[$section] ?? [], self::runSnippet($code));
        }

        foreach (self::SECTION_EXPECTATIONS as $section => $expectations) {
            self::assertArrayHasKey($section, $bySection, sprintf('Missing documented section: %s', $section));

            foreach ($expectations as $variable => $type) {
                self::assertArrayHasKey(
                    $variable,
                    $bySection[$section],
                    sprintf('Section "%s" must define $%s.', $section, $variable),
                );

                if ($type === 'array') {
                    self::assertIsArray($bySection[$section][$variable]);
                } else {
                    self::assertInstanceOf($type, $bySection[$section][$variable]);
                }
            }
        }
    }

    /**
     * Fenced PHP blocks paired with their preceding `## ` heading.
     *
     * @param string $markdown Full docs/integrations.md contents.
     * @return list<array{string, string}> Heading + code pairs, in file order.
     */
    private static function phpSnippets(string $markdown): array
    {
        $snippets = [];
        $section = '';
        $buffer = null;
        $lines = preg_split('/\R/', $markdown);

        foreach ($lines === false ? [] : $lines as $line) {
            if (str_starts_with($line, '## ')) {
                $section = substr($line, 3);

                continue;
            }

            if (str_starts_with($line, '```php')) {
                $buffer = '';

                continue;
            }

            if ($buffer !== null && str_starts_with($line, '```')) {
                $snippets[] = [$section, $buffer];
                $buffer = null;

                continue;
            }

            if ($buffer !== null) {
                $buffer .= $line . "\n";
            }
        }

        return $snippets;
    }

    /**
     * Evaluate one snippet and return the variables it defined.
     *
     * Snippets are self-contained: every class is imported or fully
     * qualified, and every input is constructed in the block.
     *
     * The namespace prefix is load-bearing: eval() otherwise runs in the
     * global namespace, where unqualified globals (e.g. DateTimeImmutable)
     * resolve and hide the drift this test guards against.
     *
     * @param string $code PHP code without the opening tag.
     * @return array<string, mixed> Variables in scope after execution.
     */
    private static function runSnippet(string $code): array
    {
        eval("namespace AlFalaq\\AlFalaq\\Tests\\Wiring;\n\n" . $code);

        return get_defined_vars();
    }
```

- [ ] **Step 2: Run it to verify it fails**

Run:

```bash
vendor/bin/phpunit --no-coverage --filter testDocumentedSnippetsRunAsWritten tests/Wiring/CalculatorWiringTest.php
```

Expected: **FAIL** at the snippet-count guard — `Expected at least one PHP snippet per documented wiring recipe. Failed asserting that 4 is equal to 8 or is greater than 8.` The current page has four PHP blocks under three headings (Prayer basic, Prayer advanced, Qibla, Clock); the new recipes are not there yet. (Verified against the current doc.)

- [ ] **Step 3: Replace `docs/integrations.md`**

Write the complete file:

````markdown
# integrations — wiring this library into any PHP app

There are no service providers, bundles, or config files. Every service is
constructed with plain `new` or a named factory, in any app — framework or
not.

Every PHP block below is self-contained and is executed **verbatim** by
`tests/Wiring/CalculatorWiringTest.php`. A renamed constructor, a changed
signature, or an unqualified global class in a snippet fails the build.

## Prayer times

```php
use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Services\PrayerTimesCalculator;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

$calculator = PrayerTimesCalculator::default();
$params = CalculationMethod::SINGAPORE->defaultParameters();
$schedule = $calculator->calculate(
    Coordinates::fromDegrees(3.1390, 101.6869),
    CivilDate::fromYmd(2025, 3, 1),
    $params,
);
```

## Prayer times — explicit strategy wiring

Inject strategies explicitly instead of `default()`:

```php
use AlFalaq\AlFalaq\Astro\Services\MeanSolarCalculator;
use AlFalaq\AlFalaq\Prayer\Services\PrayerTimesCalculator;
use AlFalaq\AlFalaq\Prayer\Strategies\MiddleOfNightCorrection;
use AlFalaq\AlFalaq\Prayer\Strategies\NearestLatitudeHandler;
use AlFalaq\AlFalaq\Prayer\Strategies\SeasonAdjustedTwilight;

$calculator = new PrayerTimesCalculator(
    new MeanSolarCalculator(),
    new MiddleOfNightCorrection(),
    new NearestLatitudeHandler(),
    new SeasonAdjustedTwilight(),
);
```

`MeanSolarCalculator` is the stable default `SolarDayEngine` adapter: naming
it here for explicit wiring is supported (implement the port instead for test
doubles).

## Qibla

```php
use AlFalaq\AlFalaq\Qibla\Services\QiblaCalculator;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

$spherical = QiblaCalculator::default(); // spherical earth
$precise = QiblaCalculator::wgs84();     // WGS84/Karney

$result = $spherical->calculate(Coordinates::fromDegrees(3.1390, 101.6869));
$preciseResult = $precise->calculate(Coordinates::fromDegrees(3.1390, 101.6869));
```

## Timetable

```php
use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Services\TimetableService;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

$month = TimetableService::default()->monthly(
    Coordinates::fromDegrees(3.1390, 101.6869),
    2025,
    3,
    CalculationMethod::SINGAPORE->defaultParameters(),
);
```

`monthly()` returns one UTC `PrayerSchedule` per calendar day, in order.
There is no yearly helper by design: loop `monthly()`.

## Sunnah

```php
use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Services\PrayerTimesCalculator;
use AlFalaq\AlFalaq\Prayer\Services\SunnahCalculator;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

$sunnah = SunnahCalculator::fromCalculator(
    PrayerTimesCalculator::default(),
    Coordinates::fromDegrees(3.1390, 101.6869),
    CivilDate::fromYmd(2025, 3, 1),
    CalculationMethod::SINGAPORE->defaultParameters(),
)->calculate();
```

The night runs tonight's maghrib → tomorrow's fajr. `forZone()` converts the
markers for display; the values are UTC.

## Crescent visibility

```php
use AlFalaq\AlFalaq\Moon\Services\CrescentCalculator;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

$crescent = CrescentCalculator::default();
$coordinates = Coordinates::fromDegrees(3.1390, 101.6869);
$date = CivilDate::fromYmd(2025, 2, 28);

$geometry = $crescent->atSunset($coordinates, $date);
$verdicts = $crescent->evaluate($coordinates, $date);
```

`$verdicts` carries one `CrescentVerdict` per criterion under the keys
`yallop`, `odeh`, `mabims1992`, and `neomabims2021`. Pass
`evaluateGeometry($geometry)` to reuse geometry you already hold.

## Hijri conversion

```php
use AlFalaq\AlFalaq\Hijri\Services\GregorianToHijriConverter;
use AlFalaq\AlFalaq\Hijri\Strategies\UmmAlQuraCalendar;
use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

$converter = GregorianToHijriConverter::default();

// The caller's own zone decides the civil date (a local wall-clock date):
$hijri = $converter->convert(
    new \DateTimeImmutable('2025-03-01 12:00:00', new \DateTimeZone('Asia/Kuala_Lumpur')),
    new UmmAlQuraCalendar(),
);

// Or roll the UTC date at the observer's maghrib:
$hijriAtSunset = $converter->convert(
    new \DateTimeImmutable('2025-03-01 12:00:00', new \DateTimeZone('UTC')),
    new UmmAlQuraCalendar(),
    Coordinates::fromDegrees(3.1390, 101.6869),
    CalculationMethod::SINGAPORE->defaultParameters(),
    changeAtSunset: true,
);
```

Other calendars are `TabularCalendar()` and
`MabimsCalendar(MabimsCountry::MY)`. An optional `OffsetStore` applies
official day corrections after conversion.

## Clock input (edges only)

The domain never reads the clock. Callers pass time explicitly:

```php
use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Services\NextPrayerResolver;
use AlFalaq\AlFalaq\Prayer\Services\PrayerTimesCalculator;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

$calculator = PrayerTimesCalculator::default();
$params = CalculationMethod::SINGAPORE->defaultParameters();
$coordinates = Coordinates::fromDegrees(3.1390, 101.6869);
$today = $calculator->calculate($coordinates, CivilDate::fromYmd(2025, 3, 1), $params);
$tomorrow = $calculator->calculate($coordinates, CivilDate::fromYmd(2025, 3, 2), $params);
$nowUtc = new \DateTimeImmutable('2025-03-01 12:00:00', new \DateTimeZone('UTC'));

$next = NextPrayerResolver::default()->resolve($today, $tomorrow, $nowUtc);
```

If the host app uses `psr/clock` (suggested, never required), call
`$clock->now()` at the edge and pass the result in — the library never
type-hints it.

## Output shapes (JSON)

`PrayerSchedule::all()` keys `['imsak','fajr','sunrise','syuruk','ishraq','dhuha','dhuhr','asr','maghrib','isha']`
in this order, UTC `DateTimeImmutable`; `toArray(): array<string,string>` maps
same keys to `Y-m-d\TH:i:s+00:00`; `DisplaySchedule::toArray()` same keys in
display zone; `json_encode($sched->toArray())` for consumers; no other keys
without a minor bump.
````

- [ ] **Step 4: Run the parser test to verify every block executes**

Run:

```bash
vendor/bin/phpunit --no-coverage --filter testDocumentedSnippetsRunAsWritten tests/Wiring/CalculatorWiringTest.php
```

Expected: **PASS** — 9 `##` headings, 8 of them carrying exactly one snippet (the ninth is the prose-only "Output shapes (JSON)"), each snippet evaluated with no error and every expected variable defined with the right type. Verified ahead of time against the current code with `TimetableService::default()`/`NextPrayerResolver::default()` substituted by `new`: all 8 blocks run and all 8 type checks pass under the `AlFalaq\AlFalaq\Tests\Wiring` prefix used by `runSnippet()`, so Task 5's factories are the only prerequisite.

- [ ] **Step 5: Delete the re-implemented snippet tests**

Delete these four methods from `tests/Wiring/CalculatorWiringTest.php`; each is now literally the doc block executed by the parser:

- `testPrayerSnippetCalculatesTenUtcMarkers`
- `testAdvancedSnippetConstructsWorkingCalculator`
- `testQiblaSnippetCalculatesBothModels`
- `testClockSnippetResolvesNextPrayer`

Keep `testOutputShapesMatchDocumentedSchema()` — it checks the prose section's key order and ISO formats, not a snippet. Keep the factory/contract/strategy tests; they assert wiring properties the doc does not show.

Also delete any import that becomes unused. Most stay: `testDefaultForMapsRulesToStrategies()` still uses `MiddleOfNightCorrection`, `NearestLatitudeHandler`, `SeasonAdjustedTwilight`, `MakkahHandler`, `SeventhOfNightCorrection`, `AngleIsha`, `MinutesAfterSunsetMaghrib`, `CalculationParameters`, `PrayerAdjustments`, `Madhab`, `HighLatitudeRule`, `PolarCircleRule`, and `CalculationMethod`; the two Qibla factory tests still use `SphericalCalculator` and `Wgs84KarneyCalculator`. The only import that becomes unused is:

```php
use AlFalaq\AlFalaq\Prayer\Enums\Prayer;
```

Run `composer cs` after the edit; php-cs-fixer's `no_unused_imports` is authoritative.

- [ ] **Step 6: Run the wiring suite and the full suite**

```bash
vendor/bin/phpunit --no-coverage tests/Wiring
vendor/bin/phpunit --no-coverage
```

Expected: all green.

- [ ] **Step 7: Commit**

```bash
git add docs/integrations.md tests/Wiring/CalculatorWiringTest.php
git commit -m "docs(integrations): execute every wiring recipe instead of duplicating it"
```

---

## Task 7: Documentation sync and final verification

**Files:**
- Modify: `docs/user/api-reference.md` (two service rows gain `default(): self`)
- Modify: `ARCHITECTURE.md` (gate inventory + `Wiring` wording)
- Modify: `CHANGELOG.md` (`[Unreleased]`)
- Verify: `AGENTS.md` (done in Task 5), `CONTEXT.md` (no structural change)

- [ ] **Step 1: Update the two service rows**

In `docs/user/api-reference.md`, replace:

```markdown
| `Prayer\Services\TimetableService` | `monthly(Coordinates, int $year, int $month, CalculationParameters): list<PrayerSchedule>` |
| `Prayer\Services\NextPrayerResolver` | `resolve(PrayerSchedule $today, PrayerSchedule $tomorrow, DateTimeImmutable $nowUtc): NextPrayer` (`$nowUtc` must be UTC) |
```

with:

```markdown
| `Prayer\Services\TimetableService` | `default(): self`; `monthly(Coordinates, int $year, int $month, CalculationParameters): list<PrayerSchedule>` |
| `Prayer\Services\NextPrayerResolver` | `default(): self`; `resolve(PrayerSchedule $today, PrayerSchedule $tomorrow, DateTimeImmutable $nowUtc): NextPrayer` (`$nowUtc` must be UTC) |
```

- [ ] **Step 2: Update the machine-checked-shape paragraph**

In `ARCHITECTURE.md`, replace the "Machine-checked shape" bullet:

```markdown
- **Machine-checked shape.** The arch groups (`composer arch`) enforce `final` services and
  value objects, `*Exception` naming, no `Util/` namespace, mandatory
  docblocks (`@param $name` / `@return` on all public API), and the
  layering rules above. `composer arch` runs these in isolation.
```

with:

```markdown
- **Machine-checked shape.** The arch groups (`composer arch`) enforce `final`
  services and `final readonly` value objects (stateless static holders
  aside), `*Exception` naming, no `Util/` namespace, `Contracts/` holding
  interfaces only and the exact `*Interface` suffix set, one named class per
  file, private trait helpers (the two Hijri contract traits aside),
  `default()` service factories, mandatory docblocks (`@param $name` /
  `@return` on all public API), and the layering rules above. The API
  reference is checked in both directions. `composer arch` runs these in
  isolation.
```

Also update `ARCHITECTURE.md:158-159`. Replace:

```markdown
  sweeps), `Wiring/` (smoke tests for every `docs/integrations.md`
  snippet), `Goldens/` (pinned-Python parity vectors, 25 CSVs;
```

with:

```markdown
  sweeps), `Wiring/` (extracts and executes every PHP block in
  `docs/integrations.md`, so the recipes cannot drift from the code),
  `Goldens/` (pinned-Python parity vectors, 25 CSVs;
```

- [ ] **Step 3: Add the changelog entries**

`CHANGELOG.md`'s `## [Unreleased]` already has `### Changed` and `### Fixed`, and has no `### Added` or `### Removed`. Make four insertions — do **not** paste a second `### Changed`/`### Fixed` heading, which would duplicate the sections.

1. Immediately after the `## [Unreleased]` line (blank line, then before the existing `### Changed`), add the new section:

```markdown
### Added

- `tests/ArchShapeTest.php` now enforces the remaining `AGENTS.md`
  conventions: `Contracts/` interfaces-only, the exact `*Interface` suffix
  set, one named class per file, `readonly` value objects (stateless static
  holders aside), private trait helpers, and `default()` service factories.
- `docs/integrations.md` now has runnable recipes for Timetable, Sunnah,
  Crescent visibility, and Hijri conversion. `tests/Wiring/` extracts and
  executes every fenced PHP block, so the recipes cannot drift.
```

2. Insert these four bullets at the **top** of the existing `### Changed` list (above the solar-triplet entry):

```markdown
- `GregorianToHijriConverter` now takes the `maghribAt()` fallback for every
  astronomical "schedule undefined" case (`AstronomicalException`), not only
  `UndefinedScheduleException`; validation errors still surface.
- `NightResolver` no longer computes high-latitude fallback portions on the
  seasonal path (MSC / `SEASONAL_TWILIGHT`), where they were discarded.
- `CalculationParameters` withers share one private `copyWith()` construction
  site, so a future field cannot be dropped by a missed wither.
- `NextPrayerResolver` and `TimetableService` expose `default()` factories.
```

3. Between the end of the `### Changed` list and the existing `### Fixed` heading, add:

```markdown
### Removed

- `SharedKernel\Contracts\ClockView` — never implemented or referenced; the
  library passes `$nowUtc` explicitly.
- `Astro\ValueObjects\SolarPosition` and `MeanSolarCalculator::position()` —
  the only caller was `position()`'s own test, and the class is `@internal`.
```

4. Insert this bullet at the **top** of the existing `### Fixed` list (above the bench-harness entry):

```markdown
- The public-API reference is checked in both directions: a documented class
  that no longer exists fails the build.
```

Resulting order under `## [Unreleased]`: `### Added`, `### Changed`, `### Removed`, `### Fixed` — Keep a Changelog order, one heading each.

- [ ] **Step 4: Run the complete gate**

Run:

```bash
composer ci
```

Expected: `lint` pass, `cs` 0 findings, `stan` no errors, `test` coverage ≥ 95 (expect ≈ 97%).

Then:

```bash
composer arch
```

Expected: all arch groups green.

- [ ] **Step 5: Verify no stale references and no frozen-file changes**

Run:

```bash
git status --short
git diff --stat main -- docs/user/v0.1.0 docs/user/v0.1.1
grep -rn "ClockView\|SolarPosition" --include='*.php' src tests examples || echo "no code references"
```

Expected: the diff for the two snapshot directories is empty; the code-reference grep finds nothing.

- [ ] **Step 6: Commit**

```bash
git add docs/user/api-reference.md ARCHITECTURE.md CHANGELOG.md
git commit -m "docs: sync the tier-2 changes"
```

---

## Final Verification

- [ ] `composer ci` green (lint, cs, PHPStan max, coverage ≥ 95).
- [ ] `composer arch` green (all four arch test classes).
- [ ] `vendor/bin/phpunit --no-coverage` reports ≥ 466 tests and 0 failures (443 baseline + 27 new − 4 deleted). The new tests add 27 cases (2 Hijri, 1 night, 14 wither data sets, 1 reverse reference, 8 shape, 1 wiring), while Task 6 deletes 4 snippet tests — net +23.
- [ ] `grep -rn "ClockView\|SolarPosition\|->position(" src tests examples README.md ARCHITECTURE.md CONTEXT.md AGENTS.md docs/*.md docs/user` returns nothing.
- [ ] `git diff --stat main -- docs/user/v0.1.0 docs/user/v0.1.1` is empty.
- [ ] `composer mutation` is **not** required for this PR; the MSI floor is unchanged and the deleted seams only remove mutants. Run it if the PR touches anything beyond the files listed here.

---

## Out of Scope

- **Tier 3** items remain: dual changelog (P20), npm advisories (P22), blank-line gaps (P14), inline FQCNs (P15), direct unit tests for `Minutes`/`Angle`/`Coordinates`/`CivilDate` (P18 in the report's action list), the Asr-clamp comment, and the `docs/` vs `docs/user/` scoping decision (P24).
- **P12** (`MeanSolarCalculator::declination()`/`equationOfTime()` public on an `@internal` class) stays as-is: they are the pin-parity oracle and are used internally; only `position()`/`SolarPosition` are removed.
- **P13** is fixed as a drive-by in Task 4 (the duplicated paragraph is in the lines being edited). **P17/P18** wording is fixed as part of Task 5 because the new gates encode the real rule; no further doc work is needed for those two.
- `REPO_HYGIENE_REPORT.md` and `docs/plans/2026-10-22-tier1-hygiene.md` are historical records; do not rewrite their mentions of deleted classes.
- No new runtime dependency, no framework adapter, no golden-vector change.
