# Hijri conversion (M4)

Three calendar systems behind one interface, plus a Gregorian bridge that can
roll the date at sunset.

## Choosing a calendar

| Calendar | Class | Key | Coverage | Character |
|---|---|---|---|---|
| Tabular (arithmetic Type IIa) | `TabularCalendar` | `tabular` | 1-1-1 AH onward, Gregorian 1900–2100 via `CivilDate` | Closed form, no data. Drifts ±1–2 days from observation — never present it as an observed date. |
| Umm al-Qura | `UmmAlQuraCalendar` | `uqu` | 1423-01 … 1500-12 AH | Rule-generated month-start table (Makkah crescent rule). |
| Neo-MABIMS | `MabimsCalendar` | `mabims` | 1445-01 … 1500-12 AH | Per-country rule-generated month-start table (MY/ID/SG/BN). |

The observational calendars ship **tables, not rules**. The crescent criteria are
evaluated once, at build time, by the pinned reference implementation; the
converted result is therefore identical to the pin's, and conversion is a binary
search rather than a per-call astronomy evaluation. The tables are byte-for-byte
reproducible — see `UQ_PROVENANCE.md`.

## Converting

```php
use AlFalaq\AlFalaq\Hijri\Services\GregorianToHijriConverter;
use AlFalaq\AlFalaq\Hijri\Strategies\UmmAlQuraCalendar;
use AlFalaq\AlFalaq\Hijri\ValueObjects\HijriDate;
use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

$converter = GregorianToHijriConverter::default();

$hijri = $converter->convert(
    new DateTimeImmutable('2025-03-01 12:00:00', new DateTimeZone('UTC')),
    new UmmAlQuraCalendar(),
);   // 1446-09-01

// The calendar can be driven directly when no zone logic is involved:
$calendar = new UmmAlQuraCalendar();
$calendar->fromGregorian(CivilDate::fromYmd(2025, 3, 1))->isoformat();   // '1446-09-01'
$calendar->toGregorian(new HijriDate(1446, 9, 1))->isoformat();         // '2025-03-01'
$calendar->monthLength(1446, 9);                                        // 29
$calendar->shift(new HijriDate(1446, 9, 29), 1)->isoformat();           // '1446-10-01'
```

`HijriDate` deliberately has **no** `plusDays()`: a Hijri date cannot know its
own month length without a calendar, so day arithmetic lives on
`HijriCalendar::shift()`.

## Sunset rollover

Islamic days begin at sunset, and which civil day an instant belongs to depends
on who is asking. `convert()` exposes exactly two frames:

- **Default (`changeAtSunset: false`)** — the civil date is read in the
  **caller's own zone**. `2024-03-01T20:00Z` converts as 2024-03-02 for a Tokyo
  caller and as 2024-03-01 for a Honolulu caller. This is what a user's
  wall-clock calendar means.
- **`changeAtSunset: true`** — the civil date is read in **UTC**, then rolled
  forward at the observer's maghrib (and rolled backward when the instant falls
  before the previous local maghrib, which happens for observers far west of
  Greenwich). Requires both `$coordinates` and `$parameters`; omitting either
  raises `ConfigurationException`.

```php
$converter = GregorianToHijriConverter::default();
$instant = new DateTimeImmutable('2025-03-01 12:00:00', new DateTimeZone('UTC'));
$hijri = $converter->convert(
    $instant,
    new UmmAlQuraCalendar(),
    $coordinates = Coordinates::fromDegrees(3.1390, 101.6869),
    $parameters = CalculationMethod::JAKIM->defaultParameters(),
    changeAtSunset: true,
);
```

The maghrib instant comes through `PrayerTimesCalculatorInterface`
(`calculate()`, falling back to `maghribAt()`), so high-latitude and
polar behaviour is exactly the prayer pipeline's: an undefined maghrib (e.g.
`PolarCircleRule::NONE` above the Arctic Circle in June) propagates an
`AstronomicalException` from the maghrib-only probe rather than guessing.
When the full schedule is undefined but maghrib itself is defined (e.g.
`PolarCircleRule::NONE` on a polar-transition day, where today's sunset exists
but tomorrow's sunrise does not), the bridge resolves maghrib alone via the
pipeline's maghrib-only computation; only a truly undefined maghrib (e.g.
polar night) still propagates.

