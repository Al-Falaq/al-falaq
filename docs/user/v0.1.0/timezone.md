---
title: Timezones & Display
description: The UTC-inside, display-at-the-edge discipline — how to render
  times without corrupting them.
slug: v0.1.0/timezone
---

# Timezones & Display

Every timezone bug in prayer software has the same shape: zone arithmetic leaks into the astronomy, and a DST transition silently moves a prayer by an hour. al-falaq eliminates the category with one rule — **all domain math is UTC; zones appear only when you render**. This page shows what that means in practice.

## The rule, stated plainly

* **Inputs** are zone-free: `Coordinates` (degrees) and `CivilDate` (year/month/day). There is no timezone to get wrong.
* **Outputs** are UTC: every `PrayerSchedule` and `SunnahTimes` marker is a `DateTimeImmutable` in `+00:00`.
* **Display** converts last: `forZone()` maps each instant to the user's zone without recomputing anything.

```php
use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Enums\Prayer;
use AlFalaq\AlFalaq\Prayer\Services\PrayerTimesCalculator;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

$schedule = PrayerTimesCalculator::default()->calculate(
    Coordinates::fromDegrees(51.5074, -0.1278), // London
    CivilDate::fromYmd(2025, 10, 26),           // DST changeover day
    CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters(),
);

// UTC truth:
$schedule->timeFor(Prayer::FAJR); // +00:00, always

// Human rendering:
$display = $schedule->forZone(new DateTimeZone('Europe/London'));
$display->timeFor(Prayer::FAJR); // BST or GMT as appropriate — math untouched
```

Because the night that spans the October clock change is computed on UTC instants, Sunnah thirds land on the true middle of the night, not the clock-face middle. Because `CivilDate::fromDateTime()` reads the date in the instant's own zone, "today in Tokyo" and "today in Honolulu" correctly differ for the same UTC moment.

## The three conversions you need

```php
$schedule->all();     // ordered UTC instants, keyed by marker
$schedule->toArray(); // ISO-8601 strings in UTC — for JSON APIs and storage

$display = $schedule->forZone(new DateTimeZone('Asia/Kuala_Lumpur'));
$display->timeFor(Prayer::MAGHRIB)->format('H:i'); // for humans
$display->toArray();  // ISO-8601 strings in +08:00 — for zone-aware clients
```

Store and cache the UTC form; render the display form per request. The same schedule serves a traveller checking two zones without recomputation, and a cached timetable stays valid across a government's DST announcement because the instants never contained a zone.

## "Now" is yours to pass

The resolver takes the current instant as an argument, and requires it in UTC:

```php
$nowUtc = new DateTimeImmutable('now', new DateTimeZone('UTC'));
$next = (new NextPrayerResolver())->resolve($today, $tomorrow, $nowUtc);
```

Passing a zone-stamped "now" throws `ValidationException` immediately. That strictness catches the most common integration bug (a server-local `new DateTimeImmutable('now')` inheriting `date.timezone`) at the call site instead of shifting every badge by your UTC offset. If you use `psr/clock`, call `$clock->now()` at the edge and normalise to UTC before passing it in — the library never type-hints the interface, so any implementation works. Details are in [Framework Integration](integration/).

The Hijri bridge takes an instant too, but it is looser by design: it accepts any zone-aware `DateTimeImmutable` and reads whichever frame you asked for. Do not pre-normalise a wall-clock instant to UTC before handing it over — the default frame reads the civil date in the *instant's own zone*, so a needless `setTimezone()` near midnight changes the date it reports. Pass the instant your user's clock produced and let `changeAtSunset` choose the frame.

## Hijri frames revisited

The Hijri bridge's two frames are timezone decisions in disguise: the default frame reads the civil date in the instant's own zone (wall-clock semantics), while `changeAtSunset: true` reads it in UTC and rolls at the observer's Maghrib. If your datetimes arrive naive (no zone), give them the zone they were written in — PHP otherwise stamps them with `date.timezone`, and a local wall-clock time silently treated as UTC misplaces the rollover by your UTC offset. Prefer aware datetimes everywhere; the [Hijri Converter](hijri-converter/) page has examples.

## See also

* [Prayer Times](prayer-times/) — `forZone()` and the single-rounding pipeline
* [Timetable & Next Prayer](timetable/) — why both days matter for UTC-date boundaries
* [Framework Integration](integration/) — per-request zone handling in Laravel and Symfony
