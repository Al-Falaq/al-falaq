# Architecture (condensed from Technical-PRD.md §7–8)

```
Domain (Contracts, VOs, Enums, Exceptions) has ZERO dependencies inward.
Application (Services) MAY depend on Domain + Astro-internal.
Astro-internal MAY depend on Domain VOs only, never on Application.
```

- Domain: `SharedKernel, Prayer, Qibla, Moon, Hijri` — zero I/O, trivial normalization only.
- Application: `*Calculator, *Converter, TimetableService, NextPrayerResolver` — orchestration, no trig.
- `Astro/` is `@internal` pure astronomy. Userland moon path is `Moon/` facade.
- Framework adapters live outside `src/`; recipes in `docs/integrations.md` (CI-tested).
