# al-falaq — Technical PRD

- Package: `al-falaq/al-falaq`
- Type: offline, zero-dependency, stateless, framework-agnostic PHP library
- Lineage: port of `nexusnv/al-falak` (Python, detached fork of `alphahm/adhanpy` ← `batoulapps/adhan`) at pinned commit `PYTHON_PIN: <fill e.g. abc1234 — MUST be filled before M1>`; **library surface only, CLI excluded**. All parity goldens in `tests/Goldens/` are generated from that pin via `tools/export_goldens.py --pin <sha>`. If pin changes, goldens MUST be regenerated and diff reviewed.
- PHP: `>=8.2`, `declare(strict_types=1)`, PSR-1/4/12 + PER-2.0
- Status: draft for implementation (M1 blocked until Appendix A frozen and §20 decisions merged)

---

## 1. Purpose & Background

`al-falak` (Python) is an offline Islamic-astronomy library: precise prayer times, Qibla, Sunnah night markers, moon-sighting geometry (Yallop/Odeh/MABIMS), and Hijri conversion (Tabular / Umm al-Qura / MABIMS country calendars) with sunset rollover and official-correction offsets. Pipeline: coordinates + date + method/parameters → `SolarTime` → `PrayerTimes`.

`al-falaq` ports this to idiomatic PHP 8.2+ **from scratch architecturally** (not a line-by-line transliteration). It keeps behavioral fidelity (same angles, formulas, golden values per Appendix A/B) but adopts strict PHP OOP: bounded contexts, `Contracts` vs implementation separation, `final readonly` value objects, strategy polymorphism, and ArchTest-enforced layering.

Why a fresh architecture instead of mirroring `calculation/astronomy/data/util/`:

- Python's layout is technical-layering + module functions + a `PrayerTimes.__init__` god-object. Idiomatic in Python, an anti-pattern in strict PHP.
- PHP static analysis (PHPStan max), reflection-based Arch tests, and IDE DX reward explicit interfaces, small final classes, and `Enums/ValueObjects/Services/Strategies` role folders.
- Long-term cost: a mirror port is cheaper once, then every extension (new calendar, new geodetic model, new high-latitude rule) touches the god-object. A strategy-based design pays once, extends cleanly.

---

## 2. Goals

1. **Behavioral parity** with pinned Python on: prayer schedule (imsak/fajr/sunrise/syuruk/ishraq/dhuha/dhuhr/asr/maghrib/isha per §5.2 definitions), Qibla (spherical + ellipsoidal per §5.3), Sunnah markers, crescent geometry + visibility criteria, Hijri conversion + sunset rollover + offsets.
2. **Zero runtime dependencies**: `require: { php: >=8.2 }` only (+ `ext-date`, always present). No Carbon, no Symfony, no HTTP.
3. **Stateless & deterministic**: same inputs → same outputs, no globals, no singletons, no clock reads inside domain, no network, no filesystem.
4. **Framework-agnostic**: accept/return only SPL + `\DateTimeImmutable` / `\DateTimeZone`. Never type-hint Laravel/Symfony classes. `psr/clock` is a `suggest` (decided in §20 Q3): domain never reads clock; only `NextPrayerResolver` and examples accept an injected `ClockInterface`/explicit `now`.
5. **Strict typing & clean architecture**: `strict_types`, PHPStan max + strict-rules clean, `final readonly` VOs, native enums, dependency rule in §7, enforced by Arch tests.
6. **First-class DX**: discoverable named constructors, fluent `with*()` immutability, precise exceptions, examples, and docs that let a new user print prayer times in < 5 lines.

## 3. Non-Goals

- **No CLI surface.** Python `cli/` (`prayer|qibla|sunnah|hijri|moon|astro`) is out of scope. No `symfony/console` dependency. Framework apps may wrap the library in their own commands.
- No HTTP API, no daemon, no caching layer, no database, no telemetry.
- No magnetic-declination model (hook only: `QiblaResult::magneticHeading(float $declinationEast)` takes caller-supplied declination).
- No locale/translation of prayer names; keys are `Prayer` enum cases. Hijri month display names (English transliteration + Arabic) are provided as a pure `HijriMonth::label(string $locale): string` helper for `en`/`ar` only; no ICU/intl requirement, no other locales.
- No historical Hijri observance data beyond shipped tables (Umm al-Qura range 1356-02-01 AH – 1500-12-30 AH per Appendix B, Tabular algorithm, MABIMS rules); callers inject corrections via `OffsetStore`.

---

## 4. Personas & Use Cases

| Persona | Use case | API entry |
|---|---|---|
| App dev (plain PHP) | Daily + monthly prayer timetable for a mosque site | `PrayerTimesCalculator::calculate()` + `TimetableService::monthly()` |
| App dev | "What is next prayer / how long until?" badge | `NextPrayerResolver::resolve()` |
| Mobile-backend dev | Qibla compass + distance | `QiblaCalculator::calculate()` |
| Content dev | "Middle of night / last third" cards | `SunnahCalculator` |
| Researcher | Crescent visibility at sunset | `Moon\Services\CrescentCalculator` + `Moon\ValueObjects\CrescentGeometry` + `Yallop/Odeh/Mabims` evaluators |
| Admin tool | Gregorian → Hijri with sunset rollover | `GregorianToHijriConverter::convert()` |

All use cases must work **offline on shared hosting** (no extensions beyond `date`, no exec, no ICU requirement).

---

## 5. Scope — Functional Requirements

### 5.1 SharedKernel

- `Coordinates`: `fromDegrees(float $lat, float $lon)`, also `fromArray(array $pair): self` where `$pair === [lat, lon]` in that order, keys `0,1` only; assoc `['lat'=>..,'lon'=>..]` is rejected with `ValidationException` to avoid lat/lon swap bugs. Validate `lat ∈ [-90,90]`, `lon ∈ [-180,180]`, reject `bool/int-that-is-bool/non-finite/NaN`. Normalize `int` → `float`. Immutable. Error type: `InvalidCoordinatesException::outOfRange(string $which, mixed $received)` including valid range + received value + doc link.
- `CivilDate`: `fromYmd(int $y, int $m, int $d)`, `fromDateTime(DateTimeImmutable $dt)` (single canonical name; `fromPhpDate` alias is forbidden), `toUtcMidnight(): DateTimeImmutable`, `plusDays(int): self`. Gregorian validity checked via `checkdate()`; year range `1900–2100` (outside → `ValidationException`; astronomy outside this range is not golden-tested). Replaces Python `datetime|DateComponents` union — caller never constructs `DateComponents` directly.
- `Angle`: `fromDegrees(float): self`, `normalized(): self` (`[0,360)` via `fmod` + correction for negatives), `toRadians(): float`. Absorbs `FloatUtil::unwind_angle`. Rejects non-finite with `ValidationException`.
- `JulianDay`: `fromCivilDate(CivilDate): self`, `toFloat(): float`. Lives in `SharedKernel/ValueObjects`, not `Astro`, because calendars and tests need it without importing internals.
- Time: `Minutes::roundedHalfUp(DateTimeImmutable $utc): DateTimeImmutable` (truncate seconds/microseconds, half-up on `second >= 30`, then hour/day rollover; input MUST have `UTC` zone else `ValidationException`; mirrors `rounded_minute`). Duration math always in UTC; `->setTimezone($display)` only in presentation layer (`PrayerSchedule::forZone()`, `SunnahTimes::forZone()`).

### 5.2 Prayer context

