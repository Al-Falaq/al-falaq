---
title: Calculation Methods
description: Compare all 19 prayer calculation methods, understand angles versus intervals, and pick the right preset.
---

# Calculation Methods

Every prayer timetable rests on a human convention: *how far below the horizon must the sun be for dawn to count as Fajr, and for night to count as Isha?* There is no single revealed angle — different scholarly bodies and national authorities adopted different ones, sometimes with extra administrative minutes. A calculation method is simply one such convention, frozen into numbers.

al-falaq ships 19 of them. This page explains what varies between them, gives you the full comparison table, and helps you choose.

## What varies: angles, intervals, and offsets

Most methods define Fajr and Isha as **angles** — the sun's depression below the horizon. Muslim World League says 18° for Fajr and 17° for Isha; Egypt says 19.5° and 17.5°; North America (ISNA) says 15° and 15°. A larger angle means an earlier Fajr and a later Isha.

Two methods instead define Isha as an **interval** after Maghrib: Umm al-Qura (90 minutes, 120 in Ramadan) and Qatar (90 minutes). Interval Isha ignores twilight angles entirely — and it also ignores the high-latitude night-fraction safety caps, because a fixed interval needs no night to be meaningful.

On top of the astronomy, some presets carry **method adjustments**: fixed administrative minutes inherited from official timetables. Dubai subtracts 3 from sunrise and adds 3 to Dhuhr, Asr, and Maghrib. The Moonsighting Committee adds 5 to Dhuhr and 3 to Maghrib. Singapore, JAKIM, MUIS, and Brunei add 1 to Dhuhr (the well-known `+1` zohor convention). Your own adjustments stack on top — see [Prayer Times](prayer-times/).

## The full table

All presets share the same skeleton — Shafi madhab, middle-of-night high-latitude rule, nearest-latitude polar fallback, `ishraq +15`, `dhuha +28`, sea level — and differ only where listed:

| Method | Fajr | Isha | Maghrib | Imsak | Method adjustments |
| --- | --- | --- | --- | --- | --- |
| Muslim World League | 18° | 17° angle | sunset | Fajr−10 | Dhuhr +1 |
| Egyptian | 19.5° | 17.5° angle | sunset | Fajr−10 | Dhuhr +1 |
| Karachi | 18° | 18° angle | sunset | Fajr−10 | Dhuhr +1 |
| Umm al-Qura | 18.5° | 90 min interval (120 in Ramadan) | sunset | Fajr−10 | — |
| Dubai | 18.2° | 18.2° angle | sunset | Fajr−10 | Sunrise −3, Dhuhr +3, Asr +3, Maghrib +3 |
| Moonsighting Committee | 18° | 18° angle | sunset | Fajr−10 | Dhuhr +5, Maghrib +3 |
| North America (ISNA) | 15° | 15° angle | sunset | Fajr−10 | Dhuhr +1 |
| Kuwait | 18° | 17.5° angle | sunset | Fajr−10 | — |
| Qatar | 18° | 90 min interval | sunset | Fajr−10 | — |
| Singapore | 20° | 18° angle | sunset | Fajr−10 | Dhuhr +1 |
| Turkey (Diyanet) | 18° | 17° angle | sunset | = Fajr | Sunrise −7, Dhuhr +5, Asr +4, Maghrib +7 |
| Tehran | 17.7° | 14° angle | 4.5° angle | Fajr−10 | — |
| Morocco (Habous) | 19° | 17° angle | sunset | Fajr−10 | — |
| JAKIM (Malaysia) | 20° | 18° angle | sunset | Fajr−10 | Dhuhr +1 |
| Kemenag (Indonesia) | 20° | 18° angle | sunset | Fajr−10 | Fajr +2, Sunrise −2, Dhuhr +4, Asr +2, Maghrib +2, Isha +2 |
| MUIS (Singapore) | 20° | 18° angle | sunset | Fajr−10 | Dhuhr +1 |
| Brunei (KHEU) | 20° | 18° angle | sunset | Fajr−10 | Dhuhr +1 |
| UOIF (France) | 12° | 12° angle | sunset | Fajr−10 | — |
| None | — | — | — | — | No defaults; build `CalculationParameters::create()` by hand |

```php
use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;

$params = CalculationMethod::JAKIM->defaultParameters();
$params = CalculationMethod::UOIF->defaultParameters();
```

