---
title: Errors
description: The typed exception hierarchy, when each failure fires, and how to catch well.
---

# Errors

al-falaq fails the way it computes: precisely, with types. Every failure is a typed exception in one hierarchy, every message tells you how to fix the call, and nothing ever returns a silent `null` or a guessed time where the sky has no answer. Learn the four types once and your error handling is done.

## The hierarchy

```
DomainException (marker interface — catch this for everything)
└── AlFalaqException (abstract; extends RuntimeException)
    ├── ValidationException      — bad input: fix the caller's data
    │   ├── InvalidCoordinatesException
    │   └── HijriOutOfRangeException
    ├── ConfigurationException   — well-typed but inconsistent wiring: fix the setup
    └── AstronomicalException    — the sky has no answer: choose a strategy
        └── UndefinedScheduleException
```

Catch `AlFalaqException` (or the `DomainException` marker) as your catch-all at framework boundaries. Catch narrower types where you have something smarter to do.

## When each one fires

**`ValidationException` — "you gave me bad data."** Out-of-range coordinates, impossible dates (`CivilDate` covers 1900–2100), non-UTC instants where UTC is required, per-prayer adjustments outside ±60 minutes, degenerate Sunnah nights, non-finite geometry. The message names the value and the valid range:

```php
use AlFalaq\AlFalaq\SharedKernel\Exceptions\InvalidCoordinatesException;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

try {
    $coords = Coordinates::fromDegrees(95.0, 200.0);
} catch (InvalidCoordinatesException $e) {
    // $e->getMessage() names the axis ('lat' vs 'lon') and the received value.
}
```

**`ConfigurationException` — "your wiring contradicts itself."** Two flavours. `CalculationMethod::NONE->defaultParameters()` (NONE has no defaults — build `CalculationParameters::create()` by hand) and `isRamadan` on any method but Umm al-Qura throw while you *build* the parameters, before any astronomy runs. `changeAtSunset: true` without coordinates *and* parameters throws when you call `GregorianToHijriConverter::convert()`, which is the earliest it can be detected — the call itself supplies the place. Either way the failure lands in your setup or request edge, never at dawn:

```php
$params = CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters()
    ->withRamadan(true); // throws now, not at calculate() time
```

**`AstronomicalException` — "the sky has no answer."** Polar day or night with no sunrise, crescent geometry with no sunset, a Karney inverse that cannot converge. Catch it to pick a strategy, never to guess a time.

**`UndefinedScheduleException` — the polar specialisation.** Carries the coordinates, date, and active rule, and its message names the rule and points at [Polar Regions](polar-regions/):

```php
use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Enums\PolarCircleRule;
use AlFalaq\AlFalaq\Prayer\Exceptions\UndefinedScheduleException;
use AlFalaq\AlFalaq\Prayer\Services\PrayerTimesCalculator;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

$coords = Coordinates::fromDegrees(78.2232, 15.6267); // Longyearbyen
$date = CivilDate::fromYmd(2025, 6, 21);
$params = CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters()
    ->withPolarCircleRule(PolarCircleRule::NONE); // presets otherwise substitute a solvable day

try {
    $schedule = PrayerTimesCalculator::default()->calculate($coords, $date, $params);
} catch (UndefinedScheduleException $e) {
    // Offer a polar-strategy switcher or show "no times today".
} catch (ValidationException $e) {
    // Bad input — log, alert, fix the caller.
}
```

**`HijriOutOfRangeException` — the calendar edge.** Extends `ValidationException`, so existing `catch (ValidationException)` blocks keep working; catch it first when the distinction matters (e.g. to show "this calendar starts in 2002" instead of a generic validation error).

## Catching well

Three habits cover nearly every app:

1. **Validate at your boundary, catch typed at theirs.** Let `Coordinates::fromDegrees()` reject bad request data with a 422; let `UndefinedScheduleException` drive a polar UI state, not a 500.
2. **Catch narrow first.** `HijriOutOfRangeException` before `ValidationException`; `UndefinedScheduleException` before `AstronomicalException`; framework catch-alls last.
3. **Never catch to guess.** If Maghrib is undefined inside the Hijri sunset bridge, the exception propagates unchanged — mirror that discipline and propagate rather than substituting a neighbouring day's times silently.

Non-integer arguments to typed parameters surface as PHP `TypeError`, not library exceptions — a programming error, not a domain failure. Let it bubble to your error tracker.

## See also

- [Polar Regions](polar-regions/) — designing around `UndefinedScheduleException`
- [Hijri Converter](hijri-converter/) — the range table for every calendar
- [API Reference](api-reference/) — the full exception list with factories
