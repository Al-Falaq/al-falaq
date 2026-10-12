# al-falaq/al-falaq (PHP)

Offline, zero-dependency PHP 8.2+ library for Islamic astronomy: prayer times, Qibla, Sunnah markers, crescent visibility, Hijri conversion.

Status: **M5 landed** — prayer pipeline, monthly timetable, next-prayer resolver, Qibla (spherical + WGS84/Karney), Sunnah markers, crescent visibility, Hijri calendars (tabular, Umm al-Qura, MABIMS + bridge), hardening gates green (coverage 95, MSI≥85%); calculation-method defaults frozen + milestone amendments. Goldens green (24 CSVs incl. drift-gated tehran/morocco; UOIF gap open, see issues). See `docs/`.

## Install

```bash
composer require al-falaq/al-falaq
```

Requires PHP `>=8.2` (64-bit), no runtime dependencies.

## Quickstart (M1)

```php
use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Enums\Prayer;
use AlFalaq\AlFalaq\Prayer\Services\PrayerTimesCalculator;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;

$coords = Coordinates::fromDegrees(3.1390, 101.6869);
$date = CivilDate::fromDateTime(new DateTimeImmutable('now', new DateTimeZone('UTC')));
$params = CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters();
$schedule = PrayerTimesCalculator::defaultFor($params)->calculate($coords, $date, $params);
echo $schedule->forZone(new DateTimeZone('Asia/Kuala_Lumpur'))->timeFor(Prayer::FAJR)->format('H:i');
```

See `docs/prayer.md` for the algorithm appendix + method-freeze state.

## Non-goals

- No CLI, HTTP endpoint, daemon, or telemetry; the library is offline only.
- No magnetic-declination model: `QiblaResult::magneticHeading()` takes a
  caller-supplied declination, nothing more.
- Month labels are `en`/`ar` only via `HijriMonth::label()`; no ICU.
- Hijri coverage is exactly the shipped tables (Umm al-Qura 1423–1500,
  MABIMS 1445–1500, Tabular) with caller `OffsetStore` corrections.

## Scripts

```bash
composer lint && composer cs && composer stan && composer test
composer ci   # all of the above
composer bench
```

## Docs

- `ARCHITECTURE.md` — layering summary
- `docs/` — per-context guides (M1–M5)
- `UQ_PROVENANCE.md` — Umm al-Qura + MABIMS table provenance, digests, regeneration