- `Prayer` enum: `IMSAK, FAJR, SUNRISE, SYURUK, ISHRAQ, DHUHA, DHUHR, ASR, MAGHRIB, ISHA`. String values are lowercase (`'fajr'`, …) and are the canonical keys for `PrayerSchedule::all()/toArray()` per §5.2.1.
- `CalculationMethod` enum: `NONE, MUSLIM_WORLD_LEAGUE, EGYPTIAN, KARACHI, UMM_AL_QURA, DUBAI, MOON_SIGHTING_COMMITTEE, NORTH_AMERICA, KUWAIT, QATAR, SINGAPORE, TURKEY, TEHRAN, MOROCCO, JAKIM, KEMENAG, MUIS, BRUNEI`. Frozen parameter table is **Appendix A (normative)**. `NONE` means "no defaults — caller MUST supply every angle/offset via `with*()` else `ConfigurationException`". Each case exposes `defaultParameters(): CalculationParameters`. M1 is blocked until Appendix A is dual-entered from `PYTHON_PIN` and checksummed.
- `CalculationParameters`: `final readonly`, private ctor, construction only via `CalculationMethod::…->defaultParameters()->with*(…)`. Fields:
  - `method: CalculationMethod` (owning method, set by `defaultParameters()`, preserved by every `with*()`; drives the UQU-Ramadan guard and the MSC seasonal gating),
  - `madhab: Madhab`, `highLatitudeRule: HighLatitudeRule`, `polarCircleRule: PolarCircleRule` (enum, NOT string "handler name"),
  - `fajrAngle: float`, `ishaRule: IshaRule` (polymorphic: `AngleIsha(float $angle)` | `IntervalIsha(int $minutes)` — no parallel `ishaAngle`+`ishaInterval` nullable pair),
  - `maghribRule: MaghribRule` (`AngleMaghrib(float)` | `MinutesAfterSunsetMaghrib(int)`; TEHRAN uses angle 4.5°, UQU uses 0°/sunset),
  - `dhuhrOffsetMinutes: int` (minutes added after solar transit; default 0, allowed 0–5),
  - `imsakOffsetMinutes: int`, `ishraqOffsetMinutes: int`, `dhuhaOffsetMinutes: int`, `elevationM: float`, `isRamadan: bool`,
  - `methodAdjustments: PrayerAdjustments` (shipped defaults per method), `adjustments: PrayerAdjustments` (caller overrides). Effective offset = `method + user` per prayer.
  - Derived sub-times (normative): `imsak = fajr − imsakOffsetMinutes` (default 10 where method does not specify); `syuruk = sunrise` (alias, same instant object, documented as MY/SG naming); `ishraq = sunrise + ishraqOffsetMinutes` (default per Appendix A, commonly 0–15); `dhuha = sunrise + dhuhaOffsetMinutes` (defaults ≥ ishraq). Validation: angles `[0,30]` for fajr/isha/maghrib (not `[0,90]` — 90° is never valid twilight and hid bugs), offsets `0–60` ints (reject `bool`; PHP `is_bool` check first because `is_int(true)===false` but explicit guard teaches), `dhuhaOffsetMinutes >= ishraqOffsetMinutes`, `elevationM` finite `>=0 && <=8848`, `isRamadan` strict `bool`.
- `PrayerAdjustments`: `final readonly` DTO, per-prayer minute offsets `array<string,int>` keyed by `Prayer::value`, `empty()` + `with(Prayer,int)` (range `-60..+60`).
- `Madhab`: `SHAFI|HANAFI` with `shadowLength(): float` (1|2). Consumed via `AsrRule` strategy (`ShafiAsr`, `HanafiAsr`).
- `PrayerSchedule`: `final readonly`, stores **UTC only**. Props `imsak…isha: DateTimeImmutable` (all `UTC`). Methods `timeFor(Prayer): DateTimeImmutable` (UTC), `forZone(DateTimeZone): DisplaySchedule` (leaf conversion, math never rerun), `all(): array<string,DateTimeImmutable>` (UTC, keys per Prayer values), `toArray(): array<string,string>` (ISO8601 `Y-m-d\TH:i:sP` in UTC — frozen schema in Appendix C), `nightLength(): DateInterval`. Documented invariant on non-polar days with zero adjustments: `fajr ≤ sunrise ≤ dhuhr ≤ asr ≤ maghrib ≤ isha`; user `adjustments` MAY break ordering — calculator MUST NOT silently reorder; `Sweep/` test asserts ordering only when adjustments are empty. Polar-boundary saturation: `asr` clamped to `[dhuhr,maghrib]` (only documented clamping allowed).
- `PrayerTimesCalculator`: `final`, `__construct(HighLatitudeCorrection $hl, PolarCircleHandler $polar, TwilightModel $twilight)` with `default(): self` and `defaultFor(CalculationParameters): self` (rule-driven wiring). Single command `calculate(Coordinates, CivilDate, CalculationParameters): PrayerSchedule` — **no `$display` param** (display is `forZone()` post-step). Constructor does no computation. Throws `AstronomicalException` (polar undefined, never NaN/null), `ConfigurationException` (method-vs-params misuse, e.g. `isRamadan=true` with non-UQU), `ValidationException` (bad inputs), never bare `\ValueError`. Deferred-context `\LogicException` markers (`Wgs84KarneyCalculator`, `SunnahTimes::nightFraction`, M3/M4 contexts) persist until the pre-M5 no-throw sweep per the approved M2 design.
- High-latitude rules (`MIDDLE_OF_NIGHT, SEVENTH_OF_NIGHT, TWILIGHT_ANGLE`) → `HighLatitudeCorrection` strategy returning `NightPortions(fajr,isha)`. Moonsighting-Committee seasonal twilight (`SeasonAdjustedTwilight: seasonalSeconds(dayOfYear, year, latitude): {morning, evening}` in whole SECONDS, `int(round(adj·60))` per pin) is an injectable `TwilightModel`. Seasonal SAFE caps engage whenever the MSC method or the `SEASONAL_TWILIGHT` rule is in play, at ALL latitudes; the seventh-of-night temp replaces the angle temp only at signed latitude ≥ 55 (pin `_set_fajr`/`_set_isha` gate). Formula pinned in Appendix B.
- Polar-circle handlers (`NEAREST_LATITUDE default, NEAREST_DAY, MAKKAH, NONE`) → `PolarCircleHandler` strategies selected by `PolarCircleRule` enum. `NearestLatitude` bisects toward equator (max 60 iterations, stop when sunrise/sunset solvable, then 0.5° backoff toward equator per Python `_nearest_latitude_with_sunrise_sunset`); if still unsolvable → `AstronomicalException`. `NearestDay` searches ±366d day-by-day, nearest first; if none → `AstronomicalException`. `Makkah` substitutes Makkah coords `(21.4225, 39.8262)` keeping input date; `None/Throw` lets `AstronomicalException` propagate. `UndefinedScheduleException extends AstronomicalException` carries `coordinates/civilDate/rule` typed props.
- Elevation: dip-of-horizon `h0 = -0.833 - 0.0293 * sqrt(h_m)` degrees (code form, `sqrt()` on meters), plumbed through internal `SolarTime`, default `0`. Negative elevation rejected.
- Umm al-Qura Ramadan mode (normative): `method===UMM_AL_QURA && isRamadan===true` → `isha = maghrib + 120min`; `method===UMM_AL_QURA && isRamadan===false` → `isha = maghrib + 90min`. The old prose "+30" was a typo and is removed. `isRamadan` with any other method → `ConfigurationException`.

