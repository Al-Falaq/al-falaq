---
title: Polar Regions
description: What happens when the sun never rises or sets, and how to choose
  among the four fallback strategies.
slug: v0.1.1/polar-regions
---

# Polar Regions

High-latitude rules handle missing *twilight*. Polar handling handles missing *sunrise*. In Longyearbyen in June the sun circles the sky without setting; in December it never rises. There is no solar day to compute from — no transit, no sunrise, no sunset — so the pipeline must decide what to stand in for them before any prayer time can be derived.

al-falaq makes that decision explicit and configurable. There are four strategies, and `NONE` — refusing to guess — is one of them.

## The four strategies

| Rule | What it does | When to use it |
| --- | --- | --- |
| `NEAREST_LATITUDE` (default) | Bisects toward the equator until a solvable sunrise appears, then backs off 0.5° | General publishing — every shipped preset uses this |
| `NEAREST_DAY` | Keeps your location and scans ±366 days for the nearest solvable date | "What would today look like on the closest normal day?" |
| `MAKKAH` | Computes as if you were in Makkah (21.4225241, 39.8261818), keeping your date | Communities that follow Makkah times above the Arctic Circle |
| `NONE` | Throws `UndefinedScheduleException` immediately | When you would rather show "no times today" than an estimate |

```php
use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Enums\PolarCircleRule;

$params = CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters()
    ->withPolarCircleRule(PolarCircleRule::MAKKAH);
```

`NearestLatitude` works by 60-iteration bisection toward the equator: it walks your latitude south (or north, in the southern hemisphere) until sunrise exists, then steps 0.5° back so the substitute day is safely solvable rather than knife-edge. `NearestDay` scans day offsets outward from your date, testing the earlier date before the later one at each distance (`−1`, then `+1`, then `−2`, `+2`, up to ±366 days), so the substitute is always the nearest solvable date. `Makkah` substitutes the canonical Kaaba coordinates verbatim and keeps your date. `None` short-circuits before any dispatch and echoes your inputs into the exception, so the error message can name the rule that refused.

## Handling the exception well

When the sky has no answer and the rule is `NONE` — or when even a substitute cannot be solved (exactly at the poles, `NEAREST_DAY` finds no solvable date) — the calculator throws `UndefinedScheduleException`. This is not a bug to suppress; it is the library telling the truth. Design your UI around it:

```php
use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Enums\PolarCircleRule;
use AlFalaq\AlFalaq\Prayer\Exceptions\UndefinedScheduleException;
use AlFalaq\AlFalaq\Prayer\Services\PrayerTimesCalculator;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

$params = CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters()
    ->withPolarCircleRule(PolarCircleRule::NONE);

try {
    $schedule = PrayerTimesCalculator::default()->calculate(
        Coordinates::fromDegrees(78.2232, 15.6267), // Longyearbyen
        CivilDate::fromYmd(2025, 6, 21),            // midnight sun
        $params,
    );
} catch (UndefinedScheduleException $e) {
    // No solar day — show "no times today", or offer a strategy switcher.
}
```

The exception message names the active rule and points at this guide, so logs stay actionable. Qibla never raises polar errors — bearings exist everywhere — and the Hijri sunset-rollover bridge propagates an undefined Maghrib unchanged rather than guessing (see [Hijri Converter](hijri-converter/)).

## A note on honesty

Every polar strategy is an estimate wearing a timetable's clothes. Nearest-latitude borrows a neighbour's sun; nearest-day borrows a neighbour's date; Makkah borrows a different continent. Communities differ on which borrowing is acceptable, and some accept none. The library's job is to make the borrowing explicit (the rule is part of your parameters, visible in code review) and to refuse silently inventing times when you asked for none. Say which rule you use wherever you publish polar timetables — your users in Tromsø will thank you.

## See also

* [High Latitude Rules](high-latitude/) — missing twilight vs missing sunrise
* [Prayer Times](prayer-times/) — where polar resolution sits in the pipeline
* [Errors](errors/) — catching `UndefinedScheduleException` alongside the other failures
