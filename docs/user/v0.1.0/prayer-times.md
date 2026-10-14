---
title: Prayer Times
description: What each of the ten daily markers means, how al-falaq computes
  them, and how to adjust them.
slug: v0.1.0/prayer-times
---

# Prayer Times

A prayer timetable looks like a list of times. Underneath, it is a small astronomy pipeline: find the sun's path for the day, mark where it crosses a handful of angles, and round each crossing to the minute. This page explains each marker in plain language, shows how al-falaq derives it, and covers the adjustments you will actually reach for.

## The ten markers

A `PrayerSchedule` always carries the same ten keys, in this order:

| Marker | What it means | How it is derived |
| --- | --- | --- |
| `imsak` | The moment to stop eating before Fajr — a precautionary buffer, not a prayer | Final Fajr minus your `imsak` offset (default 10 min) |
| `fajr` | Dawn: the first light spreading horizontally before sunrise | Sun reaches the method's Fajr angle below the horizon (e.g. 18°) |
| `sunrise` | The sun's upper edge appears — end of the Fajr window | Geometric sunrise with dip-of-horizon correction for your elevation |
| `syuruk` | The Malaysian/Singaporean name for sunrise | The same instant as `sunrise` — an alias, not a second computation |
| `ishraq` | The sun has risen clear of the horizon haze; voluntary prayer may begin | Adjusted sunrise plus your `ishraq` offset (default 15 min) |
| `dhuha` | Mid-morning; marks the start of the Dhuha window | Adjusted sunrise plus your `dhuha` offset (default 28 min) |
| `dhuhr` | The sun crosses your meridian — midday prayer | Solar transit (highest point), plus any method offset |
| `asr` | Afternoon: shadows lengthen past their noon minimum | Shadow length equals the object's height times the madhab factor ([Madhab](madhab/)) |
| `maghrib` | Sunset prayer | Sunset, or a fixed angle below it for angle-based methods (Tehran uses 4.5°) |
| `isha` | Night prayer | The method's Isha angle below the horizon, or a fixed interval after Maghrib (Umm al-Qura, Qatar) |

If you only need the five obligatory prayers plus sunrise, read `fajr`, `sunrise`, `dhuhr`, `asr`, `maghrib`, and `isha` — the other four are conveniences built from the same pipeline.

## How the calculation works

It helps to picture the pipeline as five steps. You do not need to operate it — the defaults do — but knowing the shape makes every adjustment predictable:

1. **Check the sun is defined.** The engine asks whether sunrise and sunset exist for your coordinates and date. If they do not (polar day or night), the configured [polar fallback](polar-regions/) supplies substitute inputs, and the pipeline continues from there.
2. **Find the solar day.** From the sun's position (Meeus, *Astronomical Algorithms* 2nd ed., chapters 15 and 25) the engine derives solar transit, sunrise, and sunset for your elevation.
3. **Resolve the night portion.** Near the poles, Fajr and Isha angles may never occur. The [high-latitude rule](high-latitude/) decides what fraction of the night stands in for them.
4. **Compute each marker.** Fajr and Isha from their angles (or intervals), Dhuhr from transit, Asr from shadow length, Maghrib from sunset or its angle.
5. **Offset, then round once.** Method offsets and your own adjustments are added together first; then each marker is rounded to the minute, half-up. One rounding, at the end — so stacked adjustments never double-round.

```php
use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Enums\Prayer;
use AlFalaq\AlFalaq\Prayer\Services\PrayerTimesCalculator;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

$schedule = PrayerTimesCalculator::default()->calculate(
    Coordinates::fromDegrees(51.5074, -0.1278), // London
    CivilDate::fromYmd(2025, 6, 15),
    CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters(),
);

$schedule->timeFor(Prayer::DHUHR); // UTC DateTimeImmutable
```

### Why sunrise depends on elevation

