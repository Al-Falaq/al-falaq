---
title: Getting Started
description: Install al-falaq and run your first prayer time calculation in plain PHP.
slug: v0.1.0/getting-started
---

# Getting Started

This page takes you from an empty directory to your first correct prayer time in about five minutes. Along the way you will meet the three objects you will use everywhere — coordinates, a civil date, and calculation parameters — and you will learn why the library insists on UTC.

## Install

al-falaq is a regular Composer package with no runtime dependencies:

```bash
composer require al-falaq/al-falaq
```

That is the whole installation. There are no extensions to enable beyond PHP's own `date`, no services to start, and nothing to configure. The library works offline because everything it needs — the astronomy and the calendar tables — ships inside the package.

It requires PHP `>= 8.2` on a 64-bit build.

## Your first calculation

Prayer times answer a simple question: *for this place, on this day, by this community's convention, when does each prayer begin?* Three inputs, one schedule out. Every snippet on this page continues one file, so load Composer's autoloader once at the top:

```php
require __DIR__ . '/vendor/autoload.php';
```

```php
use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Enums\Prayer;
use AlFalaq\AlFalaq\Prayer\Services\PrayerTimesCalculator;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

$calculator = PrayerTimesCalculator::default();
$params = CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters();

$schedule = $calculator->calculate(
    Coordinates::fromDegrees(3.1390, 101.6869), // Kuala Lumpur
    CivilDate::fromYmd(2025, 3, 1),
    $params,
);

$display = $schedule->forZone(new DateTimeZone('Asia/Kuala_Lumpur'));

echo $display->timeFor(Prayer::FAJR)->format('H:i');    // 06:15
echo $display->timeFor(Prayer::MAGHRIB)->format('H:i'); // 19:27
```

Notice what is *not* in this snippet: no timezone on the way in, no "now", no framework bootstrap. The calculator takes a place (`Coordinates`), a calendar day (`CivilDate`), and a convention (`CalculationParameters`), and returns a `PrayerSchedule` holding ten UTC instants. You convert to a display zone only at the very end with `forZone()`. That separation — UTC for math, zones for display — is the single most important habit in this library, and [Timezones & Display](timezone/) explains why it saves you from daylight-saving bugs.

## The three inputs, properly understood

**Where: `Coordinates`.** Latitude and longitude in degrees. Construction validates immediately — a latitude of 95 or a non-numeric string throws `InvalidCoordinatesException` with a message that names the offending axis, rather than producing a quietly wrong time:

```php
$kl = Coordinates::fromDegrees(3.1390, 101.6869);
```

If your coordinates arrive as a plain pair (from a database row or a request payload), `Coordinates::fromArray([3.1390, 101.6869])` accepts exactly that shape and rejects associative arrays — a deliberate guard against swapped or mislabelled keys.

**When: `CivilDate`.** A year, month, and day — not a timestamp. Prayer times are computed per civil day (1900–2100), so the library models the day itself rather than smuggling it inside a datetime:

```php
$date = CivilDate::fromYmd(2025, 3, 1);
```

When you already hold a `DateTimeImmutable` (for example, "today in the user's zone"), `CivilDate::fromDateTime($instant)` reads the calendar date in that instant's own zone. The clock is always yours to read; the domain never calls "now" itself.

**How: `CalculationParameters`.** Each community's convention — which twilight angles define Fajr and Isha, which madhab defines Asr, how to behave near the poles — frozen into one object. You almost never build it by hand; you ask a method for its defaults:

```php
$params = CalculationMethod::SINGAPORE->defaultParameters();
```

The full comparison lives in [Calculation Methods](calculation-methods/). For now, pick the method your community already uses. If you serve Malaysia, that is `JAKIM`; France, `UOIF`; the Gulf, `DUBAI` or `QATAR` — the table tells you which.

## Reading the answer

A `PrayerSchedule` holds ten markers in a fixed order: `imsak, fajr, sunrise, syuruk, ishraq, dhuha, dhuhr, asr, maghrib, isha`. Every one is a UTC `DateTimeImmutable`:

```php
foreach ($schedule->all() as $name => $instant) {
    echo $name . ' ' . $instant->format('H:i') . PHP_EOL;
}

// Or encode the whole day for an API consumer:
json_encode($schedule->toArray());
```

For humans, convert once at the edge:

```php
$display = $schedule->forZone(new DateTimeZone('Asia/Kuala_Lumpur'));
$display->timeFor(Prayer::ISHA)->format('H:i');
```

`syuruk` is the Malaysian/Singaporean name for sunrise — the same instant object, not a second computation. `imsak` defaults to ten minutes before Fajr, `ishraq` to fifteen minutes after sunrise, and `dhuha` to twenty-eight minutes after sunrise; all three offsets are configurable. [Prayer Times](prayer-times/) explains what each marker means and how it is derived.

## Customising one thing

Presets are starting points, not cages. Every parameter object is immutable, so customisation reads as a chain of `with*()` calls that each return a new object:

```php
$params = CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters()
    ->withElevationM(15.0); // observer 15 m above sea level
```

Elevation matters because the horizon dips as you climb — at 1,000 m, sunrise comes minutes earlier. Madhab, high-latitude rule, polar fallback, and per-prayer minute adjustments all work the same way. The rule of thumb: choose the closest preset, then adjust the one thing that differs.

## What to do when the sky has no answer

Above the Arctic Circle in summer, the sun may never set; in winter, it may never rise. When no sunrise or sunset exists for your inputs, the calculator does not invent one — it throws `UndefinedScheduleException`, and you choose a fallback strategy ([Polar Regions](polar-regions/)):

```php
use AlFalaq\AlFalaq\Prayer\Exceptions\UndefinedScheduleException;

try {
    $schedule = $calculator->calculate($coords, $date, $params);
} catch (UndefinedScheduleException $e) {
    // Switch strategy, show a notice, or fall back to the nearest solvable day.
}
```

This is deliberate. A wrong prayer time presented confidently is worse than an honest exception.

## Next steps

* [Prayer Times](prayer-times/) — what each of the ten markers means and how it is computed
* [Calculation Methods](calculation-methods/) — compare all 19 methods and pick yours
* [Timezones & Display](timezone/) — the UTC-in, display-at-the-edge discipline
* [Errors](errors/) — the full exception hierarchy and how to catch well
* [Framework Integration](integration/) — wiring this into Laravel, Symfony, and WordPress
