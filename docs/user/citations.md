---
title: Citations
description: Every astronomical source, method provenance, and criterion reference behind al-falaq.
---

# Citations

Prayer software inherits trust: when your app says Fajr is at 05:42, someone sets an alarm by it. This page records *why* each number is what it is — the books, papers, committees, and official tables behind the code — so you (and your users) can check.

## Core astronomy

- **Jean Meeus, *Astronomical Algorithms*, 2nd ed. (Willmann-Bell, 1998).** The core solar theory: Ch. 7 (Julian Day), Ch. 15 p. 102 (transit and hour-angle interpolation), Ch. 25 pp. 163–165 (solar coordinates), Ch. 28 p. 183 (equation of time), Ch. 47 (lunar positions for crescent geometry), plus interpolation, altitude-correction, nutation, and obliquity treatments (pp. 24/93/144/147). Every engine formula cites its page.
- **US Naval Observatory (USNO).** Solar-position algorithms and twilight definitions underlying the depression angles. Reference: [aa.usno.navy.mil](https://aa.usno.navy.mil/).
- **Nautical Almanac / Bowditch dip correction.** The horizon-dip formula `−0.833° − 0.0293°·√h` (height in metres) applied to sunrise, sunset, and Maghrib for elevated observers.

## Prayer methods and conventions

- **Muslim World League** — Fajr 18°, Isha 17°.
- **ISNA (Islamic Society of North America)** — Fajr 15°, Isha 15°.
- **Egyptian General Authority of Survey** — Fajr 19.5°, Isha 17.5°.
- **University of Islamic Sciences, Karachi** — Fajr 18°, Isha 18°.
- **Umm al-Qura University, Makkah** — Fajr 18.5°, Isha 90-minute interval (120 in Ramadan).
- **Moonsighting Committee** — Fajr 18°, Isha 18° with seasonal twilight adjustments (after moonsighting.com, 1/7-night based curves).
- **PrayTimes.org** — standard method/angle conventions catalogue.
- **Diyanet (Turkey)** — temkin offsets behind the Turkey preset (sunrise −7, Dhuhr +5, Asr +4, Maghrib +7), confirmed against official tables.
- **Kemenag (Indonesia)** — ihtiyat offsets behind the Kemenag preset, confirmed against official tables.
- **MUIS / KHEU** — the `+1` zohor convention behind the Singapore/Malaysia/Brunei Dhuhr +1.
- **MUIS prayer timetable 2025** — Majlis Ugama Islam Singapura, via
  data.gov.sg dataset `d_e81ea2337599b674c4f645c1af93e0dc` (retrieved
  2026-10-22). Subuh, Syuruk, Zohor, Asar, Maghrib, Isyak for the whole
  of 2025, Singapore local time (+08:00, no DST).
- **UOIF (France)** — pure 12°/12° preset cited to the 1994 Dar el-Fatwa text, the 2012 UOIF presidential statement (Saphirnews), ECFR Dublin 2004 toleration, communally confirmed current (Musulmans de France, Feb 2026 review), cross-checked against the praytimes.org France row. The 2015 14°/15° joint-commission compromise never landed.
- **Tehran / Morocco** — Tehran 17.7°/14°/Maghrib-4.5° cross-checked against `batoulapps/adhan-js@a2c4bda` `Tehran()` and drift-gated (convention, not published official rule); Morocco 19°/17° as the labelled Habous convention (Fajr 18-vs-19 undecided in sources as of 2026-10-11).

## Qibla geodesy

- **Isaac Todhunter, *Spherical Trigonometry* (p. 50).** The spherical bearing formula behind the default calculator, with haversine distance on the IUGG mean radius R1 = 6371.0088 km.
- **C. F. F. Karney, "Algorithms for geodesics", *J. Geodesy* 87, 43–55 (2013).** The ellipsoidal inverse behind `QiblaCalculator::wgs84()`, vendored from GeographicLib 2.1 (transcribed from the pinned reference, MIT/X11 © Charles Karney 2011–2022). Chosen over Vincenty (1975) because Karney's hybrid Newton/astroid iteration converges for all inputs including near-antipodal points.
- **WGS84 defining constants** — `a = 6378137.0 m`, `f = 1/298.257223563`.

## Crescent criteria

- **Bernard Yallop, "A Method for Predicting the First Sighting of the New Crescent Moon", NAO Technical Note No. 69 (HM Nautical Almanac Office, 1997).** The `q`-score zone system (A–F) fitted to catalogued sighting reports.
- **Mohammad Odeh, ICOP criterion (2006).** The `V`-score class system (A–D) with explicit Danjon floor, fitted to the Islamic Crescents' Observation Project dataset.
- **MABIMS 1992 (Labuan rule).** Altitude ≥ 2° and elongation ≥ 3°, or age at moonset ≥ 8 h — the regional rule of the Brunei–Indonesia–Malaysia–Singapore religious-ministers forum.
- **Neo-MABIMS 2021.** Altitude ≥ 3° and elongation ≥ 6.4° — the tightened replacement adopted after review of accumulated sightings.
- **Danjon limit.** The ~6–7° elongation floor below which a crescent is physically impossible (insufficient sun–moon angle for a visible sliver).

## Hijri calendars

- **Tabular (arithmetic Type IIa, "Kuwaiti" pattern).** Epoch 1 Muharram 1 AH = Julian Date 1948439.5 (622-07-19 proleptic Gregorian); 30-year cycle with leap remainders {2, 5, 7, 10, 13, 16, 18, 21, 24, 26, 29}; odd months 30 days, even 29, Dhu al-Hijjah 30 in leap years. Drifts ±1–2 days from observation by construction.
- **Umm al-Qura month-start rule (1423H scheme).** Evaluated at Makkah: on the 29th evening the month completes 29 days iff moonset lag is positive and moon age below 15 days at Makkah sunset, else 30. Earlier variants (1392H scheme, 1420–1422H interim) are out of scope. Tables regenerated by `tools/gen_hijri.py` with SHA-256 digests embedded as PHP constants.
- **Neo-MABIMS month-start rule.** Per-country proxy evaluation (Kuala Lumpur, Jakarta, Bandar Seri Begawan, Singapore): crescent altitude ≥ 3° and elongation ≥ 6.4° at sunset → 29-day month, else 30.

## Lineage

- **batoulapps** — the original `adhan` implementations (Java, JavaScript, Swift, Kotlin) from which the calculation methods, formulas, and architecture derive.
- **alphahm/adhanpy** — the original Python port this lineage continues from.
- **nexusnv/al-falak** — the Python sibling this PHP port keeps behavioural parity with, proven by shared golden vectors (`PYTHON_PIN`, see `CONTRIBUTING.md`).

## License

This project is MIT-licensed. Vendored Karney code retains its original MIT/X11 © Charles Karney notice in `src/Astro/Services/KarneyInverse.php`.
