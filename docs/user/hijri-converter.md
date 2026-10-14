---
title: Hijri Converter
description: Convert Gregorian dates to Hijri with tabular, Umm al-Qura, and per-country MABIMS calendars, sunset rollover, and official corrections.
---

# Hijri Converter

The Hijri calendar is lunar: months begin with the new crescent, so month lengths alternate 29 and 30 days by observation, not by formula. al-falaq offers three calendar systems behind one interface — an arithmetic approximation and two rule-generated tables — plus a bridge that decides *which civil day an instant belongs to*, including the Islamic convention that days begin at sunset.

> Tabular dates routinely differ from observed months by ±1–2 days. Never present a tabular date as an observed date, or vice versa.

## Choosing a calendar

A Hijri calendar answers a deceptively simple question — *was this month 29 or 30 days?* — and the three systems answer it three different ways, with three different histories:

- **Tabular (arithmetic Type IIa, "Kuwaiti" pattern).** The oldest approach: no observation at all, just arithmetic. A fixed 30-year cycle with 11 leap years (remainders {2, 5, 7, 10, 13, 16, 18, 21, 24, 26, 29} mod 30), odd months 30 days, even months 29, Dhu al-Hijjah 30 in leap years, anchored at 1 Muharram 1 AH = Julian Date 1948439.5 (19 July 622, proleptic Gregorian). It has been used for centuries for planning and conversion precisely because it needs no sky — and it drifts ±1–2 days from real sightings by construction. Never present it as observed.
- **Umm al-Qura (Makkah rule, 1423H scheme).** Saudi Arabia's civil calendar, in its current form dating to 1423H (2002): on the 29th evening at Makkah, the month completes 29 days if the moon sets after the sun with the moon younger than 15 days, else 30. Earlier variants (the 1392H scheme, the 1420–1422H interim criteria) are out of scope — inputs before 1 Muharram 1423H raise rather than return a wrong date.
- **Neo-MABIMS (2021, per-country).** The current rule of the MABIMS ministerial forum (Brunei, Indonesia, Malaysia, Singapore): at each country's national proxy point, the crescent counts if altitude ≥ 3° and elongation ≥ 6.4° at sunset. It replaced the looser 1992 Labuan rule (2°/3° or 8-hour age) after sighting records showed the old thresholds admitted evenings nobody ever saw. See [Moon Sighting](moon-sighting/) for both committees' full stories.

| Calendar | Class | Key | Coverage | Character |
| --- | --- | --- | --- | --- |
| Tabular (Type IIa) | `TabularCalendar` | `tabular` | 1 Muharram 1 AH onward | Closed form, no data. Drifts ±1–2 days from observation. |
| Umm al-Qura | `UmmAlQuraCalendar` | `uqu` | 1423–1500 AH | Makkah crescent rule evaluated once at build time; conversion is a table lookup. |
| Neo-MABIMS | `MabimsCalendar` | `mabims` | 1445–1500 AH | Per-country rule (MY/ID/SG/BN) evaluated once at build time; conversion is a lookup. |

The observational calendars ship **tables, not rules**. Their crescent criteria were evaluated month by month by the pinned reference implementation when the tables were built; your calls perform a binary search, not per-call astronomy. That makes conversion fast, deterministic, and byte-for-byte reproducible — see the provenance notes in [Citations](citations/).

```php
use AlFalaq\AlFalaq\Hijri\Services\GregorianToHijriConverter;
use AlFalaq\AlFalaq\Hijri\Strategies\MabimsCalendar;
use AlFalaq\AlFalaq\Hijri\Strategies\TabularCalendar;
use AlFalaq\AlFalaq\Hijri\Strategies\UmmAlQuraCalendar;
use AlFalaq\AlFalaq\Hijri\Enums\MabimsCountry;

$converter = GregorianToHijriConverter::default();

$converter->convert(
    new DateTimeImmutable('2025-03-01 12:00:00', new DateTimeZone('UTC')),
    new UmmAlQuraCalendar(),
)->isoformat(); // '1446-09-01'

$converter->convert(
    new DateTimeImmutable('2025-03-01 12:00:00', new DateTimeZone('UTC')),
    new MabimsCalendar(MabimsCountry::MY),
)->isoformat();
```

When no zone logic is involved, drive the calendar directly — it speaks civil dates, the bridge speaks instants:

```php
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\Hijri\ValueObjects\HijriDate;

$calendar = new UmmAlQuraCalendar();
$calendar->fromGregorian(CivilDate::fromYmd(2025, 3, 1))->isoformat(); // '1446-09-01'
$calendar->toGregorian(new HijriDate(1446, 9, 1))->isoformat();        // '2025-03-01'
$calendar->monthLength(1446, 9); // 29
$calendar->shift(new HijriDate(1446, 9, 29), 1)->isoformat(); // '1446-10-01'
```

