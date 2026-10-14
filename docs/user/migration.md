---
title: Migration
description: Upgrade between al-falaq versions — what changed, what breaks, and what to do.
---

# Migration

This page collects the changes that require your attention when upgrading. Everything below ships in 0.1.0 — the first tagged release.

## 0.1.0

The guiding promise: `default()` / `defaultFor()` factories keep working across these changes — only explicit construction and custom strategy implementations need attention. Bugfixes that need no action are marked as such.

### Architecture deepening

One batch of seam-clarifying changes. If you wire with factories (as the [Framework Integration](integration/) page recommends), skip to the next section — nothing here touches you.

1. **`PrayerTimesCalculator::__construct` takes the day engine first.** Inject `SolarDayEngine` (`MeanSolarCalculator` is the stable default adapter) ahead of the three strategies:

   ```php
   // Before:
   new PrayerTimesCalculator($highLatitude, $polar, $twilight);
   // After:
   new PrayerTimesCalculator(new MeanSolarCalculator(), $highLatitude, $polar, $twilight);
   ```

2. **Rule interfaces gain members.** `IshaRule` / `MaghribRule` gain `validate()`; `HighLatitudeCorrection::portions()` narrows to `(float $fajrAngle, ?float $ishaAngle)`; `PrayerTimesCalculatorInterface` gains `maghribAt()`. Custom strategy implementations must add the members (the shipped adapters already carry them).

3. **`AsrRule` seam deleted.** `AsrRule`, `ShafiAsr`, and `HanafiAsr` are gone; read the closed enum instead (it already carried the factor):

   ```php
   // Before:
   $asrRule = match ($madhab) { Madhab::SHAFI => new ShafiAsr(), /* ... */ };
   $asrRule->shadowLength();
   // After:
   $madhab->shadowLength(); // 1.0 Shafi, 2.0 Hanafi
   ```

4. **`SolarModel` folded into `SolarDayEngine`.** Hint the day-engine port; the narrow interface no longer exists. (`hourAngle` vs `hourAngleFloat` intentionally stay a pair: floored instants for minute parity, raw fractional hours for sub-second Julian-day math.)

5. **`QiblaCalculator` requires its geodesy.** `new QiblaCalculator()` with no argument no longer exists — use `::default()` / `::wgs84()` or inject a `GeodesyCalculator`. Kaaba digits moved with it (`GeodesyEngine::MAKKAH_*` → `GeodesyCalculator::MAKKAH_*`).

6. **`CrescentCalculator` takes a `ConjunctionSearch`.** Pass `EphemerisConjunctionSearch::default()` (or a test double) as the new argument; `LunarPosition` is deleted.

7. **Ramadan consistency fails fast.** `isRamadan` outside Umm al-Qura now throws in `create()` / `withRamadan()`, not at `calculate()` time. Move any rescue logic to parameter construction.

### `nightFraction` strictly-interior clamp (pure bugfix)

`SunnahTimes::nightFraction()` now clamps the minute-rounded result strictly inside the night. Before, on very short nights the rounded output could equal an anchor; after, `maghrib < result < fajr` always holds (for nights longer than two minutes). No caller changes needed:

```php
// Before: could return exactly maghrib on very short nights.
$night->nightFraction(0.001);
// After: clamped one minute inside the anchor.
```

### Hijri surface changes

Three intentional breaks, all SemVer-minor justified because the previous shapes could not express the required behaviour:

1. **`HijriDate::plusDays()` removed.** A Hijri date cannot know its own month length without a calendar, so day arithmetic moved to the calendar:

   ```php
   // Before:
   $date->plusDays(1);
   // After:
   $calendar->shift($date, 1);
   ```

2. **`HijriCalendar::fromGregorian()` narrowed to `CivilDate`.** The old `DateTimeImmutable|CivilDate` union forced the calendar to guess a frame; the caller now decides (see [Hijri Converter](hijri-converter/)) and the calendar stays date-only. The interface also gained `toGregorian()` and `shift()`.

3. **Calendar coverage is explicit.** `UmmAlQuraCalendar` covers 1423-01…1500-12 AH and `MabimsCalendar` covers 1445-01…1500-12 AH; outside those spans they raise `HijriOutOfRangeException` instead of extrapolating. `HijriOutOfRangeException::forDate()` takes a range label as its third argument.

## See also

- [Changelog](changelog/) — the release-by-release record
- [Errors](errors/) — the exception hierarchy these changes throw into
- [Framework Integration](integration/) — factory-first wiring that avoids most breakage
