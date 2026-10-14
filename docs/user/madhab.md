---
title: Madhab
description: How Shafi and Hanafi Asr differ, why shadows define the afternoon prayer, and how to choose.
---

# Madhab

Asr is the only prayer defined by shadows rather than by the sun's height. Both major conventions agree on the *idea* — Asr begins when an object's shadow grows past its midday minimum by a set multiple of the object's height — and differ only on the multiple. This page explains the idea once, so the setting never feels arbitrary.

## The idea: shadows as clocks

At solar noon the sun stands highest and shadows are shortest. As the afternoon wears on, shadows lengthen. Classical jurisprudence defines Asr as the moment the shadow exceeds that noon minimum by a fixed amount:

- **Shafi (and Maliki, Hanbali):** shadow = noon shadow + **1×** the object's height.
- **Hanafi:** shadow = noon shadow + **2×** the object's height.

Hanafi Asr therefore always falls later than Shafi Asr on the same day — sometimes by tens of minutes in summer. Neither is "more correct" astronomically; they are different readings of the same texts, and your community will already have one.

## How al-falaq does it

There is no strategy class to choose and no branching flag to pass. The madhab is a closed enum carrying its own shadow factor, and the pipeline reads it directly:

```php
use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Enums\Madhab;

$params = CalculationMethod::KARACHI->defaultParameters()
    ->withMadhab(Madhab::HANAFI);

$madhab->shadowLength(); // 1.0 for Shafi, 2.0 for Hanafi
```

Every shipped preset defaults to `Madhab::SHAFI` — including Karachi, despite the name, matching the pinned reference. Switching is a one-word change (`withMadhab(Madhab::HANAFI)`), and the rest of the pipeline — transit, angles, offsets, rounding — is untouched. Older designs exposed an `AsrRule` seam with separate classes; the current design deleted it, because a two-valued multiple needs no polymorphism.

## Worked sense of the difference

On a temperate afternoon, the gap between Shafi and Hanafi Asr is typically 30–80 minutes, widest in summer when the sun's path is long and shallow. In the deep tropics the gap narrows; near the poles the Asr computation itself can become undefined on extreme days, in which case the polar fallback applies before Asr is ever evaluated (see [Polar Regions](polar-regions/)).

If you publish for a mixed community, label which Asr you show. Timetables that silently switch madhab between editions cause more confusion than any angle choice.

## See also

- [Calculation Methods](calculation-methods/) — the preset table (all default to Shafi)
- [Prayer Times](prayer-times/) — where Asr sits in the pipeline
- [API Reference](api-reference/) — `Madhab::shadowLength()`
