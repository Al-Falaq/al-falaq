# migration (M1-M5)

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
