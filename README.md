# al-falaq/al-falaq (PHP)

Offline, zero-dependency PHP 8.2+ library for Islamic astronomy: prayer times, Qibla, Sunnah markers, crescent visibility, Hijri conversion.

Status: **M4 landed** — prayer pipeline (`PrayerTimesCalculator`), monthly timetable (`TimetableService`), next-prayer resolver (`NextPrayerResolver`), Qibla (spherical + WGS84/Karney), Sunnah markers, crescent visibility, and Hijri calendars (tabular, Umm al-Qura, MABIMS + bridge) shipped; Appendix A frozen + milestone amendments. Goldens green (24 CSVs incl. drift-gated tehran/morocco; UOIF gap open, see issues). See `Technical-PRD.md` + `docs/`.

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

See `docs/prayer.md` for the algorithm appendix + Appendix A freeze state.

## Scripts

```bash
composer lint && composer cs && composer stan && composer test
composer ci   # all of the above
composer bench
```

## Docs

- `Technical-PRD.md` — build contract
- `ARCHITECTURE.md` — layering summary
- `docs/` — per-context guides (M1–M5)
- `UQ_PROVENANCE.md` — Umm al-Qura + MABIMS table provenance, digests, regeneration
