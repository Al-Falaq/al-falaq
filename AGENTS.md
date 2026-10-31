# AGENTS.md

## Project Overview

`al-falaq/al-falaq` — offline, zero-dependency (`require: php >=8.2` only),
stateless, framework-agnostic PHP 8.2+ library for Islamic astronomy: prayer
times, Qibla, Sunnah markers, crescent visibility, Hijri conversion.
The shipped code, its golden tests, and the open issues are the contract of record. Status: **M5 landed** — `SharedKernel` value
objects, exception hierarchy, prayer pipeline (`PrayerTimesCalculator`),
monthly timetable (`TimetableService`), next-prayer resolver
(`NextPrayerResolver`), Qibla (spherical + WGS84/Karney), Sunnah markers
(incl. `nightFraction`), crescent visibility evaluators, and Hijri calendars
(tabular, Umm al-Qura, per-country MABIMS + Gregorian bridge) shipped
(calculation-method defaults frozen + milestone amendments; no deferred-context
`LogicException` markers remain in `src/` — the final no-throw sweep was an
M5 gate). Public surface is `src/{SharedKernel,Prayer,Qibla,
Moon,Hijri}/`; `src/Astro/` is `@internal`.

## Setup Commands

- `composer install` (platform pinned to PHP 8.2.0 via `config.platform` so
  the lock stays installable on 8.2/8.3/8.4 — never resolve the lock on a
  newer PHP without the pin)
- `composer validate --strict` must pass before commit (lock hash included)

## Development Workflow

- Canonical gate: `composer ci` = `lint + cs + stan + changelog:check + test`
- `composer bench` — performance tripwire harness (`--budget` enforces the loose
  50ms/30000ms tripwires; the printed 2ms/1000ms/16MB PRD budgets are headroom
  references, gated later — see `examples/bench.php`)
- Single-command equivalents: `composer lint`, `composer cs` (`cs-fix` to
  write), `composer stan`, `composer test-unit` (fast, no coverage),
  `composer test` (coverage ratchet gate), `composer arch`
- CI (`.github/workflows/test.yml`, ubuntu-only): lint/cs/stan once on PHP 8.4,
  then test + bench on PHP 8.2/8.3/8.4 × UTC plus one America/New_York leg on
  8.4. `.github/workflows/pre-release.yml` holds the Windows gate (manual
  dispatch + `v*` tags) and, on a tag, creates the GitHub Release after that
  gate passes (CHANGELOG header + docs snapshot validated first) —
  `release.yml` is gone; do not reintroduce a second tag-triggered workflow.
- Actions are pinned to full commit SHAs with a `# vX.Y.Z` comment; Dependabot
  (`.github/dependabot.yml`, github-actions, weekly) refreshes them. Keep the
  comment when bumping by hand — it is what Dependabot tracks.
- `composer mutation` — Infection gate (MSI ≥ 85, about 10 min). `composer.json`
  sets `config.process-timeout: 0` so the script survives Composer's default
  300 s process timeout; CI sets the same via `COMPOSER_PROCESS_TIMEOUT`.
- `composer changelog` regenerates `docs/user/changelog.md` from `CHANGELOG.md`;
  `composer changelog:check` fails when the published page is stale (also in the
  release gate). Never hand-edit the generated page.

## Testing Instructions

- Plain PHPUnit 11, no ecosystem runner. Bootstrap `tests/bootstrap.php`
  fixes UTC; `Sweep/` seeds `mt_srand(1234)` inline per test.
- Layout mirrors `src/` as contexts land (M1: `SharedKernel`, `Sweep`,
  `Wiring`, `Prayer`, `Astro`, `Goldens`); `tests/Goldens/` holds pinned-Python parity vectors +
  `GoldenLoader.php` (load), `GoldenAsserts.php` (assert), `Comparator.php`
  (minute equality); arch groups (`tests/Arch{Shape,Docs,Layering,ApiReference}Test.php`,
  shared `tests/ArchSupport.php`) enforce layering, shape and
  public-API-reference rules (`#[Group('arch')]`).
