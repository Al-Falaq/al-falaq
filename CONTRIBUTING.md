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