`NONE` is the deliberate escape hatch: `defaultParameters()` throws `ConfigurationException` on it, because "no convention" has no defaults. Build a fully custom configuration with `CalculationParameters::create()` instead.

## Choosing a method

Pick the method your community already follows — this is a question of trust, not mathematics:

- **Malaysia:** `JAKIM` (20°/18°, the e-solat baseline; official tables may add minute-level administrative tweaks on top).
- **Singapore:** `SINGAPORE` or `MUIS` (same 20°/18° computation; separate presets exist for operational naming).
- **Indonesia:** `KEMENAG` (20°/18° with the Kemenag ihtiyat offsets baked in).
- **Brunei:** `BRUNEI`.
- **France (UOIF mosques):** `UOIF` — a pure 12°/12° preset with sunset Maghrib and zero offsets.
- **Makkah and Saudi Arabia:** `UMM_AL_QURA`, with `withRamadan(true)` during Ramadan for the 120-minute Isha.
- **Gulf:** `DUBAI` or `QATAR` depending on emirate and convention.
- **North America:** `NORTH_AMERICA` (ISNA 15°/15°).
- **General default when unsure:** `MUSLIM_WORLD_LEAGUE`.

JAKIM, Singapore, MUIS, and Brunei share the same 20°/18° twilight computation by design — the separate presets exist for Malaysian, Singaporean, and Bruneian operations (naming, zone handling, timetable alignment), not because the sky differs.

## Provenance notes worth knowing

A timetable is a claim about what an authority publishes, so al-falaq records where each preset's numbers come from — and where the evidence runs out:

- **Dubai's 18.2° angles and minute offsets** are app-level tuning carried over from BatoulApps research, not an official IACAD specification. Treat them as conventional.
- **Qatar follows the library-canonical 18° + 90-minute definition.** Some current Doha publications cite 18.5°; that conflict is recorded and left unresolved pending a primary source.
- **Turkey's offsets** (sunrise −7 through Maghrib +7) encode the Diyanet temkin convention, confirmed against official tables.
- **Tehran (17.7°/14°, Maghrib 4.5° angle)** is cross-checked against the BatoulApps reference implementation and drift-gated; the 14° Isha is a convention, not a published official rule.
- **Morocco (19°/17°)** is the Habous convention with Fajr 18-vs-19 undecided in the sources — the preset records the 19° reading.
- **UOIF (12°/12°)** is cited to the 1994 Dar el-Fatwa text, the 2012 UOIF presidential statement, and ECFR Dublin 2004 toleration, communally confirmed as current in 2026. The 14°/15° compromise proposal from 2015 never landed, so the preset carries no ±5-minute adjustments.

Every numeric behaviour is pinned against golden vectors with cited sources (Meeus page or official-table URL plus date). If a future official table contradicts a preset, the docs and goldens move together — code wins, docs follow.

## The Moonsighting Committee difference

Unlike every other method, `MOON_SIGHTING_COMMITTEE` does not use night-fraction safety caps. It replaces them with season-adjusted twilight curves: Fajr is capped by a seasonal morning curve and Isha floored by a seasonal evening curve, both derived from 1/7-of-night shapes. At latitudes at or above 55°, the angle-derived times are additionally replaced by 1/7-of-night estimates *before* the seasonal cap applies. Elsewhere the angle computation stands unless it breaches the cap. The mechanics are in [High Latitude Rules](high-latitude/); the practical point is that MSC timetables behave differently through the year by design.

## Custom parameters and precedence

Override any preset's defaults immutably. When you pass both, be aware the method's built-ins are the starting point and your `with*()` calls replace them wholesale per field:

```php
use AlFalaq\AlFalaq\Prayer\ValueObjects\CalculationParameters;

$params = CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters()
    ->withFajrAngle(18.0)
    ->withElevationM(25.0);
```

`isRamadan` deserves a special mention: setting it on anything but Umm al-Qura throws at parameter-build time, never at calculation time. A Ramadan flag on the wrong method is always a wiring bug, and al-falaq would rather fail in your setup code than at dawn.

## See also

- [Prayer Times](prayer-times/) — how each marker is derived and adjusted
- [Madhab](madhab/) — the Asr shadow factor
- [High Latitude Rules](high-latitude/) — night fractions and seasonal twilight
- [Citations](citations/) — every source behind the table
