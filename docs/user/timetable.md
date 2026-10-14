---
title: Timetable & Next Prayer
description: Build monthly timetables, resolve the next prayer, and keep the clock at the edge of your app.
---

# Timetable & Next Prayer

Single-day calculation is the engine. Two thin services turn it into products: `TimetableService` loops the engine across a month, and `NextPrayerResolver` answers *"what comes next?"* for badges, notifications, and widgets. Both are deliberately boring — the astronomy stays in the calculator, so timetables and badges can never drift from it.

## Monthly timetables

A month is just the single-day pipeline run once per calendar day. There is intentionally no yearly helper: callers loop months, which keeps memory flat and lets you mix parameters per month if a community adjusts seasonally.

```php
use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Services\TimetableService;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

$params = CalculationMethod::JAKIM->defaultParameters();

$month = (new TimetableService())->monthly(
    Coordinates::fromDegrees(3.1390, 101.6869),
    2025, 3,
    $params,
);

foreach ($month as $day => $schedule) {
    $display = $schedule->forZone(new DateTimeZone('Asia/Kuala_Lumpur'));
    // $day is 0-based; render $display->toArray() per row.
}
```

The service builds its calculator via `defaultFor($parameters)`, so your high-latitude and polar rules flow through automatically — a Tromsø March timetable resolves each polar-transition day with the same rule as a single-day call. Each element is a full `PrayerSchedule` (ten UTC markers), in calendar order. Encode rows with `toArray()`; never invent a yearly cache that could go stale across a method change.

## Next prayer: "Isha in 42 minutes"

Badges and notifications need a pure function of three things: today's schedule, tomorrow's schedule, and the current instant. The resolver walks both days in canonical order and returns the first *marker* after now — not the first obligatory prayer. Because `all()` carries all ten entries, the answer can be an auxiliary marker (`imsak`, `sunrise`, `syuruk`, `ishraq`, `dhuha`) or one of the five prayers, so check `$next->prayer` and decide what your UI should show. That is usually a feature: "Ishraq in 20 minutes" is exactly what a sunrise widget wants:

```php
use AlFalaq\AlFalaq\Prayer\Services\NextPrayerResolver;

$resolver = new NextPrayerResolver();
$next = $resolver->resolve($today, $tomorrow, $nowUtc);

$next->prayer;    // a Prayer enum, e.g. Prayer::ISHA
$next->at;        // UTC DateTimeImmutable of that marker
$next->inSeconds; // seconds from $nowUtc until it
```

Tomorrow's schedule matters because Isha can fall after midnight UTC for far-eastern zones, and Fajr belongs to the coming dawn. Always compute both days — the resolver validates that `$nowUtc` is UTC and throws `ValidationException` otherwise, catching the classic bug of passing a zone-stamped "now".

## The clock stays at the edge

The domain never reads the clock. You read it — in your controller, command, or widget — and pass the instant in. If your app uses `psr/clock` (suggested, never required), the pattern is one line at the boundary:

```php
$nowUtc = $clock->now(); // must already be UTC
$next = (new NextPrayerResolver())->resolve($today, $tomorrow, $nowUtc);
```

Why this discipline? Because a library that secretly calls "now" cannot be tested deterministically, cannot replay yesterday's bug, and cannot render a timetable for a traveller's future date. Explicit instants make every calculation reproducible: same inputs, same outputs, forever. The [Framework Integration](integration/) page shows where this line lives in Laravel and Symfony request lifecycles.

## See also

- [Prayer Times](prayer-times/) — the single-day pipeline both services reuse
- [Timezones & Display](timezone/) — why `$nowUtc` must be UTC and rendering happens last
- [Framework Integration](integration/) — scheduling timetable refreshes and prayer notifications
