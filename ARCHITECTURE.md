# Architecture (layering summary)

```
Domain (Contracts, VOs, Enums, Exceptions) has ZERO dependencies inward.
Application (Services) MAY depend on Domain + Astro-internal.
Astro-internal MAY depend on Domain VOs only, never on Application.
```

- Domain: `SharedKernel, Prayer, Qibla, Moon, Hijri` — zero I/O, trivial normalization only.
- `Contracts/` holds both port kinds: **driving ports** (service API callers invoke: `PrayerTimesCalculatorInterface`, `TimetableServiceInterface`, `NextPrayerResolverInterface`; plus concrete entry points `QiblaCalculator`, `GregorianToHijriConverter`, `CrescentCalculator`) and **driven ports** (strategy SPIs callers select or implement: `*Rule`, `*Correction`, `*Handler`, `TwilightModel`, `GeodesyCalculator`, `HijriCalendar`, `OffsetStore`, `CrescentEvaluator`). Driving-port interfaces are implemented once in `Services/`; driven ports have ≥1 implementation in `Strategies/` (or are caller-supplied, e.g. `OffsetStore`).
- Application: `*Calculator, *Converter, TimetableService, NextPrayerResolver` — orchestration, no trig.
- `Astro/` is `@internal` pure astronomy. Userland moon path is `Moon/` facade.
- Host-app wiring lives outside `src/`; `docs/integrations.md` shows plain-`new` construction (smoke-tested by `tests/Wiring/`).
