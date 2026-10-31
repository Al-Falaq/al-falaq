# polar — polar-circle fallback reference (M1)

Inside the polar circle the sun may not rise or set, so `isSolarDefined()`
fails and `PrayerTimesCalculator::calculate()` resolves substitute inputs via
the `PolarCircleRule` selector before computing. `NONE` short-circuits before
dispatch (throws `UndefinedScheduleException` directly); the other three rules
dispatch to a `PolarCircleHandler` whose result is re-probed — a still-unsolvable
substitute throws `UndefinedScheduleException` too.

| `PolarCircleRule` | Handler | Behavior |
|---|---|---|
| `NEAREST_LATITUDE` (default — every shipped preset) | `NearestLatitudeHandler` | 60-iteration bisection toward the equator for a solvable sunrise, then 0.5° backoff; date kept |
| `NEAREST_DAY` | `NearestDayHandler` | ±366-day scan, nearest-first (`-o, +o` per offset); location kept; throws `AstronomicalException` at the exact poles where no such date exists |
| `MAKKAH` | `MakkahHandler` | Substitute Makkah `(21.4225241, 39.8261818)` (pin `Constants.MAKKAH` verbatim); date kept |
| `NONE` | `ThrowHandler` | Echoes inputs unchanged so the re-probe throws `UndefinedScheduleException` (`extends AstronomicalException`) |

`UndefinedScheduleException::polar()` names the active rule and points at this
guide. See `docs/prayer.md` for the algorithm appendix.

## See also

- [Polar Regions](user/polar-regions.md) — the published user guide
