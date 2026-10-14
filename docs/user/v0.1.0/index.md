---
title: al-falaq
description: An offline PHP library for Islamic astronomy — prayer times, Qibla,
  Sunnah markers, crescent visibility, and Hijri conversion.
slug: v0.1.0
---

# al-falaq

An offline, zero-dependency PHP library for Islamic astronomy. Give it a place and a date, and it tells you when to pray, which way to face, how the night is divided, whether the new crescent can be seen, and what the Hijri date is — with no network calls, no API keys, and no surprises.

If you have used the Python [`al-falak`](https://nexusnv.github.io/al-falak/) library, this will feel familiar: the PHP port keeps behavioural parity with it, proven by shared golden vectors. What is different is the shape — idiomatic PHP 8.2+, immutable value objects, and explicit construction that works the same in plain PHP, Laravel, Symfony, or WordPress.

## Who is this for?

| Audience | What you get |
| --- | --- |
| **PHP developers** | Prayer times, timetables, and Qibla you can drop into any app with `composer require` |
| **Laravel / Symfony teams** | Framework-agnostic services with plain-`new` wiring — no bundles, no config files, no facades to fight |
| **WordPress / shared-hosting builders** | One package, no extensions beyond `date`, no network — it runs anywhere PHP 8.2 runs |
| **Researchers and timetable publishers** | Deterministic output pinned against reference vectors, with provenance you can cite |

## What al-falaq does — and does not do

Prayer time libraries live at the boundary between astronomy and religious practice. It helps to be precise about where this one stops.

| Does | Does not |
| --- | --- |
| Compute all ten daily markers (Fajr, Sunrise, Dhuhr, Asr, Maghrib, Isha — plus Imsak, Syuruk, Ishraq, Dhuha) | Require network access or a location API |
| Ship 19 calculation methods (Muslim World League, ISNA, Egyptian, Karachi, Umm al-Qura, Dubai, Moonsighting Committee, Singapore, JAKIM, Turkey, Morocco, UOIF, and more) | Issue fiqh rulings — angles are conventions, and the docs say whose convention each one is |
| Handle polar days and nights with four explicit strategies | Guess when the sky has no answer — it throws a typed exception instead |
| Return UTC `DateTimeImmutable` objects, with display-zone conversion at the edge | Read your clock — you pass the current instant in, so every call stays testable |
| Calculate Qibla on a sphere (fast default) or the WGS84 ellipsoid (precise opt-in) | Look up magnetic declination — you supply it, the library only does the arithmetic |
| Divide the night into Sunnah markers (middle, thirds, tahajjud window) | Judge actual crescent visibility — it scores geometry; weather, optics, and local rulings are yours |
| Convert Gregorian dates to Hijri (tabular, Umm al-Qura, per-country MABIMS) with sunset rollover | Rule on observed months — computed tables can differ from announcements by design |

## At a glance

You describe *where* with coordinates, *when* with a civil date, and *how* with a calculation method. The library does the rest:

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

echo $schedule
    ->forZone(new DateTimeZone('Asia/Kuala_Lumpur'))
    ->timeFor(Prayer::FAJR)
    ->format('H:i'); // 06:15
```

Three ideas carry the whole design, and they are worth learning once:

1. **UTC inside, timezones at the edge.** Every calculation happens in UTC. You convert to a display zone only when you render. That single rule eliminates an entire class of daylight-saving bugs.
2. **Values, not managers.** Inputs and outputs are immutable value objects (`Coordinates`, `CivilDate`, `PrayerSchedule`, `HijriDate`). They validate at construction, so bad data fails fast with a message that tells you how to fix it.
3. **Strategies, not flags.** Method differences — angles versus intervals, night rules, polar fallbacks, geodesic models, calendar systems — are separate classes behind small interfaces. You pick them by choosing a preset, or you inject them explicitly when you need control.

## Where to go next

| I want to… | Go to |
| --- | --- |
| Install and run my first calculation | [Getting Started](getting-started/) |
| Understand what each time means and how it is computed | [Prayer Times](prayer-times/) |
| Pick the right method for my community | [Calculation Methods](calculation-methods/) |
| Understand Shafi vs Hanafi Asr | [Madhab](madhab/) |
| Serve users in Oslo, Tromsø, or Anchorage | [High Latitude Rules](high-latitude/) and [Polar Regions](polar-regions/) |
| Build a monthly timetable or a "next prayer in…" badge | [Timetable & Next Prayer](timetable/) |
| Point people toward Makkah | [Qibla Direction](qibla/) |
| Divide the night for tahajjud | [Sunnah Times](sunnah-times/) |
| Predict the new crescent | [Moon Sighting](moon-sighting/) |
| Convert Gregorian dates to Hijri | [Hijri Converter](hijri-converter/) |
| Show times in the user's timezone | [Timezones & Display](timezone/) |
| Wire this into Laravel, Symfony, or WordPress | [Framework Integration](integration/) |
| Handle failures gracefully | [Errors](errors/) |
| See every class and method | [API Reference](api-reference/) |

## Requirements

* PHP `>= 8.2` (64-bit), no runtime dependencies
* Install with Composer: `composer require al-falaq/al-falaq`

## License

MIT. Astronomical sources and method provenance are cited in [Citations](citations/).