- Coverage floor is a ratchet (`tools/coverage_gate.php --min=N`): M0
  baseline 20, raised per milestone, 95 at M5. Never lower the floor; raise
  it when a context lands. Without pcov/xdebug the gate passes on green
  tests with a notice (CI always provides pcov).
- Every numeric golden vector must cite Meeus page+ed. or Takwim URL+date +
  `PYTHON_PIN` (see `CONTRIBUTING.md`).

## Code Style

- `declare(strict_types=1)` everywhere; PER style via php-cs-fixer
  (`.php-cs-fixer.php`, `@PER` — a strict superset of PSR-12); PHPStan max +
  strict-rules clean.
- Inline docs are a hard gate (`tests/ArchDocsTest.php`, `#[Group('arch')]`):
  every class-like and public/protected method in `src/` needs a docblock
  summary; every parameter needs `@param $name`; every declared non-void
  return needs `@return`. Engine-provided enum methods (`cases/from/tryFrom`)
  and `tests/` are exempt. Docblock types must resolve (PHPStan validates
  them) — use FQCN where the file has no import.
- `src/{Context}/{Role}/`, one class per file, PSR-4 `AlFalaq\AlFalaq\`.
- `Contracts/`: interfaces only — driving ports (service API,
  `*Interface` suffix) and driven ports (strategy SPIs, short names).
  Never add an `Interface` suffix outside service contracts; never strip one.
- `Services/`: `final`, explicit ctor injection, `default()` factory, single
  command method. Two documented exceptions: `NightResolver` is an internal
  step wired by `PrayerTimesCalculator::default()` (no standalone factory),
  and `SunnahCalculator` needs the day's schedules, so it is built through
  `fromCalculator()`. `Astro/Services/` is internal engine wiring and is
  exempt from the factory convention (the stable `MeanSolarCalculator`
  adapter is constructed with `new`). `Strategies/`: `final`, one
  `Contracts/` interface each. `ValueObjects/`: `final readonly`, validated
  ctor, UTC math only (the stateless static holders `Kaaba` and `Minutes`
  stay plain `final`). `Traits/` (named `Traits/`, not `Concerns/`):
  stateless, `private` helpers (the two Hijri calendar traits contribute
  public `HijriCalendar` methods by design).
  `Exceptions/`: `*Exception` suffix, extend `AlFalaqException`; `*Error`
  names forbidden.
- Pure domain: SPL + `\DateTimeImmutable` only (mutable `DateTime` and
  `DateTimeInterface` unions banned by ArchShapeTest); no I/O, no network
  (`curl_`, `exec`, `shell_exec`, http URLs banned); no `Util/` namespace;
  userland must not import `Astro/` (stable exception: the `SolarDayEngine`
  port + `MeanSolarCalculator` adapter for explicit Prayer wiring — see
  `docs/integrations.md`). Clock never read inside domain —
  pass `$nowUtc` explicitly.
- No framework types in `src/` (`Illuminate/*`, framework adapters live
  outside this repo; wiring recipes go in `docs/integrations.md` as
  plain-`new` snippets, smoke-tested by `tests/Wiring/`).

## Build and Deployment

- No build step; `type: library`, MIT. `composer.json` runtime must stay
  `php`-only (CI asserts zero locked runtime packages).
- `autoload.exclude-from-classmap: tests/, examples/`.

## Pull Request Guidelines

- `composer ci` green on all matrix legs before merge; include test evidence.
- Update `docs/prayer.md` + goldens when behavior intentionally
  diverges (code wins, doc follows). Keep `ARCHITECTURE.md`,
  `docs/integrations.md`, `AGENTS.md`, `CONTEXT.md` in sync with structure.

## Additional Notes

- Golden generators: full inventory + contracts in `ARCHITECTURE.md`
  (`composer goldens` lists them); `tools/golden_cities.py` shares the
  Qibla/Moon city grids.
- Gotcha: same short names in `Contracts/` + `Services/` namespaces cannot
  share one file's imports (PHP fatal) — that is why service contracts keep
  the `Interface` suffix. Do not "simplify" it away.
- Gotcha: `composer update` without `config.platform.php=8.2.0` resolves
  8.4-only deps and breaks the 8.2/8.3 CI legs.