#### 5.2.1 Timetable + next-prayer (required for "best" claim)

- `TimetableService`: `monthly(Coordinates, int $year, int $month, CalculationParameters): list<PrayerSchedule>` (UTC, loops `CivilDate::plusDays`, constant memory, no retained state). Year helper is a loop over `monthly()`, NOT a separate code path. Performance budget in §6 applies here.
- `NextPrayerResolver`: `resolve(PrayerSchedule $today, PrayerSchedule $tomorrow, DateTimeImmutable $nowUtc): NextPrayer` where `$nowUtc` must be UTC (`ValidationException` otherwise; no clock read inside — caller passes `now`, optionally from injected `psr/clock`). Returns `readonly(from: Prayer, at: DateTimeImmutable UTC, inSeconds: int)`. Spring-forward/day-boundary semantics tested with `America/New_York` DST vector.

### 5.3 Qibla context

- `QiblaCalculator`: `final`, `__construct(?GeodesyCalculator $geodesy = null)` (ctor injection ONLY — method-param form is removed) + `default(): self` (wires `SphericalCalculator`) + `wgs84(): self` (wires `Wgs84KarneyCalculator`). Single command `calculate(Coordinates): QiblaResult`.
- Default `SphericalCalculator` (Todhunter 1936 p.50 initial-bearing + haversine distance with IUGG mean radius `6371.0088 km` — value now pinned; `clamp(a,0,1)` before `asin` preserved). Opt-in `Wgs84KarneyCalculator` implementing Karney (2013) geodesic inverse on WGS84 (`a=6378.137`, `f=1/298.257223563`): max 100 iterations, convergence `1e-12`, non-convergence → `AstronomicalException` (not defensive silent fallback). Spherical-vs-WGS84 max divergence documented (<0.2° direction, <0.5% distance except near antipode); if Karney proves too risky in M2 spike, fallback decision is Vincenty with same interface — no string-flag diffusion. No `method: string` flag — polymorphism replaces `Qibla.py:48` string union.
- `QiblaResult`: `final readonly(direction: float [0,360), distanceKm: float)`, `magneticHeading(float $declinationEast): float` (`true − fmod(decl,360)`, unwound to `[0,360)`; reject non-finite with `ValidationException`). Degenerate Makkah-self (distance < 1m) returns `direction=0.0` + `distanceKm=0.0`; antipode returns computed bearing in range; never throws for these two cases.

### 5.4 Sunnah context

- `SunnahCalculator`: `__construct(PrayerSchedule $today, PrayerSchedule $tomorrow)` (both UTC) or `fromCalculator(PrayerTimesCalculator, Coordinates, CivilDate, CalculationParameters)` that internally computes tomorrow. Night = `today.maghrib → tomorrow.fajr` in UTC. If either endpoint is polar-undefined the ctor MUST have already thrown — `SunnahCalculator` never catches `AstronomicalException` to invent a night. UTC duration math (DST-safe), `Minutes::roundedHalfUp` in UTC, `forZone()` conversion last.
- `SunnahTimes` VO lives at `Prayer/ValueObjects/SunnahTimes` (not undefined): `middleOfNight, firstThird, lastThird: DateTimeImmutable` (UTC), `tahajjudWindow(): array{from: DateTimeImmutable, to: DateTimeImmutable}` (`[lastThird, fajr)`), `nightFraction(float $f): DateTimeImmutable` with `f ∈ (0,1)` exclusive (no optional start/end overload — anchors are always maghrib/fajr; custom-interval overload removed as it duplicated ctor), `forZone(DateTimeZone): self`, degenerate interval (fajr ≤ maghrib) → `ValidationException`.

### 5.5 Moon-sighting context (public) / Astro internals

