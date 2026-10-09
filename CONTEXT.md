# CONTEXT.md

## What this is

`al-falaq/al-falaq` — a port of Python `nexusnv/al-falak` (Islamic-astronomy
library: prayer times, Qibla, Sunnah night markers, crescent visibility,
Hijri conversion) to idiomatic PHP 8.2+, rebuilt architecturally (bounded
contexts + strategy polymorphism) instead of transliterated. Offline,
zero-runtime-dependency, stateless, framework-agnostic; targets plain PHP,
WordPress, and shared hosting as first-class consumers.

## Why it exists

One offline library any PHP app can drop in — no extensions beyond `date`,
no network, no framework — with behavioral parity to the pinned Python
implementation, proven by golden vectors.

## Current status (M0 skeleton)

Landed: composer scaffold, `SharedKernel` value objects (`Coordinates`,
`CivilDate`, `Angle`, `Minutes`, `JulianDay`), exception hierarchy,
namespace shells + throwing stubs for all contexts, PHPUnit suite (22
tests), ArchTest layering rules, coverage-ratchet gate (floor 20 → 95 at
M5), CI matrix. Next: M1 prayer pipeline (blocked on Appendix A freeze +
`PYTHON_PIN`).

## Stack & tooling

- Runtime: PHP >=8.2, no dependencies. Dev: PHPUnit 11, PHPStan max +
  strict-rules, php-cs-fixer (`@PER`), Rector, Infection (nightly; MSI≥85
  gate at M5). Coverage via pcov.
- Commands: `composer ci` (lint+cs+stan+test), `test-unit`, `arch`
  (`phpunit --group arch`), `bench --budget`.

## Repo map

- `src/{SharedKernel,Prayer,Qibla,Moon,Hijri}/` — public surface
  (`Contracts/` driving+driven ports, `Enums/`, `ValueObjects/`,
  `Services/`, `Strategies/`, `Traits/`, `Exceptions/`); `src/Astro/`
  `@internal` engine.
- `tests/` — `SharedKernel/`, `Sweep/`, `Wiring/`, `Goldens/`,
  `ArchTest.php`, `bootstrap.php`. `examples/` — runnable snippets +
  `bench.php`. `tools/` — `coverage_gate.php`, `build_uq.php` (M4),
  `export_goldens.py` (goldens from `PYTHON_PIN`).
- Docs: `Technical-PRD.md` (build contract), `ARCHITECTURE.md` (layering),
  `docs/` per-context guides + `integrations.md` (plain-`new` wiring),
  `AGENTS.md` (agent instructions), `UQ_PROVENANCE.md` (M4).

## Conventions that must not regress

- `*Interface` suffix on service contracts (PHP import-collision rule —
  see `AGENTS.md` gotchas); short names elsewhere.
- `Traits/`, never `Concerns/`; no `Util/`; no `*Error` names.
- `DateTimeImmutable` only; UTC math, `forZone()` at display edge; clock
  passed explicitly, never read in domain.
- Constructor injection with `default()` factories; no containers,
  providers, or framework types in `src/`.
- Coverage floor only moves up; lock stays PHP-8.2-installable via
  `config.platform`.
