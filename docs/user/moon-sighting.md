---
title: Moon Sighting
description: What crescent visibility means, who defined the four criteria and how each algorithm works, then how to compute them.
---

# Moon Sighting

Whether the new crescent can be seen on a given evening is one of the oldest applied-astronomy questions. It opens Ramadan, ends it, and starts each Hijri month — and for over a thousand years, the answer came from someone standing on a hill at sunset, looking west. This page explains what that observer was judging, how four published criteria turned that judgement into arithmetic, and only then how to compute them with al-falaq.

Weather, optics, horizon obstructions, and religious rulings are deliberately out of scope: the library returns geometric scores, never verdicts on practice.

## The vocabulary first: what are we measuring?

At sunset on the 29th of a Hijri month, the moon is a thin crescent low in the west — if it is there at all. Six quantities describe the scene, and every criterion below is built from them:

| Term | What it is, in plain words |
| --- | --- |
| **Sunset** | The moment the sun's upper edge dips below the horizon. Everything is evaluated here, because this is when the observer looks. |
| **Moon age** | Hours since conjunction (the astronomical new moon, when sun and moon share ecliptic longitude). A 10-hour-old moon is fundamentally harder to see than a 25-hour-old one. |
| **Altitude** | How high the moon stands above the horizon at sunset, in degrees. Higher means clearer of haze. |
| **ARCL (elongation)** | The angular separation between the sun and the moon — the length of the sky between them. Below about 6–7°, the crescent is physically impossible (the Danjon limit): there is not enough angle for sunlight to paint a visible sliver. |
| **ARCV (arc of vision)** | The vertical component of that separation — roughly, moon altitude minus sun altitude. This is the "how far above the sunset glow" number, and the workhorse of the Yallop and Odeh criteria. |
| **Lag** | Minutes between sunset and moonset. A moon that sets 10 minutes after the sun gives you a 10-minute window; one that sets *before* the sun gives you none. |
| **Width and illumination** | The crescent's thickness (arcminutes) and lit fraction — proxies for contrast against twilight. |

al-falaq bundles all of these into one immutable `CrescentGeometry` per evening. The criteria then score the same bundle differently — which is why you can compute the sky once and ask all four opinions.

## Yallop (1997): the astronomer who catalogued sightings

**Who and when.** Bernard Yallop was an astronomer at HM Nautical Almanac Office in the United Kingdom. In 1997 he published *"A Method for Predicting the First Sighting of the New Crescent Moon"* (NAO Technical Note No. 69) — the first widely adopted criterion built by fitting mathematics to a large catalogue of real sighting reports, both positive and negative, rather than reasoning from optics alone.

**The idea.** Yallop asked: given the moon's width (which encodes age, distance, and illumination in one number) and the geocentric arc of vision, can we draw boundary curves separating evenings when people *did* see the crescent from evenings when they *did not*? His answer is a single number, `q`:

```
limit  = -0.1018·w³ + 0.7319·w² − 6.3226·w + 11.8371   (w = crescent width, arcmin)
q      = (ARCV_geocentric − limit) / 10
```

The cubic `limit` is the best-time curve — the ARCV at which a crescent of width `w` sits exactly on the edge of visibility. Dividing the surplus by 10 normalises it into zones:

| Zone | `q` range | Meaning |
| --- | --- | --- |
| A | > 0.216 | Easily visible with the naked eye |
| B | > −0.014 | Visible under perfect conditions |
| C | > −0.160 | May need optical aid to find, then visible naked-eye |
| D | > −0.232 | Visible with binoculars or telescope only |
| E | > −0.293 | Not visible, even with optical aid (but above the physical limit) |
| F | ≤ −0.293 | Not visible — below the physical possibility curve |

