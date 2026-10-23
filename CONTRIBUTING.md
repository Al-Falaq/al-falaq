# Contributing

## Setup

```bash
composer install
composer ci
```

## Rules

- `composer ci` must be green (`lint + cs + stan + test`).
- Every numeric golden vector cites Meeus page+ed. or Takwim URL+date + `PYTHON_PIN`.
- Public API = `src/{SharedKernel,Prayer,Qibla,Moon,Hijri}/{Contracts,Enums,ValueObjects,Services}` + documented `*Exception`s. `Astro` is `@internal`.
- One class per file, `final readonly` VOs, no `Util/` namespace, no `*Error` names.
- Update `docs/prayer.md` + goldens when behavior intentionally diverges (code wins, doc follows).

## Mutation gate

`composer mutation` runs the full Infection suite (2,378 mutants, about 10
minutes on a developer laptop — hardware-dependent). The repository sets
`config.process-timeout: 0` in `composer.json` so the script is not killed by
Composer's default 300-second process timeout; CI sets the same value through
`COMPOSER_PROCESS_TIMEOUT`. Run it before opening a pull request that changes
`src/` behaviour.