`HijriDate` deliberately has no `plusDays()`: a Hijri date cannot know its own month length without a calendar, so day arithmetic lives on `HijriCalendar::shift()`, which crosses month and year boundaries correctly.

## Which civil day? Sunset rollover

Islamic days begin at sunset, so an instant's Hijri date depends on *where you ask*. `convert()` exposes exactly two frames — pick consciously:

- **Default (`changeAtSunset: false`)** — the civil date is read in the **instant's own zone**. `2024-03-01T20:00Z` converts as March 2nd for a Tokyo caller and March 1st for a Honolulu caller. This is what a wall-clock calendar means, and it is right for most display purposes.
- **Sunset frame (`changeAtSunset: true`)** — the civil date is read in **UTC**, then rolled forward at the observer's Maghrib (and rolled backward when the instant falls before the previous local Maghrib, which happens for observers far west of Greenwich). Requires both coordinates and parameters; omitting either throws `ConfigurationException`, because a sunset without a place is meaningless.

```php
use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

$hijri = $converter->convert(
    new DateTimeImmutable('2025-03-01 12:00:00', new DateTimeZone('UTC')),
    new UmmAlQuraCalendar(),
    Coordinates::fromDegrees(3.1390, 101.6869),
    CalculationMethod::JAKIM->defaultParameters(),
    changeAtSunset: true,
);
```

The Maghrib instant comes through the real prayer pipeline (never a reimplementation), so high-latitude and polar behaviour match prayer times exactly: an undefined Maghrib propagates `AstronomicalException` unchanged rather than guessing, and when the full schedule is undefined but Maghrib alone is defined, the bridge resolves Maghrib alone.

## MABIMS countries and proxies

The four country references are **national proxies**, not per-zone truth: Kuala Lumpur, Jakarta, Bandar Seri Begawan, and Singapore stand in for countries that span many prayer zones (Malaysia alone has 60+; Indonesia spans three time zones). Official announcements fold in zone observations and actual sighting reports, so computed dates may differ from announced dates by ±1 day. On 2025-03-01, for example, the KL proxy still reads Shaban 30th while tabular and Umm al-Qura already read Ramadan 1st — an expected one-day proxy spread, not a bug. The superseded 1992 Labuan rule is not implemented; only Neo-MABIMS 2021 (altitude ≥ 3°, elongation ≥ 6.4°) ships.

## Official day corrections

Governments occasionally announce a month start a day off the computed table. `OffsetStore` is your port for those announcements — the library ships no correction table, because corrections are jurisdictional data, not astronomy:

```php
use AlFalaq\AlFalaq\Hijri\Contracts\OffsetStore;
use AlFalaq\AlFalaq\Hijri\Contracts\HijriCalendar;
use AlFalaq\AlFalaq\Hijri\ValueObjects\HijriDate;

final class OfficialOffsets implements OffsetStore
{
    public function offsetDaysFor(HijriDate $date, HijriCalendar $calendar): int
    {
        return 0; // look up your gazette; 0 is a no-op
    }
}

$hijri = $converter->convert(
    new DateTimeImmutable('2025-03-01 12:00:00', new DateTimeZone('UTC')),
    new UmmAlQuraCalendar(),
    offsets: new OfficialOffsets(),
);
```

The store is asked once with the already-converted date, and its answer is applied as a single `shift()` — so a correction crosses month boundaries correctly, and a missing entry (return `0`) costs nothing.

## When a date is out of range

Nothing saturates and nothing wraps. Outside a calendar's span you get `HijriOutOfRangeException` (which extends `ValidationException`, so existing `catch (ValidationException)` blocks keep working):

| Situation | Result |
| --- | --- |
| Gregorian day before a table starts (e.g. Umm al-Qura before 2002-03-15) | `HijriOutOfRangeException` naming the calendar, date, and span |
| Hijri month outside the shipped table | `HijriOutOfRangeException` |
| A `shift()` or offset that would leave the span | `HijriOutOfRangeException` |
| Day 30 of a 29-day month | `ValidationException` |
| `changeAtSunset` without coordinates or parameters | `ConfigurationException` |

Extend coverage by adding a table, never by widening a guard. The tabular calendar's `adjustmentDays` (−2…+2, same role as .NET's `HijriAdjustment`) is the sanctioned way to express regional tabular corrections without forking the arithmetic.

## See also

- [Moon Sighting](moon-sighting/) — the crescent geometry behind the tables
- [Timezones & Display](timezone/) — civil-date frames and the sunset edge
- [Errors](errors/) — catching range failures gracefully
- [API Reference](api-reference/) — `GregorianToHijriConverter`, the three calendars, `HijriDate`