al-falaq counts zones A–D as `visible: true` and E–F as `false` (a PHP convention; Yallop's paper returns only the letter). Two deliberate quirks are transcribed faithfully: the criterion takes **geocentric** ARCV (measured from Earth's centre, not the observer's surface), and it is evaluated **at sunset** even though the crescent keeps improving for minutes afterward — so Yallop understates visibility by design. A Makkah `E` on a very young evening is conservative, not a bug.

## Odeh (2006): the engineer who re-fitted the data

**Who and when.** Mohammad Odeh ( ICOP — the Islamic Crescents' Observation Project, which he founded) published his criterion in 2006 after collecting one of the largest modern sighting datasets, with heavy coverage from the Middle East. Where Yallop fitted one global curve, Odeh re-fitted the boundary as a function of the same width polynomial but calibrated the zone thresholds against newer reports and separated the Danjon floor explicitly.

**The idea and the algorithm.** The structure mirrors Yallop's, with two differences that matter:

```
limit = -0.1018·w³ + 0.7319·w² − 6.3226·w + 7.1651
V     = ARCV_topocentric − limit
```

First, Odeh takes **topocentric** ARCV (measured from the observer, parallax-corrected) where Yallop takes geocentric — swapping them moves boundary calls by arcminutes, so the frame discipline is load-bearing. Second, the Danjon limit is enforced as a hard floor: any evening with `ARCL < 6.4°` is class D regardless of `V`. Above the floor, classes run `A (V ≥ 5.65)`, `B (V ≥ 2.0)`, `C (V ≥ −0.96)`, else `D`; anything but D counts as visible. The constant differs (`+7.1651` vs Yallop's `+11.8371`) because the two authors fitted different datasets to different frames — they are cousins, not duplicates.

## MABIMS 1992 and Neo-MABIMS 2021: the committee criteria

**Who and when.** MABIMS is the informal ministerial forum of Brunei, Indonesia, Malaysia, and Singapore (Menteri Agama Brunei, Indonesia, Malaysia, Singapura). In **1992** its scholars agreed the first regional rule: the crescent counts as seen when the moon stands at least 2° high with at least 3° elongation, *or* the moon is at least 8 hours old at moonset. For nearly thirty years this Labuan rule governed official calendars across the region. In **2021**, after reviewing accumulated sighting records (notably that 2°/3° admitted evenings nobody ever actually saw), the forum adopted the stricter **Neo-MABIMS** rule: altitude ≥ 3° **and** elongation ≥ 6.4°, with the age branch dropped.

**The algorithms, plainly.** These are threshold rules, not fitted curves — deliberately simple enough for a committee to apply from tables:

- **1992:** visible if `(altitude ≥ 2° AND elongation ≥ 3°) OR (age at moonset ≥ 8 h)`. Note *altitude*, never ARCV — the committee judged height above the horizon, not height above the sun. And note *age at moonset*, not at sunset: an old moon that lingers counts even if it was low at sunset.
- **2021:** visible if `(altitude ≥ 3° AND elongation ≥ 6.4°)` — both must hold, no age fallback. The 6.4° elongation floor matches the Danjon physics; the 3° altitude floor clears the horizon haze that produced false positives under the old 2°.

al-falaq transcribes both exactly (`>=` on every threshold), with the 1992 evaluator owning the no-moonset window: when the moon never sets, age-at-moonset is null and the verdict falls back to the altitude/elongation branch alone.

## Computing it: geometry first, criteria second

With the concepts in place, the code reads naturally. The service works in two phases — run the sky once, score it four ways:

```php
use AlFalaq\AlFalaq\Moon\Services\CrescentCalculator;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

$calculator = CrescentCalculator::default();
$coords = Coordinates::fromDegrees(3.1390, 101.6869);
$date = CivilDate::fromYmd(2025, 2, 28); // Ramadan-eve young crescent

// Phase 1: the sky at sunset (Meeus ch. 47 lunar theory, ~1.4k ephemeris
// evaluations: hourly conjunction search, solar/lunar positions, sidereal
// time, parallax-corrected topocentric moon, analytic moonset and lag).
$geometry = $calculator->atSunset($coords, $date);

// Phase 2: four criteria score that one geometry bundle (pure polynomials,
// no further astronomy — safe to store and re-score later).
$verdicts = $calculator->evaluateGeometry($geometry);

// Or both at once:
$verdicts = $calculator->evaluate($coords, $date);
$verdicts['yallop']->visible; // bool (zones A–D)
$verdicts['yallop']->zone;    // 'A'..'F'
$verdicts['odeh']->zone;      // 'A'..'D'
$verdicts['mabims1992']->visible;
$verdicts['neomabims2021']->visible;
```

Frame discipline worth knowing (deliberate guards, not bugs): Yallop reads geocentric ARCV, Odeh reads topocentric ARCV, MABIMS reads altitude — never interchange them. The sun's altitude stays geocentric throughout (the reference applies parallax to the moon only). Sidereal time uses the UTC instant while the ephemeris uses TT (~70 s Delta-T apart; merging them biases altitudes by ~0.3°).

On the Ramadan-eve test evening (2025-02-28), Kuala Lumpur scores `(F, D, false, true)` and Makkah `(E, C, true, true)` — mixed verdicts that exercise every branch, pinned in the goldens. On the eclipse-day new moon (2025-03-29), both cities are invisible everywhere.

## Degenerate skies

- **Polar night with no sunset** (Tromsø, 2025-01-01): there is no sunset to evaluate at, so `atSunset()` raises `AstronomicalException`.
- **Sunset with no moonset** (circumpolar moon): the geometry is finite with `lag` and `ageAtMoonset` both null. Pass it through `evaluateGeometry()` unchanged — the null window resolves inside the 1992 evaluator's altitude/elongation-only fallback. No caller-side special-casing.
- **Eclipse-day ages** hit the 0.1-day floor; pre-conjunction evenings read ~29 days (the previous lunation) and can spuriously pass the 1992 age branch — check `lag` first.

## A note on what scores are not

A `visible: true` from Yallop does not declare Ramadan open, and a `false` does not declare it closed. Actual month starts fold in weather, observer experience, horizon quality, and the local authority's ruling — and the per-country MABIMS tables in [Hijri Converter](hijri-converter/) already encode how one such folding is done at national-proxy level. Use crescent scores as *evidence for* a decision process, never as the decision.

## See also

- [Hijri Converter](hijri-converter/) — how Neo-MABIMS becomes month-start tables
- [Errors](errors/) — `AstronomicalException` when there is no sunset
- [Citations](citations/) — Yallop (1997), Odeh (2006), MABIMS 1992 / 2021, Meeus ch. 47
