---
title: High Latitude Rules
description: Why Fajr and Isha need night fractions near the poles, and how each rule resolves them.
---

# High Latitude Rules

In Kuala Lumpur the night is reliably dark: the sun sinks well past 18° below the horizon, so Fajr and Isha angles always occur. In Oslo in June it never gets that dark. The sun may dip only 10° below the horizon all night — the 18° angle your method asks for *never happens*. Without a fallback, there would simply be no Fajr and no Isha.

High-latitude rules are those fallbacks. They answer: *when the angle never occurs, what fraction of the night stands in for it?*

## The night, divided

Picture the night as the span from sunset to sunrise. Each rule claims a slice of it:

| Rule | Idea | Fajr portion | Isha portion |
| --- | --- | --- | --- |
| `MIDDLE_OF_NIGHT` (default) | Split the night in half; Fajr and Isha each get up to half | ½ night | ½ night |
| `SEVENTH_OF_NIGHT` | A stricter slice for communities that want earlier Isha / later Fajr | ⅐ night | ⅐ night |
| `TWILIGHT_ANGLE` | Interpolate between the method's angles rather than using a fixed fraction | angle-derived | angle-derived |
| `SEASONAL_TWILIGHT` | Mirror the Moonsighting Committee's seasonal curves at any latitude | seasonal cap | seasonal floor |
| `NONE` | No fallback — throw rather than estimate | — | — |

The default (`MIDDLE_OF_NIGHT`) ships with every preset. It says: Fajr cannot be earlier than the middle of the night, Isha cannot be later than it. That single guard keeps summer timetables in Stockholm sane without touching winter days, when the angles occur naturally and the rule stays silent.

```php
use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Enums\HighLatitudeRule;

$params = CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters()
    ->withHighLatitudeRule(HighLatitudeRule::SEVENTH_OF_NIGHT);
```

## How the pipeline applies them

The rule engages only when the angle computation would fall outside its allowed portion of the night. On ordinary days at ordinary latitudes, your choice of rule changes nothing — the angles occur well within bounds and pass through untouched. That is why switching rules is safe to experiment with: it only affects the days that need it.

Two details matter for correctness. First, the seventh-of-night temperature replaces the angle time only at signed latitudes at or above 55° — below that, the seasonal safe caps apply to every method without replacing anything. Second, the Moonsighting Committee method (and `SEASONAL_TWILIGHT` generally) uses whole-second seasonal adjustments with safe caps at *all* latitudes, not just the far north.

## Which rule should you use?

- **Publishing for a general audience in a high-latitude city?** Stay with `MIDDLE_OF_NIGHT`. It is the most widely recognised behaviour.
- **Your community follows a stricter interpretation?** `SEVENTH_OF_NIGHT` is the common alternative.
- **Following moonsighting.com conventions?** Use the Moonsighting Committee method itself, which brings its seasonal curves automatically.
- **Writing a research comparison?** `NONE` exposes exactly which days have no angle solution, by throwing instead of estimating.

If the sun never rises or sets at all — not just the twilight angles, but sunrise and sunset themselves — you have left high-latitude territory and entered the polar circle. That is the next page.

## See also

- [Polar Regions](polar-regions/) — when sunrise itself is missing
- [Calculation Methods](calculation-methods/) — the Moonsighting Committee seasonal curves
- [Prayer Times](prayer-times/) — where night resolution sits in the pipeline