Stand at sea level and the horizon sits at eye level. Climb a hill and the horizon dips, so you see the sun earlier at dawn and later at dusk. al-falaq applies the standard dip correction (`−0.833° − 0.0293°·√h`, with height in metres) to sunrise, sunset, and Maghrib. Fajr, Isha, and Dhuhr are unaffected — twilight angles and meridian transit do not move with the observer's height:

```php
$params = CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters()
    ->withElevationM(540.0); // e.g. Johannesburg plateau
```

Sea-level goldens are unchanged by this; elevated observers gain minutes at both ends of the day, exactly as the almanac predicts.

### Why there is one rounding, at the end

Consider Dubai, whose convention adds three minutes to Maghrib, plus your own two-minute safety adjustment. If each layer rounded separately, the result could drift by a minute from the true sum. Instead, al-falaq adds first (method offsets + your adjustments = one effective offset per marker) and rounds once, half-up. That is also why each layer is limited to ±60 minutes and the *sum* must stay within ±60 — the guard catches configuration mistakes before they reach the sky.

## Adjusting the schedule

Two mechanisms exist, and they stack in a defined order: **method adjustments** (the preset's baked-in administrative minutes, e.g. Dubai's `sunrise −3, dhuhr +3`) plus **your adjustments** (per-prayer safety minutes). Both apply before the single rounding:

```php
use AlFalaq\AlFalaq\Prayer\Enums\Prayer;
use AlFalaq\AlFalaq\Prayer\ValueObjects\PrayerAdjustments;

$params = CalculationMethod::DUBAI->defaultParameters()
    ->withAdjustments(PrayerAdjustments::empty()->with(Prayer::ISHA, 2));
```

The sunrise-derived markers have their own offsets rather than per-prayer adjustments: `withImsakOffset()`, `withIshraqOffset()`, `withDhuhaOffset()` (Turkey ships `imsak = fajr`, i.e. offset zero). `withDhuhrOffset()` accepts 0–5 minutes. Dhuha must never precede Ishraq, so the parameter object rejects `dhuha < ishraq` at construction — a configuration error caught in daylight, not at 5 a.m.

Ramadan in Makkah is the one interval exception worth knowing: under Umm al-Qura, Isha is Maghrib + 90 minutes normally and Maghrib + 120 minutes in Ramadan. The flag is caller-set (`withRamadan(true)`) and only valid with the Umm al-Qura method — any other method throws `ConfigurationException` immediately, because a Ramadan flag on the wrong method is always a bug:

```php
$params = CalculationMethod::UMM_AL_QURA->defaultParameters()->withRamadan(true);
```

## Reading and rendering

All ten instants are stored as UTC `DateTimeImmutable`. `all()` returns them keyed and ordered; `toArray()` maps the same keys to ISO-8601 strings for JSON APIs; `forZone()` converts every marker to a display zone without recomputing anything:

```php
$schedule->all();     // ['imsak' => DateTimeImmutable, ...] in canonical order
$schedule->toArray(); // ['imsak' => '2025-03-01T02:01:00+00:00', ...]

$display = $schedule->forZone(new DateTimeZone('Asia/Kuala_Lumpur'));
$display->toArray();  // same keys, '+08:00' offsets
```

Because zones appear only at this last step, the same schedule can be rendered for travellers in two zones without recomputation — and daylight-saving transitions cannot corrupt the underlying instants. The full discipline is in [Timezones & Display](timezone/).

## When there is no schedule

If the sun never rises or never sets and your polar rule is `NONE`, `calculate()` throws `UndefinedScheduleException` rather than returning a partial day. Catch it and decide what your product should do — the [Polar Regions](polar-regions/) page walks through the four strategies and their trade-offs. For high-latitude-but-not-polar places (say, London in June), the [High Latitude Rules](high-latitude/) page explains why Fajr and Isha need night fractions at all.

## See also

* [Calculation Methods](calculation-methods/) — the 19 presets and their provenance
* [Madhab](madhab/) — how Asr shadows work
* [Timetable & Next Prayer](timetable/) — month loops and "next prayer in…" badges
* [API Reference](api-reference/) — `PrayerTimesCalculator`, `PrayerSchedule`, `CalculationParameters`