- Public surface is `Moon/` context (NOT `Astro`): `Moon/ValueObjects/CrescentGeometry` (public, `final readonly`, 10-field sunset geometry bundle (this section): `sunAltitude, moonAltitude, elongationDeg, moonAgeHours, lagMinutes|null, deltaAzimuthDeg, illuminatedFraction, arcvGeoDeg, widthArcmin, ageAtMoonsetHours|null`; `sunAltitude` is geocentric — the pin applies parallax to the moon only; `lagMinutes`/`ageAtMoonsetHours` are null iff the moon never sets; topocentric ARCV derives as `moonAltitude − sunAltitude` and is never stored), `Moon/Services/CrescentCalculator::atSunset(Coordinates, CivilDate): CrescentGeometry` (primitive pipeline) + `evaluate(Coordinates, CivilDate): array{yallop, odeh, mabims1992, neomabims2021}` of `CrescentVerdict` (composition root; null window falls back to the altitude/elongation branch for 1992) + `evaluateGeometry(CrescentGeometry)` (pure scoring on held geometry; `evaluate()` delegates to it), evaluators `Moon/Strategies/YallopEvaluator, OdehEvaluator, Mabims1992Evaluator, NeoMabims2021Evaluator` each with `evaluate(CrescentGeometry): CrescentVerdict` (pure criterion polynomials; MUST NOT import `Astro\` — ArchTest-enforced). `Astro/` remains `@internal` engine (`SolarTime`, `SolarCoordinates`, `LunarCoordinates`, `DeltaTProvider`, `GeodesyEngine`) and MUST NOT be imported by userland; ArchTest bans `Astro` imports in Domain (`Contracts/ValueObjects/Enums`) and `Moon/Strategies/`; only `Services/` and non-Moon `Strategies/` may import `Astro`.
- Thresholds (normative, Appendix B): Yallop `limit = −0.1018w³ + 0.7319w² − 6.3226w + 11.8371`, `q = (arcv_geo − limit)/10`, strict-`>` zones `> 0.216 → A, > −0.014 → B, > −0.160 → C, > −0.232 → D, > −0.293 → E`, else `F` (cite Yallop 1997 + Schaefer); Odeh `limit = −0.1018w³ + 0.7319w² − 6.3226w + 7.1651`, `V = arcv_topo − limit`, Danjon floor strict `arcl < 6.4 → D`, classes `V >= 5.65 → A, >= 2.0 → B, >= −0.96 → C`, else `D` (cite Odeh 2006); MABIMS-1992 `(alt >= 2 && elong >= 3) || age_at_moonset >= 8` (all `>=`; age is `ageAtMoonsetHours`); Neo-2021 `alt >= 3 && elong >= 6.4` (criterion key `neomabims2021`, no age gate). Evaluators are pure functions on geometry, thin final classes.

### 5.6 Hijri context

- `HijriDate`: `final readonly(year,month,day)`, universal bound `day 1..30`, exact month length enforced by calendar at conversion (`ValidationException` if caller constructs impossible date directly); `isoformat(): string` (`YYYY-MM-DD` zero-padded); `compareTo(HijriDate): int`, `equals(HijriDate): bool`.
- `HijriCalendar` contract: `fromGregorian(DateTimeImmutable|CivilDate $d): HijriDate`, `monthLength(int $y, int $m): int`, `name(): string`. **Mutable `DateTime` is rejected** — signature uses `DateTimeImmutable` only (previous `DateTimeInterface` union removed to match §9 rule). Implementations: `TabularCalendar` (Kuwaiti algorithm, epoch `1937-03-14` civil = `1355-11-25` AH, 30-year leap cycle `{2,5,7,10,13,16,18,21,24,26,29}` — Appendix B), `UmmAlQuraCalendar` (shipped table for `1356-1500 AH`, indexed by absolute day number, binary search, range guard → `HijriOutOfRangeException extends ValidationException`), `MabimsCalendar` (per-country Neo-MABIMS rules for MY/ID/SG/BN; new countries = new class implementing same 3-method interface).
- `OffsetStore` contract (fixed shape): `offsetDaysFor(HijriDate $date, HijriCalendar $cal): int` (official day corrections, e.g. `-1..+2`). Converter applies `date.plusDays(offset)`; previous `apply(HijriDate, HijriCalendar): HijriDate` overload removed because it hid direction/magnitude.
- `GregorianToHijriConverter::convert(DateTimeImmutable $dt, HijriCalendar $cal, ?Coordinates $coords=null, ?CalculationParameters $params=null, bool $changeAtSunset=false, ?OffsetStore $offsets=null): HijriDate`. `changeAtSunset=true` requires `$coords` AND `$params` (else `ConfigurationException` — coords alone insufficient because maghrib needs method). Civil date normalized in UTC (`$dt->setTimezone(UTC)` first; wall-clock date in other zones MUST NOT be used). Maghrib probe via `PrayerTimesCalculator` on UTC-midnight civil date. PHP has no naive datetimes — "naive treated as UTC" note removed; all inputs carry a zone, document that callers MUST pass UTC or explicit zone. Polar `AstronomicalException` propagates unwrapped (no catch-and-guess).

---

## 6. Non-Functional Requirements

| NFR | Target |
|---|---|
| Zero deps | `composer show` runtime = `php` only. CI asserts zero locked runtime packages (`composer.lock` `packages` empty). |
| Stateless | No static mutable props, no singletons, no `date_default_timezone_set` calls. Clock injected only at edges (`NextPrayerResolver::resolve(now)`, examples); domain takes explicit dates. |
| Offline | No `curl/file_get_contents(http)`, no `exec`, no GD/ICU requirement. ArchTest bans `curl_, file_get_contents('http', exec, shell_exec`. |
| Determinism | Minute-rounded goldens identical across PHP 8.2–8.4 × UTC/non-UTC ini. Tests force `date_default_timezone_set('UTC')` in bootstrap + a dedicated non-UTC matrix leg asserting same UTC goldens. No `byte-identical float` claim. |
| Precision | Prayer goldens match pinned-Python minute-rounded UTC values 100% (hard gate). Float intermediates: absolute tolerance `1e-9` deg on reference `linux/x86_64/PHP8.2` leg, `1e-7` deg on other legs (arm64/Windows/8.3/8.4). Haversine `clamp(a,0,1)` preserved. Tolerance logic lives in `tests/Goldens/Comparator.php`, not inline. |
| Performance | Budgets enforced by `examples/bench.php --budget` in CI (not ad-hoc): single-day schedule p50 < 2ms on `ubuntu-latest` runner, 1-year `TimetableService` < 1s wall, peak memory < 16MB. Umm al-Qura data indexed by absolute day number (binary search). No memoization with unbounded growth; any cache is caller-owned. Budgets are informative in M1–M2, gates from M5. |
| Compatibility | `php >=8.2`, 64-bit float assumed. 32-bit is explicitly **unsupported** (previous "best-effort" removed — it contradicted determinism; 64-bit requirement documented in `README.md`/docs — Composer has no arch-level constraint to express it). No fibers, no 8.3-only syntax in `src/`. |
| Security | No unserialize, no eval, no dynamic include. All numeric inputs validated with `is_finite()` before trig; any residual `NaN`/`INF` → `AstronomicalException`, never returned. |

---

## 7. Architecture

Hexagonal-lite + DDD bounded contexts. Dependency rule (enforced by reflection-based ArchTest):

```
Domain (Contracts, VOs, Enums, Exceptions) has ZERO dependencies inward.
Application (Services) MAY depend on Domain + Astro-internal.
Astro-internal MAY depend on Domain VOs only (for types), never on Application.
Nothing depends on Infrastructure (there is none). Examples/tests depend inward only.
```

- **Domain**: `SharedKernel`, `Prayer`, `Qibla`, `Moon`, `Hijri` value objects, enums, contracts, exceptions. Zero I/O, zero math beyond trivial normalization.
- **Application**: `*Calculator` / `*Converter` / `TimetableService` / `NextPrayerResolver` orchestrating domain + `Astro`. No trig here.
- **`Astro` internal**: `@internal` pure astronomy engine. Userland MUST NOT import it; public moon path is `Moon/` facade.
- Host-app wiring (any PHP app, any framework, or none) lives outside this package: callers construct services with plain `new` / `::default()` (see `docs/integrations.md`). Adapters depend on this package, never vice versa.

Python → PHP concept map:

| Python | PHP |
|---|---|
| `frozen dataclass` | `final readonly` + private ctor + named ctors |
| `Enum` + `METHODS_PARAMETERS` dict + `setattr` | `enum` with `defaultParameters()` + readonly `with*()` (see Appendix A) |
| `tuple[float,float]\|Coordinates`, `datetime\|DateComponents` unions | strict `Coordinates`, `CivilDate` VOs |
| `FloatUtil/CalendarUtil/TimeComponents` modules | `Angle`, `Minutes`, `JulianDay` VOs in SharedKernel (no `Util/` namespace) |
| `method: str` flag (`Qibla`) | `GeodesyCalculator` polymorphism (ctor injection) |
| `PrayerTimes.__init__` god-object | `PrayerTimesCalculator::calculate(): PrayerSchedule` (UTC) + `forZone()` view + `TimetableService` + `NextPrayerResolver` |
| `bridge.gregorian_to_hijri()` function | `GregorianToHijriConverter::convert()` (NO global `functions.php` shim — decided §20 Q2, class-only API) |

---

## 8. Directory Structure (normative)

`src/{Context}/{Role}/`. Namespace `AlFalaq\AlFalaq\…`. One class per file.

```
src/
├── SharedKernel/
│   ├── Contracts/      # DomainException (interface); ClockView (small, no psr dep)
│   ├── ValueObjects/   # Coordinates, CivilDate, Angle, Minutes, JulianDay
│   ├── Exceptions/     # AlFalaqException (base, extends RuntimeException),
│   │                    # AstronomicalException, ConfigurationException, ValidationException,
│   │                    # InvalidCoordinatesException extends ValidationException
│   └── Traits/         # traits only: ValidatesFinite, HasWithersGuard
├── Prayer/
│   ├── Contracts/      # PrayerTimesCalculatorInterface, HighLatitudeCorrection,
│   │                    # IshaRule, MaghribRule, AsrRule, PolarCircleHandler, TwilightModel,
│   │                    # TimetableServiceInterface, NextPrayerResolverInterface
│   ├── Enums/          # Prayer, CalculationMethod, Madhab, HighLatitudeRule, PolarCircleRule
│   ├── ValueObjects/   # PrayerSchedule, DisplaySchedule, CalculationParameters, PrayerAdjustments,
│   │                    # NightPortions, SunnahTimes, NextPrayer
│   ├── Services/       # PrayerTimesCalculator, SunnahCalculator, TimetableService, NextPrayerResolver
│   ├── Strategies/     # MiddleOfNightCorrection, SeventhOfNightCorrection, TwilightAngleCorrection,
│   │                    # AngleIsha, IntervalIsha, AngleMaghrib, MinutesAfterSunsetMaghrib,
│   │                    # ShafiAsr, HanafiAsr, SeasonAdjustedTwilight,
│   │                    # NearestLatitudeHandler, NearestDayHandler, MakkahHandler, ThrowHandler
│   ├── Exceptions/     # UndefinedScheduleException extends AstronomicalException
│   └── Traits/         # NormalizesCoordinatesInput (array|VO coercion, single place)
├── Qibla/
│   ├── Contracts/      # GeodesyCalculator
│   ├── ValueObjects/   # QiblaResult
│   ├── Services/       # QiblaCalculator
│   └── Strategies/     # SphericalCalculator, Wgs84KarneyCalculator
├── Moon/               # public moon-sighting facade (Astro stays internal)
│   ├── Contracts/      # CrescentEvaluator
│   ├── ValueObjects/   # CrescentGeometry, CrescentVerdict
│   ├── Services/       # CrescentCalculator
│   └── Strategies/     # YallopEvaluator, OdehEvaluator, Mabims1992Evaluator, NeoMabims2021Evaluator
├── Hijri/
│   ├── Contracts/      # HijriCalendar, OffsetStore
│   ├── Enums/          # HijriMonth (with label('en'|'ar'))
│   ├── ValueObjects/   # HijriDate
│   ├── Services/       # GregorianToHijriConverter (orchestration; calendars selected by caller)
│   ├── Strategies/     # TabularCalendar, UmmAlQuraCalendar, MabimsCalendar (calendar variants)
│   └── Exceptions/     # HijriOutOfRangeException extends ValidationException
└── Astro/              # @internal
    ├── Contracts/      # SolarModel
    ├── ValueObjects/   # SolarPosition, SolarTime, LunarPosition
    └── Services/       # MeanSolarCalculator, DeltaTProvider, GeodesyEngine
tests/
├── SharedKernel/       # mirrors src/SharedKernel/ (context suites land with M1+)
├── Sweep/              # deterministic property sweeps (seeded mt_srand inline per test)
├── Wiring/             # plain-new constructibility smoke test (no container)
├── Goldens/            # CSV + Comparator.php + vectors citing Meeus page/ed., Takwim URL+date; generated from PYTHON_PIN
└── ArchTest.php        # layer + all-VOs/Services-final + no-Util-namespace + all-Exceptions-hierarchy + no-Error-file/class + DateTimeImmutable-only + offline-IO + docblock rules (comment-aware scans, autoloader discovery)
examples/
├── basic.php, methods.php, qibla.php, moon.php, sunnah.php, hijri.php, polar.php, timetable.php, bench.php
```

Role rules:

- `Contracts/`: interfaces only. Service contracts keep the explicit `Interface` suffix (`PrayerTimesCalculatorInterface`, `TimetableServiceInterface`, `NextPrayerResolverInterface`) so architectural roles stay transparent; all other contracts use short names. Exactly one responsibility each; no default method bodies except pure delegation.
- `Enums/`: native `enum` only. Behavior limited to self-description (`values()`, `defaultParameters()` on `CalculationMethod` reading Appendix A).
- `ValueObjects/`: `final readonly`, validated in ctor, `equals/compareTo/toArray/isoformat/forZone` behavior allowed, no service calls, no trig.
- `Services/`: `final`, constructor-promoted strategy deps with defaults via `default()` named ctor, single command method (plus `defaultFor()` where method-dependent). No statics holding state. Qibla injection is ctor-only. Explicit two-method exception: `Moon\Services\CrescentCalculator` ships `atSunset()` (primitive) + `evaluate()` (composition, delegating to pure-scoring `evaluateGeometry()` so held geometry is reused without recomputing the ephemeris) on one service per the M3 spec §3.2 — no other service may grow a second command without its own amendment.
- `Strategies/`: `final`, implement one `Contracts/` interface, no branching on sibling type.
- `Exceptions/`: one per file, `*Exception` suffix, hierarchy `AlFalaqException ← {AstronomicalException, ConfigurationException, ValidationException} ← context-specific`. Static ctors (`::outOfRange(...)`) preferred. `*Error` names are forbidden by ArchTest (filename `Error.php` + class short-name `*Error` banned).
- `Traits/`: traits only, stateless, `private` helpers. Any trait needing state is rejected in review — convert to collaborator.

---

## 9. Public API Sketch

```php
declare(strict_types=1);

use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Enums\Prayer;
use AlFalaq\AlFalaq\Prayer\Services\PrayerTimesCalculator;
use AlFalaq\AlFalaq\Qibla\Services\QiblaCalculator;
use AlFalaq\AlFalaq\Hijri\Strategies\TabularCalendar;
use AlFalaq\AlFalaq\Hijri\Services\GregorianToHijriConverter;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;

$coords = Coordinates::fromDegrees(35.7750, -78.6336);
$date   = CivilDate::fromDateTime(new DateTimeImmutable('2015-07-12', new DateTimeZone('UTC')));

$scheduleUtc = PrayerTimesCalculator::default()->calculate(
    $coords, $date, CalculationMethod::NORTH_AMERICA->defaultParameters(),
);
$display = $scheduleUtc->forZone(new DateTimeZone('America/New_York'));

echo $display->timeFor(Prayer::FAJR)->format('H:i');
// Timetable + next prayer
$month = (new TimetableService())->monthly($coords, 2015, 7, CalculationMethod::NORTH_AMERICA->defaultParameters());
$next = (new NextPrayerResolver())->resolve($scheduleUtc, $month[12], new DateTimeImmutable('now', new DateTimeZone('UTC')));
// Qibla (ctor injection, not method param)
$qibla = QiblaCalculator::default()->calculate($coords); // ->direction, ->distanceKm
$qiblaWgs = QiblaCalculator::wgs84()->calculate($coords);
// Hijri (DateTimeImmutable only)
$hijri = (new GregorianToHijriConverter())->convert(
    new DateTimeImmutable('2025-03-01 12:00:00', new DateTimeZone('UTC')),
    new TabularCalendar(),
);
echo $hijri->isoformat(); // 1446-09-01
```

Design notes: named ctors over multi-union ctors; `with*()` over setters; `DateTimeImmutable` only (reject mutable `DateTime` via `ValidationException` — ArchTest bans `DateTimeInterface` unions in `src/` except `DateTimeImmutable`); schedules store UTC, display via `forZone()` leaf conversion, never an input to math.

---

## 10. Usability

- **5-minute rule**: copy-paste example prints today's times without reading docs. `CalculationMethod::X->defaultParameters()` removes the "which params?" cliff; `with*()` discovers customization via IDE autocomplete.
- **One obvious path**: `*Calculator::default()` static factory wires recommended strategies; advanced users inject alternatives explicitly. No service-locator, no container config to learn.
- **Errors that teach**: `InvalidCoordinatesException::outOfRange('lat', 91.0)` message includes valid range + received value + doc link; `UndefinedScheduleException` message includes coords/date + which `PolarCircleRule` would resolve it. No `NaN`, no `null` times, no silent clamping (except documented polar-boundary `asr` saturation).
- **Bilingual naming awareness**: `syuruk` (= sunrise alias, MY/SG), `ishraq`/`dhuha` offsets documented as configurable conventions per Appendix A, not universal fiqh — prevents misuse. `HijriMonth::label('ar')` gives Arabic names without intl.
- **Docs**: `README` quickstart + `docs/{prayer,qibla,sunnah,moon,hijri,migration,integrations}.md` (relative links, versioned snapshots frozen per release like upstream). Examples runnable via `php examples/basic.php`. `docs/integrations.md` shows plain-`new` wiring (smoke-tested by `tests/Wiring/`).

## 11. Developer Experience (DX)

- **Install**: `composer require al-falaq/al-falaq`, no ext beyond `date`, works on shared hosting PHP 8.2 (64-bit).
- **IDE-first**: `final readonly` + enums + promoted ctor types give complete autocomplete; PHPStan level max keeps refactoring safe; `composer ci` runs `lint + cs + stan + test` in one command (single canonical name; `check` alias removed).
- **Debuggability**: value objects implement `__toString`/`toArray` (JSON-able schedule per Appendix C frozen schema); exceptions carry structured context (`coordinates`, `civilDate`, `rule`) as typed props, not string-parsing.
- **Testability**: pure domain → plain `new` in tests, no mocks needed except strategy doubles via anonymous classes; clock never read inside domain so no time-freezing library required (`NextPrayerResolver` takes explicit `$nowUtc`); `Sweep/` seeds via `mt_srand(1234)` inline per test for reproducibility, `tests/bootstrap.php` fixes UTC.
- **Contribution path**: `CONTRIBUTING.md` (setup, `composer ci`, golden-citation rule: every numeric vector cites Meeus page+ed. or Takwim URL+date + `PYTHON_PIN`), `ARCHITECTURE.md` (this doc's §7–8 condensed), PR template requiring test evidence.
- **BC policy**: SemVer; public API = `src/{SharedKernel,Prayer,Qibla,Moon,Hijri}/{Contracts,Enums,ValueObjects,Services}` + documented `*Exception`s. `Astro` is `@internal` and may break minor-to-minor.

## 12. Scalability

- **Compute**: per-day schedule is O(1) trig + at most one bisection (polar edge only). No per-call table scans; Umm al-Qura data stored as indexed map keyed by absolute day number (binary search, not linear). `TimetableService::monthly()` loops `CivilDate::plusDays` with no retained state → constant memory. Budgets verified by `examples/bench.php --budget` (see §6).
- **Concurrency**: stateless services are fiber/request-safe; no static caches. If a host app needs caching, it caches `PrayerSchedule::toArray()` externally (PSR-16 in userland, not here).
- **Data growth**: Hijri tables versioned as data classes (`UmmAlQuraData_1356_1500`); adding a range = new class + composite lookup, no migration. MABIMS country rules behind `HijriCalendar` so new countries plug in.
- **Team scale**: bounded contexts + ArchTest let prayer/hijri/qibla/moon evolve in parallel without merge conflicts in a god-object. Public surface review checklist blocks `Util/` regrowth.

## 13. Adaptability

- **New calculation method**: add enum case + `defaultParameters()` entry (Appendix A row), zero calculator changes. Covered by golden test.
- **New rule variant** (e.g., alternative twilight model): new `Contracts` implementation + wiring in `defaultFor()` behind config flag; old behavior preserved.
- **New calendar**: implement 3-method `HijriCalendar` interface; sunset-rollover and offsets compose automatically via converter.
- **New geodetic model** (e.g., Vincenty fallback if Karney spike fails): implement `GeodesyCalculator`; `QiblaCalculator` ctor accepts it. No string-flag proliferation.
- **Host-app wiring**: `docs/integrations.md` shows plain-`new` construction (`PrayerTimesCalculator::default()`, explicit strategy injection) — no service providers, no config files, none of it in this repo's `src/`. `tests/Wiring/` smoke-tests that every service is constructible in one expression.
- **Output shapes**: `PrayerSchedule::all()` + `toArray()` per Appendix C feed JSON APIs, iCal generators, and PDF timetables without library changes.

## 14. Future-Proofness

- **PHP trajectory**: 8.2 baseline avoids 8.3+ lock-in; code is `Rector`-upgradeable (typed constants, readonly evolution). When `php: >=8.3` becomes safe (shared-hosting lag ~2y), adopt typed-class-constants and `#[\Override]` mechanically.
- **Standards track**: PER-3.0 via php-cs-fixer upgrades; PHPStan max + strict-rules absorb new checks; PHPUnit 11 baseline (PHP 8.2-compatible, no framework-ecosystem runner). `psr/clock` accepted as `suggest` for edge injection (decided §20); domain never type-hints it, only `NextPrayerResolver`/examples accept `ClockInterface` optionally.
- **Astronomy track**: `DeltaTProvider` contract isolates polynomial-vs-table updates; Meeus citations pin formulas so future errata map to single `Astro` classes. Lunar/moon-sighting evaluators versioned (`Mabims1992Evaluator` vs `NeoMabims2021Evaluator`) so criteria revisions coexist.
- **Deprecation discipline**: `@deprecated` + `E_USER_DEPRECATED` one minor before removal; `CHANGELOG.md` (Keep-a-Changelog) + `UPGRADE.md` per major; `composer audit` + `roave/security-advisories` (dev) in CI.

---

## 15. Quality Gates & Tooling

`composer.json` (runtime stays empty):

```json
{
  "name": "al-falaq/al-falaq",
  "type": "library",
  "license": "MIT",
  "require": { "php": ">=8.2" },
  "suggest": { "psr/clock": "For ClockInterface injection at edges (NextPrayerResolver, examples)" },
  "require-dev": {
    "phpunit/phpunit": "^11.0",
    "phpstan/phpstan": "^2.0",
    "phpstan/phpstan-strict-rules": "^2.0",
    "friendsofphp/php-cs-fixer": "^3.0",
    "rector/rector": "^2.0",
    "infection/infection": "^0.29"
  },
  "scripts": {
    "lint": "find src examples tools tests -name '*.php' -exec php -l {} \\;",
    "cs": "php-cs-fixer fix --dry-run --diff",
    "cs-fix": "php-cs-fixer fix",
    "stan": "phpstan analyse --memory-limit=1G",
    "test-unit": "phpunit --no-coverage",
    "test": "php tools/coverage_gate.php --min=20",
    "arch": "phpunit --group arch",
    "bench": "php examples/bench.php --budget",
    "ci": ["@lint", "@cs", "@stan", "@test"]
  }
}
```

- **Tests**: plain PHPUnit 11 (no ecosystem runner); layout mirrors `src/` as contexts land (M0: `SharedKernel` + `Sweep` + `Wiring`); `tests/bootstrap.php` fixes UTC; `tests/Goldens/` (pinned-Python parity vectors + `Comparator.php` with dual tolerances + primary-source citations), `tests/Sweep/` (seeded `mt_srand(1234)` property: ordering invariant only when adjustments empty, round-trip `CivilDate`, unwind idempotence), `tests/ArchTest.php` (reflection-based layer bans per §7 via composer autoloader, all `ValueObjects/` + all `Services/` final, all `Exceptions/` extend the base, `Util`/`Utils` ban, `Error` filename + class-name ban, comment-aware `DateTimeImmutable`-only + offline-IO scans, docblock rules: every class-like and public/protected method needs a summary, every `@param $name`, every non-void `@return`), `tests/Wiring/` (plain-`new` constructibility smoke test). Coverage floor enforced by `tools/coverage_gate.php` (single `phpunit --coverage-text` run, validated `--min=0-100`) via pcov on CI as a ratchet: M0 baseline `--min=20`, raised per milestone as contexts land, 95 at M5. Without a local driver the gate passes on green tests with a notice (failures still fail).
- **Mutation** (nightly, not gate until M5, then gate): Infection MSI ≥ 85% on `Prayer/ + Hijri/ + Qibla/ + Astro/ + Moon/` (all math paths — previous Prayer+Hijri-only scope removed).
- **CI** (`.github/workflows/test.yml`, push + PR, ubuntu-only): `lint/cs/stan` once on PHP 8.4, then `test + bench` on PHP `8.2, 8.3, 8.4` × `TZ=UTC` plus one `TZ=America/New_York` leg on 8.4 (tz rules are platform-independent — PHP bundles tzdata — so the full tz cross-product is redundant). Windows is a pre-release gate (`.github/workflows/pre-release.yml`, manual dispatch + `v*` tags, `windows-latest` × same php/tz shape): the Windows leg asserts UTC goldens (documents that PHP bundles its own tzdata; no OS tzdata requirement). Docs + release workflows mirror upstream.

## 16. Packaging & Release

- Composer `type: library`, `license: MIT`, PSR-4 `AlFalaq\AlFalaq\ → src/`, `exclude-from-classmap: tests/, examples/`.
- Ship `README.md`, `LICENSE`, `CHANGELOG.md`, `UQ_PROVENANCE.md` (see §20 Q4).
- Versioning SemVer; tags `vX.Y.Z`; GitHub release notes from changelog; docs snapshots frozen per minor (`docs/vX.Y/`) with `versions.json` if docs site exists.

## 17. Security & Scope Notes

- Offline: no network, no secrets, no deserialization. Threat surface = malformed numeric/date inputs → handled by validation before trig (`is_finite` guards in `ValidatesFinite` trait; any residual `NaN`/`INF` → `AstronomicalException`, never returned).
- Windows has no system IANA DB but PHP bundles tzdata — no extra host requirement; CI Windows leg proves it.
- Private vulnerability reporting (GitHub Security tab); supported: latest `1.x` + `main`.

## 18. Risks & Mitigations

| Risk | Mitigation |
|---|---|
| Float divergence vs Python (deg→rad, `atan2`, `fmod`) | Clean re-derive against pin `a7baa8b` with per-formula Meeus citations + differential golden log: `tools/export_goldens.py` vectors vs PHP in `tests/Goldens/` (minute-equality gate, dual-tolerance `Comparator.php`); divergences fixed in code (signed-lat seventh gate, second-precision seasonal offsets) or logged as emptied CSV cells with per-file reasons (`adee976`) |
| `DateTimeImmutable` vs `ZoneInfo` DST edge (spring-forward) | UTC-math-then-`forZone()` invariant + dedicated DST test (`America/New_York` 2015-03-08 + 2015-11-01) + `NextPrayerResolver` DST vectors |
| Umm al-Qura table transcription errors | Dual-entry review + checksummed `UmmAlQuraData_1356_1500` + range-boundary tests + provenance file |
| Scope creep (adhan CLI parity, new calendars pre-1.0) | CLI explicitly out; new calendars land as minor `HijriCalendar` impls with goldens |
| Over-abstraction (too many strategies) | `default()/defaultFor()` covers 95%; strategies only where Python already branches (polar/high-latitude/isha/maghrib/asr/geodesy) |
| Karney implementation risk | M2 spike with convergence vectors; fallback to Vincenty behind same `GeodesyCalculator` interface, decision logged in CHANGELOG |

## 19. Milestones & Acceptance

- **M0 skeleton**: composer + namespaces + `SharedKernel` VOs + `*Exception` hierarchy + CI green (empty calculators throw `LogicException("not implemented")` — only allowed exception to this rule, removed in M1).
- **M1 prayer parity**: Appendix A frozen + checksummed; all methods, polar + high-latitude + adjustments + elevation + Ramadan; `TimetableService` + `NextPrayerResolver`; `tests/Goldens/prayer_*.csv` 100% minute-equality vs pinned Python.
- **M2 qibla+sunnah**: spherical + WGS84 (Karney spike accepted or Vincenty fallback recorded), magnetic hook, DST-safe sunnah + `forZone()`; goldens green.
- **M3 moon**: public `Moon/` facade + `CrescentGeometry` fields + Yallop/Odeh/MABIMS evaluators with cited vectors; no userland `Astro` imports.
- **M4 hijri**: Tabular + UQ (1356–1500 + provenance) + MABIMS + rollover (coords+params required) + `offsetDaysFor()`; bridge-parity tests with explicit-zone cases (naive-doc removed).
- **M5 hardening**: ArchTest + PHPStan max + php-cs-fixer clean + 95% coverage + Infection MSI≥85% on all math contexts + `bench --budget` gate + docs/examples + 1.0.0 tag.

Done = `composer ci` green on all matrix legs + minute-goldens 100% + float tolerances per §6 + `bench --budget` pass + docs build with relative links intact.

## 20. Decisions (former Open Questions — all resolved)

1. **CalculationMethod freeze:** FROZEN to 18 cases in Appendix A. JAKIM/KEMENAG/MUIS/BRUNEI rows MUST be dual-entered from `PYTHON_PIN:CalculationMethod.py` before M1; M1 blocked until checksum matches. No new method without Appendix A row + golden.
2. **`functions.php` shim:** REJECTED. Class-only API. Rationale: global `gregorian_to_hijri()` pollutes namespace, breaks static analysis, and duplicates `GregorianToHijriConverter::convert()`. Migration doc shows 3-line equivalent.
3. **`psr/clock`:** ACCEPT as `suggest` now. Domain never type-hints it; only `NextPrayerResolver::resolve(now)` callers and `examples/` MAY accept `Psr\Clock\ClockInterface` to supply `now`. No hard require.
4. **Umm al-Qura provenance:** SHIP with `UQ_PROVENANCE.md` header in repo + `UmmAlQuraData_*.php` file docblock: source (Saudi Umm al-Qura calendar, UmmAlQuraData source URL + access date), transformation script (`tools/build_uq.php`), SHA256 checksum, range `1356–1500 AH`, license note (facts not copyrightable; table transcription is independent entry — dual-entry reviewers sign off). If counsel flags redistribution risk, fallback is build-time downloader (still offline at runtime) — decision logged before M4.

---

## Appendix A — CalculationMethod defaults (normative, freeze before M1)

Dual-enter from `PYTHON_PIN:CalculationMethod.py`. Columns: `fajrAngle | ishaRule | maghribRule | imsakOff | ishraqOff | dhuhaOff | dhuhrOff | madhab | highLat | polar | methodAdj`. Initial transcription aid (VERIFY each cell against pin; checksum on merge):

| Method | fajr | isha | maghrib | imsak | ishraq | dhuha | dhuhr+ | madhab | HL | polar |
|---|---|---|---|---|---|---|---|---|---|---|
| NONE | — | — | — | — | — | — | 0 | SHAFI | NONE* | NONE |
| MUSLIM_WORLD_LEAGUE | 18.0 | Angle 17.0 | Sunset | 10 | 15 | 28 | 0 | SHAFI | MIDDLE | NEAREST_LAT |
| EGYPTIAN | 19.5 | Angle 17.5 | Sunset | 10 | 15 | 28 | 0 | SHAFI | MIDDLE | NEAREST_LAT |
| KARACHI | 18.0 | Angle 18.0 | Sunset | 10 | 15 | 28 | 0 | SHAFI | MIDDLE | NEAREST_LAT |
| UMM_AL_QURA | 18.5 | Interval 90/120 Ramadan | Sunset | 10 | 15 | 28 | 0 | SHAFI | MIDDLE | NEAREST_LAT |
| DUBAI | 18.2 | Angle 18.2 | Sunset | 10 | 15 | 28 | 0 | SHAFI | MIDDLE | NEAREST_LAT |
| MOON_SIGHTING_COMMITTEE | 18.0 | Angle 18.0 | Sunset | 10 | 15 | 28 | 0 | SHAFI | MIDDLE** | NEAREST_LAT |
| NORTH_AMERICA | 15.0 | Angle 15.0 | Sunset | 10 | 15 | 28 | 0 | SHAFI | MIDDLE | NEAREST_LAT |
| KUWAIT | 18.0 | Angle 17.5 | Sunset | 10 | 15 | 28 | 0 | SHAFI | MIDDLE | NEAREST_LAT |
| QATAR | 18.0 | Interval 90 | Sunset | 10 | 15 | 28 | 0 | SHAFI | MIDDLE | NEAREST_LAT |
| SINGAPORE | 20.0 | Angle 18.0 | Sunset | 10 | 15 | 28 | 0 | SHAFI | MIDDLE | NEAREST_LAT |
| TURKEY | 18.0 | Angle 17.0 | Sunset | 0*** | 15 | 28 | 0 | SHAFI*** | MIDDLE | NEAREST_LAT |
| TEHRAN | 17.7† | Angle 14.0† | Angle 4.5† | 10 | 15 | 28 | 0 | SHAFI | MIDDLE | NEAREST_LAT |
| MOROCCO | 19.0† | Angle 17.0† | Sunset | 10 | 15 | 28 | 0 | SHAFI | MIDDLE | NEAREST_LAT |
| JAKIM | 20.0 | Angle 18.0 | Sunset | 10 | 15 | 28 | 0 | SHAFI | MIDDLE | NEAREST_LAT |
| KEMENAG | 20.0 | Angle 18.0 | Sunset | 10 | 15 | 28 | 0 | SHAFI*** | MIDDLE | NEAREST_LAT |
| MUIS | 20.0 | Angle 18.0 | Sunset | 10 | 15 | 28 | 0 | SHAFI | MIDDLE | NEAREST_LAT |
| BRUNEI | 20.0 | Angle 18.0 | Sunset | 10 | 15 | 28 | 0 | SHAFI | MIDDLE | NEAREST_LAT |

`*` NONE requires caller-supplied rules (`defaultParameters()` throws `ConfigurationException`). `**` The MSC preset ships `MIDDLE_OF_NIGHT` (code wins); seasonal behavior engages via the method gate, not the portions gate: seasonal safe caps apply on every MSC row at ALL latitudes; the seventh-of-night temp replaces the angle temp only at signed latitude ≥ 55 (pin `_set_fajr`/`_set_isha` gate). `SEASONAL_TWILIGHT` rule mirrors MSC at all latitudes (same gating arm).
Corrections vs the transcription aid (pin wins, freeze `c151828`): ishraq 15 / dhuha 28 are the pin ctor defaults (aid said 0/15); `dhuhr+` is 0 everywhere — the aid column conflated `methodAdjustments.dhuhr` with the offset; KARACHI is SHAFI per pin (aid said HANAFI).
`methodAdjustments` exactly per pin: MWL/EGYPTIAN/KARACHI/NA/SINGAPORE/JAKIM `{dhuhr:+1}`; DUBAI `{sunrise:−3,dhuhr:+3,asr:+3,maghrib:+3}`; MSC `{dhuhr:+5,maghrib:+3}`; all other rows empty.
`***` TURKEY/KEMENAG/MUIS/BRUNEI were provisional at freeze, then confirmed against official tables (`28ff5ce`): TURKEY Diyanet temkin `{sunrise:−7,dhuhr:+5,asr:+4,maghrib:+7}` + imsak 0 (Imsak IS fajr) + SHAFI (Imamayn factor-1 ikindi); KEMENAG ihtiyat `{fajr:+2,sunrise:−2,dhuhr:+4,asr:+2,maghrib:+2,isha:+2}`; MUIS/BRUNEI `{dhuhr:+1}` zohor convention. Residual ±1 min cells emptied per file with reasons (`adee976`).
`†` TEHRAN/MOROCCO still PENDING (no obtainable official tables — NO preset change, no golden CSVs).
UOIF gap: pin-only method with no PHP preset (keeps the 18-freeze); lands as a future minor with an Appendix A amendment.
Every row needs a `tests/Goldens/prayer_{method}.csv` vector before M1 sign-off (15 present: 11 pin + turkey/kemenag/muis/brunei official-table; tehran + morocco pending).

## Appendix B — Formulas & constants (normative)

- Dip: `h0_deg = -0.833 - 0.0293 * sqrt(h_m)`.
- IUGG mean radius: `6371.0088 km`. WGS84: `a=6378.137 km, f=1/298.257223563`. Karney (2013) inverse, tol `1e-12`, max 100 iters.
- MSC seasonal twilight: `seasonalSeconds(dayOfYear, year, latitude): {morning, evening}` whole-second offsets (`int(round(adj·60))`) from Python `Twilight.py` — transcribe verbatim + cite; safe caps ungated, seventh-temp lat-gated, per §5.2.
- Crescent: `CrescentGeometry{sunAltitude, moonAltitude, elongationDeg, moonAgeHours, lagMinutes|null, deltaAzimuthDeg, illuminatedFraction, arcvGeoDeg, widthArcmin, ageAtMoonsetHours|null}` at local sunset (null pair = no-moonset window; sun geocentric per pin with parallax on the moon only; topo ARCV derived, never stored); Yallop `limit = −0.1018w³ + 0.7319w² − 6.3226w + 11.8371`, `q = (arcv_geo − limit)/10`, strict-`>` zones `0.216/−0.014/−0.160/−0.232/−0.293 → A/B/C/D/E`, else `F` (A–D visible, E/F invisible); Odeh `limit = −0.1018w³ + 0.7319w² − 6.3226w + 7.1651`, `V = arcv_topo − limit`, strict `arcl < 6.4 → D`, `V >= 5.65/2.0/−0.96 → A/B/C`, else `D`; MABIMS-1992 `(alt>=2 && elong>=3) || age_at_moonset>=8`, Neo-2021 `(alt>=3 && elong>=6.4)`. Service: `CrescentCalculator::atSunset(Coordinates, CivilDate): CrescentGeometry` + `evaluate(Coordinates, CivilDate): array{yallop, odeh, mabims1992, neomabims2021}`. Full polynomials + citations in `docs/moon.md`, vectors in `tests/Goldens/moon_*.csv`.
- Hijri Tabular epoch + leap set per §5.6; UQ range `1356–1500 AH` + checksum; DeltaT polynomial source + year range in `docs/hijri.md`.

## Appendix C — Output schemas (normative)

- `PrayerSchedule::all()` keys: `['imsak','fajr','sunrise','syuruk','ishraq','dhuha','dhuhr','asr','maghrib','isha']` in this order, values UTC `DateTimeImmutable`.
- `toArray(): array<string,string>` maps same keys to `Y-m-d\TH:i:s+00:00`. `DisplaySchedule::toArray()` same keys in display zone. JSON consumers use `json_encode($sched->toArray())`. No other keys allowed without minor bump.

---

*This PRD is the build contract. Code wins over prose on contradiction; update this file with the change when behavior intentionally diverges. Appendices A–C are normative and block M1 until frozen/checksummed.*
