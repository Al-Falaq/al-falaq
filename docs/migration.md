# Architecture migration (M1-M5)

> This is the contributor-facing record of the M1-M5 architecture rounds.
> For the current user-facing upgrade guide, including the 0.2.0 `ClockView`
> removal and the `TWILIGHT_ANGLE` break, see
> [`docs/user/migration.md`](user/migration.md).

## 0.1.0 — architecture deepening (M1-M5)

One batch from the C1–C6 + synthesis rounds. `default()` / `defaultFor()` /
`::default` factories are unaffected throughout — only explicit construction
and custom implementations need attention.

1. **`PrayerTimesCalculator::__construct` takes the day engine first.**
   Inject `SolarDayEngine` (`MeanSolarCalculator` is the stable default
   adapter) ahead of the three strategies.

   ```php
   // before
   new PrayerTimesCalculator($hl, $polar, $twilight);
   // after
   new PrayerTimesCalculator(new MeanSolarCalculator(), $hl, $polar, $twilight);
   ```

2. **Rule interfaces gain members.** `IshaRule` / `MaghribRule` gain
   `validate()`; `HighLatitudeCorrection::portions()` narrows to
   `(float $fajrAngle, ?float $ishaAngle)`; `PrayerTimesCalculatorInterface`
   gains `maghribAt()`. Custom implementations must add the members (the
   shipped adapters already carry them); custom `HighLatitudeCorrection`
   adapters drop the place/date params.

3. **`AsrRule` seam deleted.** `AsrRule`, `ShafiAsr`, `HanafiAsr` are gone;
   read the closed enum instead (it already carried the factor).

   ```php
   // before
   $asrRule = match ($madhab) { Madhab::SHAFI => new ShafiAsr(), ... };
   $asrRule->shadowLength();
   // after
   $madhab->shadowLength(); // 1.0 Shafi, 2.0 Hanafi
   ```

4. **`SolarModel` folded into `SolarDayEngine`.** Hint the day-engine port;
   the narrow interface no longer exists. (`hourAngle` vs `hourAngleFloat`
   intentionally stay a pair: floored instants for minute parity, raw
   fractional hours for sub-second Julian-day math.)

5. **`QiblaCalculator` requires its geodesy.** `new QiblaCalculator()` with
   no argument no longer exists — use `::default()` / `::wgs84()` or inject
   a `GeodesyCalculator`. Kaaba digits moved with it
   (`GeodesyEngine::MAKKAH_*` → `GeodesyCalculator::MAKKAH_*`).

6. **`CrescentCalculator` takes a `ConjunctionSearch`.** Pass
   `EphemerisConjunctionSearch::default()` (or a double) as the new
   argument; `LunarPosition` is deleted.

7. **Ramadan consistency fails fast.** `isRamadan` outside Umm al-Qura now
   throws in `create()` / `withRamadan()`, not at `calculate()` time.
   `Comparator::floatsEqual()` is deleted (minute equality is the live
   golden story).

## M5 — `nightFraction` strictly-interior clamp (pure bugfix, no action needed)

`SunnahTimes::nightFraction()` now clamps the minute-rounded result strictly
inside the night. Before, on very short nights the rounded output could equal
an anchor; after, `maghrib < result < fajr` always holds (nights longer than
two minutes). No caller changes needed.

```php
// before: could return exactly maghrib or fajr on short nights
$night->nightFraction(0.001); // e.g. == maghrib after minute-rounding
// after: clamped one minute inside the anchor
$night->nightFraction(0.001); // maghrib + 1 minute, strictly interior
```

## M4 — Hijri surface changes

Three intentional breaks land with M4. All are SemVer-minor justified because
the previous shapes could not express the required behaviour.

1. **`HijriDate::plusDays()` removed.** A Hijri date cannot know its own month
   length without a calendar, so day arithmetic moved to the calendar.

   ```php
   // before
   $date->plusDays(1);
   // after
   $calendar->shift($date, 1);
   ```

2. **`HijriCalendar::fromGregorian()` narrowed to `CivilDate`.** The old
   `DateTimeImmutable|CivilDate` union forced the calendar to guess a frame; the
   caller now decides (see `docs/hijri.md` §Sunset rollover) and the calendar
   stays date-only. The interface also gained `toGregorian()` and `shift()`.

3. **Calendar coverage is explicit.** `UmmAlQuraCalendar` covers 1423-01 …
   1500-12 AH (not 1356) and `MabimsCalendar` covers 1445-01 … 1500-12 AH;
   outside those spans they raise `HijriOutOfRangeException` instead of
   extrapolating. `HijriOutOfRangeException::forDate()` now takes a range label
   as its third argument.

## See also

- [Migration](user/migration.md) — the current user-facing upgrade guide