## Official day corrections

`OffsetStore` is a caller-supplied port — the library ships no correction table:

```php
$hijri = $converter->convert($instant, $calendar, offsets: $officialOffsets);
```

`offsetDaysFor(HijriDate, HijriCalendar): int` is asked once, with the
already-converted date, and the result is applied as a single
`HijriCalendar::shift()`, so a correction crosses month and year boundaries
correctly. A missing entry returns `0`, and `0` is a no-op.

## When a date is out of range

Nothing saturates and nothing wraps.

| Situation | Exception |
|---|---|
| Gregorian day outside the calendar's span (e.g. UQ before `2002-03-15`) | `HijriOutOfRangeException` (message names the calendar, the date and the span) |
| Hijri month outside the shipped table | `HijriOutOfRangeException` |
| Shift or offset that would leave the span | `HijriOutOfRangeException` |
| A Hijri date whose Gregorian result falls outside `CivilDate` (1900–2100) | `HijriOutOfRangeException` |
| Impossible day for a real month (e.g. day 30 of a 29-day month) | `ValidationException` |
| `changeAtSunset` without coordinates or parameters | `ConfigurationException` |
| `changeAtSunset` where the full schedule is undefined but maghrib is defined | converts using the maghrib-only fallback |
| `changeAtSunset` on 2100-12-31 (last supported civil day) | Maghrib resolves via the maghrib-only fallback (`calculate()` throws `UndefinedScheduleException` at the edge, caught internally); only an instant at/after maghrib still throws `ValidationException` from the `plusDays(1)` rollover past 1900–2100. With `PolarCircleRule::NEAREST_DAY` the pipeline may instead resolve to the prior solvable date (e.g. 2100-12-30) and use that schedule's maghrib — when the calculator is wired via `PrayerTimesCalculator::defaultFor()`; under the converter's default wiring the `NEAREST_DAY` rule still takes the maghrib-only fallback above |
| Non-int argument to a typed contract parameter | `TypeError` |

`HijriOutOfRangeException` extends `ValidationException`, so a `catch (ValidationException)` catches both; catch the former first when the distinction matters.

Extend coverage by adding a table, not by widening a guard.

## Algorithm appendix

### Tabular (Type IIa)

Epoch `1 Muharram 1 AH` = Julian Date `1948439.5` (622-07-19 proleptic
Gregorian). Leap years are the remainders `{2, 5, 7, 10, 13, 16, 18, 21, 24, 26,
29}` mod 30 — 11 leaps of 355 days per cycle. Month lengths: odd months 30,
even months 29, Dhu al-Hijjah 30 in leap years. `adjustmentDays` (int, −2…2)
shifts the whole conversion, playing the role of .NET `HijriAdjustment`.
Anchors: `2025-03-01 → 1446-09-01`, `1937-03-14 → 1356-01-01`,
`1355-11-25 → 1937-02-06`.

### Umm al-Qura and MABIMS tables

Both are generated from `PYTHON_PIN` by `tools/gen_hijri.py`, which walks the
pinned rule one month at a time and emits an array of month-start Gregorian days
plus a sentinel. The pin's rules are:

- **Umm al-Qura** (evaluated at Makkah): on the 29th evening the month completes
  29 days iff the moonset lag is positive and the moon age is below 15 days at
  Makkah sunset; otherwise 30.
- **Neo-MABIMS** (evaluated at each member state's national proxy point):
  crescent altitude ≥ 3° and elongation ≥ 6.4° at sunset.

The canonical digest of a table is `sha256("\n".join(starts))` — ASCII LF, no
trailing newline — and is embedded as a PHP constant. Regeneration:

```bash
python3 tools/gen_hijri.py --path <pinned-checkout>
php vendor/bin/phpunit --no-coverage tests/Goldens/HijriGoldensTest.php
```

Two consecutive runs must produce no diff; that is the determinism gate.
Trailing-zero and rounding conventions are the generator's, and the committed
CSVs are its output — if parity fails, fix the calendar, never the golden.

## See also

- [Hijri Converter](user/hijri-converter.md) — the published user guide
