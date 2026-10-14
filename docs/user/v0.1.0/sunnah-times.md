---
title: Sunnah Times
description: Divide the night into its Sunnah markers — middle, thirds, and the
  tahajjud window.
slug: v0.1.0/sunnah-times
---

# Sunnah Times

Between Maghrib and the next dawn, the night carries its own devotional geography: its middle, its thirds, and the last-third window most associated with tahajjud. These are not astronomical events like sunrise — they are *fractions of the night*, and al-falaq treats them exactly that way.

## The night, defined

The night runs from **today's Maghrib to tomorrow's Fajr**, computed in UTC so daylight-saving transitions cannot stretch or shrink it. From that single interval come three markers:

* **Middle of the night** — the halfway point (½).
* **First third** — one third of the way through (⅓).
* **Last third** — two thirds of the way through (⅔); the tahajjud window runs from here until Fajr.

```php
use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Services\PrayerTimesCalculator;
use AlFalaq\AlFalaq\Prayer\Services\SunnahCalculator;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

$coords = Coordinates::fromDegrees(3.1390, 101.6869);
$date = CivilDate::fromYmd(2025, 3, 1);
$params = CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters();

$sunnah = SunnahCalculator::fromCalculator(
    PrayerTimesCalculator::default(), $coords, $date, $params,
)->calculate();

$sunnah->middleOfNight; // UTC DateTimeImmutable
$sunnah->lastThird;     // tahajjud begins here
$sunnah->tahajjudWindow(); // ['from' => lastThird, 'to' => fajr]
```

`fromCalculator()` derives tomorrow's schedule internally (via `CivilDate::plusDays(1)`), so you never assemble the pair by hand. Or construct it explicitly when you already hold both days:

```php
$sunnah = (new SunnahCalculator($today, $tomorrow))->calculate();
```

## Why UTC math matters here

A night that spans a DST change (say, in London in October) is an hour longer on the wall clock than in absolute time. By computing fractions on UTC instants and converting to a display zone only at the end, the markers land on the true middle and thirds rather than the clock-face ones. `forZone()` converts every marker while preserving the instants:

```php
$display = $sunnah->forZone(new DateTimeZone('Asia/Kuala_Lumpur'));
```

## Arbitrary fractions

Beyond the named thirds, `nightFraction()` returns the instant at any strictly interior fraction — `0.5` is the middle, `1/3` and `2/3` are the thirds, and anything else in `(0, 1)` is yours to name. The result is minute-rounded and then clamped strictly inside the night, so `maghrib < result < fajr` holds for every valid fraction on nights longer than two minutes. A night shorter than that has no minute-aligned instant strictly inside it at all, and the clamp then returns the minute-aligned instant one minute past the nearer anchor rather than inventing a sub-minute time between them:

```php
$moment = $sunnah->nightFraction(0.5); // === middleOfNight
```

Fractions outside `(0, 1)` throw `ValidationException` — a fraction of 0 or 1 is an anchor, not a fraction, and the library says so. Degenerate nights (Fajr at or before Maghrib, which only arise from inconsistent hand-assembled schedules) are rejected rather than clamped, because clamping would hide the wiring bug.

If either endpoint is polar-undefined, the prayer pipeline throws first — `SunnahCalculator` never invents a night. That composes well with [Polar Regions](polar-regions/): choose a polar rule for the underlying prayer calculation, and the Sunnah markers follow.

## See also

* [Prayer Times](prayer-times/) — Maghrib and Fajr, the night's anchors
* [Timezones & Display](timezone/) — why night math is UTC and display is last
* [API Reference](api-reference/) — `SunnahCalculator`, `SunnahTimes::nightFraction()`
