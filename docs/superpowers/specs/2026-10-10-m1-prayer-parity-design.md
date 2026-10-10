# M1 Prayer Parity — Design (Approach A: staged clean-derivation)

- Date: 2026-10-10
- Pin: `PYTHON_PIN=a7baa8b202790d3bc84c47a610ac763be6c0b7e7` (nexusnv/al-falak)
- PRD: `Technical-PRD.md` §5.2 + §5.2.1, §6, §19 (M1 line); Appendices A–C normative.
- Status: M0 skeleton landed; M1 blocked until Appendix A frozen + checksummed.

## Decisions locked in brainstorming

1. Source of truth: pin available at `a7baa8b`; dual-enter Appendix A from it, generate goldens via `tools/export_goldens.py --pin <sha> --path <local>`.
2. Slicing: staged `freeze → engine → core → edge → timetable` (each green `composer ci` before next).
3. Engine fidelity: clean Meeus re-derive (PHP-idiomatic), Python pin as golden/differential oracle only. DIVERGENCE: contradicts PRD §18 "port formula-for-formula" — update PRD §18 + CHANGELOG on merge (code wins).
4. Acceptance: minutes + float gates (100% minute-rounded UTC equality AND `1e-9` deg ref leg / `1e-7` elsewhere).
5. Approach: A (staged clean-derivation with differential harness).

## 1. Architecture / stage map

Hexagonal-lite (`ARCHITECTURE.md`): Domain zero inward deps; Application may depend on Domain + Astro-internal; Astro-internal may depend on Domain VOs only; no Infrastructure. `Astro` stays `@internal`; userland touches only `Prayer/{Contracts,Enums,ValueObjects,Services}`.

- Stage 0 Freeze: 18-row Appendix A into `CalculationMethod::defaultParameters()`, SHA256 checksum of the dual-entered table recorded in the freeze commit message + `CHANGELOG.md`; `export_goldens.py` emits `tests/Goldens/prayer_{method}.csv` with Meeus/Takwim cites.
- Stage 1 Engine: `JulianDay` (done) → `MeanSolarCalculator` (Meeus declination/EoT) → `SolarTime` triple (transit/sunrise/sunset UTC, dip `h0=-0.833-0.0293*sqrt(h_m)`).
- Stage 2 Core: `PrayerTimesCalculator::calculate()` — fajr/isha angle-vs-interval, maghrib angle-vs-sunset, asr shadow (Shafi=1/Hanafi=2), dhuhr+offset, imsak/ishraq/dhuha offsets, UQU Ramadan override, `method+user` adjustments, `Minutes::roundedHalfUp` UTC.
- Stage 3 Edge: 3 high-latitude corrections + `SeasonAdjustedTwilight` (MSC only, `|lat|>=55`) + 4 polar handlers; `defaultFor()` wiring.
- Stage 4 Timetable: `TimetableService::monthly()` via `plusDays` loop, `NextPrayerResolver::resolve()` + DST vectors; `bench --budget` informative.

## 2. Components

- `CalculationMethod::defaultParameters()`: 18 frozen rows; `NONE` supplies no defaults (missing → `ConfigurationException`).
- `CalculationParameters`: validate fajr/isha/maghrib `[0,30]`, offsets `0–60` int (reject bool), `dhuha>=ishraq`, `elevationM [0,8848]` finite, `dhuhrOff 0–5`; add angle/rule `with*()` only as needed for `NONE`.
- `Astro`: `MeanSolarCalculator::declination/equationOfTime/position`, `DeltaTProvider`, `SolarTime`. No trig in `Prayer/Services`.
- Strategies (stubs exist): `ShafiAsr/HanafiAsr`, `AngleIsha/IntervalIsha`, `AngleMaghrib/MinutesAfterSunsetMaghrib`, `MiddleOfNight/SeventhOfNight/TwilightAngle + SeasonAdjustedTwilight`, `NearestLatitude/NearestDay/Makkah/Throw`.
- `PrayerSchedule` (UTC-only, done) + `forZone()` leaf view; `TimetableService`, `NextPrayerResolver → NextPrayer(from,at,inSeconds)`.

## 3. Data flow (UTC-math invariant)

Coordinates + CivilDate(UTC-midnight) + CalculationParameters → `JulianDay::fromCivilDate` → `MeanSolarCalculator::position(JD)` → `SolarTime` (transit/sunrise/sunset w/ dip) → hour-angle per twilight angle + shadow-length for asr + transit+dhuhrOffset → high-latitude `NightPortions` split or polar handler if unsolvable → derived `imsak=fajr-imsakOff`, `syuruk=sunrise` alias (same instant), `ishraq/dhuha=sunrise+off`, UQU-Ramadan isha override → `method+user` adjustments, UTC half-up rounding, `asr` clamp to `[dhuhr,maghrib]` only → `PrayerSchedule` (UTC) → `forZone()` at edge. `TimetableService` loops `plusDays`, stateless.

## 4. Error handling

- `ValidationException`: coords range/non-finite, non-UTC math inputs, year outside `1900–2100`, bad angles/offsets/elevation, `nightFraction f∉(0,1)`.
- `ConfigurationException`: `NONE` incomplete, `isRamadan` with non-UQU, `dhuha<ishraq`.
- `AstronomicalException` (`UndefinedScheduleException` w/ coords/date/rule props): polar-unsolvable after handler, bisection failure, residual NaN/INF. Propagate through `TimetableService`; never invent times.
- Ordering invariant asserted only when adjustments empty; user adjustments may break order, never silently reordered.

## 5. Testing / acceptance

- Goldens: 18× `prayer_{method}.csv` from pin; `Comparator.php` dual tolerance (minute 100% + float `1e-9`/`1e-7`).
- Differential log: per-vector `Δdeg` vs pin intermediates; triage `atan2/fmod/deg→rad` first; record divergences in CHANGELOG + PRD §18 note.
- Sweeps: ordering (empty-adjustments only), `CivilDate` round-trip, `Angle::normalized` idempotence (`mt_srand(1234)` inline); DST `America/New_York` 2015-03-08/2015-11-01 + resolver spring-forward vectors; polar `asr`-clamp test.
- Gates: `composer ci` green all matrix legs, `arch` (no userland `Astro` imports, `DateTimeImmutable`-only, docblocks), coverage ratchet up from 20, `bench --budget` informative (p50 <2ms/day, <1s/year, <16MB).

## Out of scope (M2+)

Qibla, Sunnah, Moon, Hijri, CLI, caching, i18n beyond `HijriMonth::label(en|ar)`, magnetic-declination model.
