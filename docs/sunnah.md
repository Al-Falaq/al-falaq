# sunnah

M2 scope: night markers from two UTC schedules.

## Night definition

Night runs `today.maghrib → tomorrow.fajr`, computed in UTC (DST-safe);
`Minutes::roundedHalfUp` applies in UTC and `forZone()` converts last.
`SunnahCalculator::fromCalculator()` derives tomorrow internally via
`CivilDate::plusDays(1)`. If either endpoint is polar-undefined the prayer
pipeline throws first — `SunnahCalculator` never invents a night.

## Markers

`SunnahTimes{middleOfNight, firstThird, lastThird, fajr, maghrib}` as calculated are
all UTC, with `tahajjudWindow() === [lastThird, fajr)`. `forZone()` returns
the same value object with every marker converted to the requested display
zone (instants preserved). Degenerate intervals
(fajr ≤ maghrib, fajr ≤ lastThird) are rejected with `ValidationException`,
never clamped.

## Night fractions

`SunnahTimes::nightFraction()` returns the instant at fraction `f ∈ (0, 1)`
exclusive between maghrib and fajr (`0.5` = middle of night, `1.0/3.0` and
`2.0/3.0` = thirds). It normalizes maghrib/fajr to UTC, applies the same
`round(nightSeconds * f)` + `Minutes::roundedHalfUp` arithmetic as
`SunnahCalculator`, then converts back to the maghrib zone — so `forZone()`
commutes with it and fraction math never throws on non-UTC markers.

## See also

- [Sunnah Times](user/sunnah-times.md) — the published user guide
