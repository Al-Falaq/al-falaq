# prayer (M1-M5)

## Algorithm appendix (M1 — mirrors shipped code)

Engine transcribed from pin `a7baa8b202790d3bc84c47a610ac763be6c0b7e7`
(`nexusnv/al-falak`): `src/alfalak/astronomy/Astronomical.py`,
`src/alfalak/astronomy/SolarCoordinates.py`, `src/alfalak/astronomy/SolarTime.py`,
`src/alfalak/calculation/Twilight.py`. Meeus *Astronomical Algorithms* 2nd ed.:
Ch.7 (Julian Day), Ch.15 p.102 (approximate/corrected transit, corrected
hour-angle interpolation), Ch.25 pp.163–165 (solar coordinates), Ch.28 p.183
(equation of time), pp.24/93/144/147 (interpolation/altitude/nutation/obliquity).

- **Dip of horizon (pin-verbatim):** solar altitude `-50/60 − 0.0293·√h`
  degrees, `h` in metres (`MeanSolarCalculator::solarAltitude`, pin
  `SolarTime.__init__`). The PRD's `-0.833` is rounded display.
- **MSC seasonal twilight (corrected during M1):**
  `SeasonAdjustedTwilight::seasonalSeconds(dayOfYear, year, latitude)` returns
  `{morning, evening}` in whole SECONDS — `int(round(adj·60))`, pin
  `timedelta(seconds=±int(round(adjustment * 60.0)))`, not whole minutes.
  Seasonal safe caps apply to EVERY MSC row at any latitude (method-level
  safe); the seventh-of-night temp (`intdiv(nightSec, 7)`) replaces the angle
  temp only at signed latitude ≥ 55 (pin `_set_fajr`/`_set_isha` gate on
  `latitude >= 55`). `HighLatitudeRule::SEASONAL_TWILIGHT` mirrors MSC at all
  latitudes (same gating arm in `PrayerTimesCalculator::calculate()`).
- **Umm al-Qura Ramadan:** `isha = maghrib + 120 min` total in Ramadan, `+90`
  otherwise (interval anchored at sunset, which equals maghrib under UQU's
  0-min rule). `isRamadan` with any non-UQU method → `ConfigurationException`.
- **Polar circle (4 handlers):** `NearestLatitudeHandler` — 60-iteration
  bisection toward the equator, then 0.5° backoff; `NearestDayHandler` — ±366d
  scan, nearest-first (`-o, +o`); `MakkahHandler` — pin `Constants.MAKKAH`
  verbatim `(21.4225241, 39.8261818)`, input date kept; `ThrowHandler` (NONE
  rule) — echoes inputs so the re-probe throws `UndefinedScheduleException`
  (`extends AstronomicalException`). `PolarCircleRule::NONE` short-circuits
  before dispatch.
- **Adjustments:** effective offset = method + caller
  (`PrayerAdjustments::combinedWith`), applied via `Minutes::add` BEFORE a
  single half-up rounding (`Minutes::roundedHalfUp`) per marker. `syuruk` is a
  sunrise alias (same instant object; no syuruk slot — sunrise adjustments flow
  through to the syuruk/ishraq/dhuha bases). `imsak` = final fajr −
  `imsakOffsetMinutes` (then the IMSAK slot + rounding); ishraq/dhuha = adjusted
  sunrise + their offsets.
- **NONE method:** `defaultParameters()` throws `ConfigurationException` (no
  defaults); custom configs are built via `CalculationParameters::create()`.
- **Method-default freeze state (M1):** 18 methods, dual-entered from the pin
  (freeze `c151828`, `sha256 3bb6df93…9356`); `dhuhrOffsetMinutes` 0 everywhere
  (the aid column conflated `methodAdjustments.dhuhr`); ishraq 15 / dhuha 28
  (pin ctor defaults; the aid said 0/15 — corrected); KARACHI SHAFI per pin;
  `methodAdjustments` exactly per pin (MWL/EGYPTIAN/KARACHI/NA/SINGAPORE/JAKIM
  `{dhuhr:+1}`; DUBAI `{sunrise:−3,dhuhr:+3,asr:+3,maghrib:+3}`; MSC
  `{dhuhr:+5,maghrib:+3}`).
  TURKEY/KEMENAG/MUIS/BRUNEI were provisional at freeze, then confirmed against
  official tables (Diyanet temkin; Kemenag ihtiyat; MUIS/KHEU `+1` zohor
  convention — `28ff5ce`); TEHRAN/MOROCCO official-table arbitration was
  inconclusive (no machine-readable 30-day archive at fm6oa.org / UT
  Geophysics as of 2026-10-11 — NO preset change; drift-gated golden CSVs
  now exist via `tools/gen_self_prayer_goldens.php`: Tehran cross-checked
  against `batoulapps/adhan-js@a2c4bda` `Tehran()`, Morocco 19/17 stays a
  labelled Habous convention with fajr 18-vs-19 undecided, Tehran isha 14
  a convention-not-official). UOIF gap: pin-only method,
  no PHP preset (future minor, see issue #7).
