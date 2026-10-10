# sunnah

M2 scope: night markers from two UTC schedules.

## Night definition

Night runs `today.maghrib → tomorrow.fajr`, computed in UTC (DST-safe);
`Minutes::roundedHalfUp` applies in UTC and `forZone()` converts last.
`SunnahCalculator::fromCalculator()` derives tomorrow internally via
`CivilDate::plusDays(1)`. If either endpoint is polar-undefined the prayer
pipeline throws first — `SunnahCalculator` never invents a night.

## Markers

`SunnahTimes{middleOfNight, firstThird, lastThird, fajr}` (all UTC) with
`tahajjudWindow() === [lastThird, fajr)`. Degenerate intervals
(fajr ≤ maghrib) are rejected with `ValidationException`, never clamped.

## Deferred

`SunnahTimes::nightFraction()` stays a throwing stub: the VO carries no
maghrib anchor, so no fraction has a defined base. Retained per the approved
M2 design; the post-M0 no-`LogicException` rule is enforced at the pre-M5
sweep (see the M2 design spec).
