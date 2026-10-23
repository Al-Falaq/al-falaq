# al-falaq/al-falaq (PHP)

Offline, zero-dependency PHP 8.2+ library for Islamic astronomy: prayer times, Qibla, Sunnah markers, crescent visibility, Hijri conversion.

Status: **M5 landed** — prayer pipeline, monthly timetable, next-prayer resolver, Qibla (spherical + WGS84/Karney), Sunnah markers, crescent visibility, Hijri calendars (tabular, Umm al-Qura, MABIMS + bridge), hardening gates green (coverage 95, MSI≥85%); calculation-method defaults frozen + milestone amendments. Goldens green (25 CSVs incl. drift-gated rows in tehran/morocco/uoif and turkey/kemenag/brunei; UOIF 12/12 preset landed Wave 2, see issues). See `docs/`.

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

- User documentation (versioned, GitHub Pages, Astro Starlight): [`docs/user/`](docs/user/) — built via `docs_site/` (see `docs_site/astro.config.mjs`, `docs_site/versions.json`). Local preview: `cd docs_site && npm install && npm run dev`.
- `ARCHITECTURE.md` — layering summary
- `docs/` — per-context guides (M1–M5, contributor-facing) plus `docs/user/` (user-facing, versioned)
- `UQ_PROVENANCE.md` — Umm al-Qura + MABIMS table provenance, digests, regeneration

### Freezing docs for a release

The versioning plugin generates the snapshot — never copy `docs/user/*.md` by hand.
Hand-copied pages lack the `slug:` frontmatter the plugin injects, so they build at the
wrong route and the version switcher links 404.

1. Add the tag to `docs_site/versions.json` (`{ "slug": "vX.Y.Z", "label": "vX.Y.Z" }`).
2. Run the build; the plugin copies the current docs into `docs/user/vX.Y.Z/`,
   injects the versioned `slug`, and writes `docs_site/src/content/versions/vX.Y.Z.json`:
   `cd docs_site && npm run build`.
3. Commit the generated snapshot, the version JSON, and `versions.json`, then tag.

The plugin only regenerates a version whose directory is *missing* (`checkForNewVersion`
skips any slug already present under `docs/user/`). So to re-freeze a version you have
not tagged yet — after a review round, say — delete `docs/user/vX.Y.Z/` and
`docs_site/src/content/versions/vX.Y.Z.json` and rebuild. Never hand-edit a generated
`docs/user/vX.Y.Z/` page: the next regeneration overwrites it, and its `slug:` frontmatter
is what routes the page under the version prefix.

`.github/workflows/pre-release.yml` refuses to publish a release whose docs snapshot,
version JSON, or `versions.json` entry is missing, and only runs after the Windows gate
passes, so the released docs always reach the version switcher.
