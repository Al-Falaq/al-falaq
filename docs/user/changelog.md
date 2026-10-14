---
title: Changelog
description: Release history for al-falaq — what shipped, what changed, what to do.
---

# Changelog

All notable changes to this project are documented here, following [Keep a Changelog](https://keepachangelog.com/en/1.0.0/) and [Semantic Versioning](https://semver.org/spec/v2.0.0.html). Dates are release dates; the `Unreleased` section tracks the main branch.

Versioned snapshots of the whole documentation site are kept per release — use the version switcher at the top of the page to read the docs as they were for any shipped version.

## [Unreleased]

No changes yet — see the repo `CHANGELOG.md` for work in progress.

## [0.1.1] - 2026-10-14

Patch release with no library change. The docs site chrome now repairs
Starlight edit links and last-updated dates for symlinked sources (#37),
drops the version selector from the 404 page (#38), and brands the header
and favicon from the repository artwork (#39); CI action pins move with the
github-actions group bump (#36).

## [0.1.0] - 2026-10-14

First tagged release. Everything below ships in it.

### Added

- Versioned user documentation: these guides, published to GitHub Pages with
  Astro Starlight and snapshotted per release (this page, frozen as v0.1.0).

- UOIF 12/12 preset: `CalculationMethod::UOIF` with Fajr 12°, Isha `AngleIsha(12°)`, sunset Maghrib, and zero offsets — cited to the 1994 Dar el-Fatwa text, the 2012 UOIF presidential statement, and ECFR Dublin 2004 toleration, communally confirmed current (2026). Drift-gated golden (`tests/Goldens/prayer_uoif.csv`).
- Sunnah Maghrib anchor + Tehran/Morocco provenance verdicts: `SunnahTimes` gains the fifth `maghrib` anchor with dual degenerate guards and a zone-agnostic `nightFraction()`; Tehran 17.7/14/Maghrib-4.5 cross-checked against the BatoulApps reference and re-pinned as a drift gate; Morocco 19/17 kept as the labelled Habous convention.
- Monthly timetable (`TimetableService`), next-prayer resolver (`NextPrayerResolver`), and Sunnah night fractions (`SunnahCalculator`, `nightFraction()`).
- Qibla on WGS84 via vendored Karney (2013) inverse (`QiblaCalculator::wgs84()`); spherical `default()` unchanged.
- Crescent visibility: `CrescentCalculator` with Yallop, Odeh, MABIMS-1992, and Neo-MABIMS-2021 evaluators.
- Hijri calendars: tabular, Umm al-Qura (1423–1500 AH), per-country MABIMS (1445–1500 AH), plus the `GregorianToHijriConverter` bridge with sunset rollover and `OffsetStore` corrections.

### Changed

- Prayer wiring goes through the `SolarDayEngine` port (`MeanSolarCalculator` is the stable default adapter); rule interfaces gain `validate()`; `AsrRule` seam deleted in favour of `Madhab::shadowLength()`; `QiblaCalculator` requires its geodesy; `CrescentCalculator` takes a `ConjunctionSearch`; Ramadan consistency fails fast at parameter-build time.
- `HijriDate::plusDays()` removed in favour of `HijriCalendar::shift()`; `fromGregorian()` narrowed to `CivilDate`; calendar coverage made explicit with `HijriOutOfRangeException`.

## How versioning works here

- **Payload contracts are versioned.** `PrayerSchedule::all()` keys and order, `QiblaResult` shape, `CrescentGeometry` fields, and `HijriDate` shape do not change without a minor bump.
- **Documentation is snapshotted per release.** When a version tag ships, the current user docs are frozen under `docs/user/vX.Y.Z/` with a `docs_site/src/content/versions/vX.Y.Z.json` sidebar snapshot, and `docs_site/versions.json` gains the entry. The `Latest` docs always track `main`.
- **Goldens move with code.** Every numeric behaviour is pinned against golden vectors; intentional divergence updates docs and goldens together, with provenance cited.

## See also

- [Migration](migration/) — upgrade instructions per change
- [Citations](citations/) — sources behind the numbers
