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

- Canonical gate: `composer ci` = `lint + cs + stan + test`
- `composer bench` — performance tripwire harness (`--budget` enforces the loose
  50ms/30000ms tripwires; the printed 2ms/1000ms/16MB PRD budgets are headroom
  references, gated later — see `examples/bench.php`)
- Single-command equivalents: `composer lint`, `composer cs` (`cs-fix` to
  write), `composer stan`, `composer test-unit` (fast, no coverage),
  `composer test` (coverage ratchet gate), `composer arch`
- CI (`.github/workflows/test.yml`, ubuntu-only): lint/cs/stan once on PHP 8.4,
  then test + bench on PHP 8.2/8.3/8.4 × UTC plus one America/New_York leg on
  8.4. Windows gate lives in `.github/workflows/pre-release.yml` (manual
  dispatch + `v*` tags) and runs as a pre-release check.

## Testing Instructions

- Plain PHPUnit 11, no ecosystem runner. Bootstrap `tests/bootstrap.php`
  fixes UTC; `Sweep/` seeds `mt_srand(1234)` inline per test.
- Layout mirrors `src/` as contexts land (M1: `SharedKernel`, `Sweep`,
  `Wiring`, `Prayer`, `Astro`, `Goldens`); `tests/Goldens/` holds pinned-Python parity vectors +
  `Comparator.php` (minute equality); arch groups (`tests/Arch{Shape,Docs,Layering}Test.php`,
  shared `tests/ArchSupport.php`) enforce layering via reflection (`#[Group('arch')]`).
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
  command method. `Strategies/`: `final`, one `Contracts/` interface each.
  `ValueObjects/`: `final readonly`, validated ctor, UTC math only.
  `Traits/` (named `Traits/`, not `Concerns/`): stateless, `private` helpers.
  `Exceptions/`: `*Exception` suffix, extend `AlFalaqException`; `*Error`
  names forbidden.
- Pure domain: SPL + `\DateTimeImmutable` only (mutable `DateTime` and
  `DateTimeInterface` unions banned by ArchTest); no I/O, no network
  (`curl_`, `exec`, `shell_exec`, http URLs banned); no `Util/` namespace;
  userland must not import `Astro/`. Clock never read inside domain —
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

- `tools/export_goldens.py` regenerates parity vectors from `PYTHON_PIN`;
  `tools/gen_hijri.py` builds the pin-generated Hijri month-start tables + goldens (M4).
- Gotcha: same short names in `Contracts/` + `Services/` namespaces cannot
  share one file's imports (PHP fatal) — that is why service contracts keep
  the `Interface` suffix. Do not "simplify" it away.
- Gotcha: `composer update` without `config.platform.php=8.2.0` resolves
  8.4-only deps and breaks the 8.2/8.3 CI legs.
