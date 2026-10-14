---
title: Qibla Direction
description: Find the direction to Makkah from anywhere, choose between
  spherical and ellipsoidal earth models, and handle compass headings.
slug: v0.1.0/qibla
---

# Qibla Direction

The Qibla is the direction to the Kaaba in Makkah — the direction Muslims face in prayer. Before touching code, it helps to understand the one decision that shapes every Qibla number: *what shape do we assume the Earth is?*

## How we model the Earth: spheres vs ellipsoids

A bearing between two cities depends on the model of the planet beneath them. Two models matter here:

* **The sphere** treats the Earth as a perfect ball of radius 6371.0088 km (the IUGG mean radius). Bearings come from classical spherical trigonometry — specifically the formula in **Isaac Todhunter's *Spherical Trigonometry*** (19th century, p. 50), with distances from the haversine formula. Todhunter (1820–1884) was a Cambridge mathematician whose textbooks standardised how generations of navigators solved exactly this triangle. The spherical answer is cheap to compute and good to a few tenths of a degree.
* **The WGS84 ellipsoid** treats the Earth as slightly squashed — wider at the equator (6378.137 km) than pole-to-pole (6356.752 km), with flattening `f = 1/298.257223563`. WGS84 (World Geodetic System 1984) is the reference frame GPS itself uses. Bearings on it come from solving the *inverse geodesic problem*, and the algorithm that does so reliably is **C. F. F. Karney's "Algorithms for geodesics" (*Journal of Geodesy* 87, 2013)**. Karney, a physicist at SRI International, designed a hybrid Newton/astroid iteration that converges for *every* input — including near-antipodal points where the older Vincenty (1975) iteration famously spins forever. al-falaq vendors a subset of his GeographicLib 2.1 implementation (MIT/X11 © Charles Karney 2011–2022).

Measured across 23 cities on every continent, the two models differ by at most ~0.25° in bearing (Auckland) with a median around 0.10°, and up to ~26 km in distance (Cape Town). Neither is "wrong" — they answer slightly different geometric questions. Use the sphere for facing a room correctly; use the ellipsoid for mosque construction, signage, and research.

## Basic usage

Give the calculator your location; it returns a bearing in `[0, 360)` degrees clockwise from true north, plus the distance in kilometres:

```php
use AlFalaq\AlFalaq\Qibla\Services\QiblaCalculator;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

$result = QiblaCalculator::default()->calculate(
    Coordinates::fromDegrees(3.1390, 101.6869), // Kuala Lumpur
);

echo round($result->direction, 2);   // ~292.54
echo round($result->distanceKm, 1);  // ~6973.9 km
```

`QiblaCalculator::default()` is the spherical fast path — Todhunter's bearing formula with a haversine distance on the IUGG mean radius (6371.0088 km). It is cheap, dependency-free, and good to a few tenths of a degree. For most prayer apps, it is the right default.

## When precision matters: WGS84

The Earth is not a sphere; it bulges at the equator. The WGS84 ellipsoid models that bulge, and the Karney (2013) geodesic algorithm solves bearings on it to nanometre accuracy. Switching is a one-word change, and the result shape is identical:

```php
$precise = QiblaCalculator::wgs84()->calculate(
    Coordinates::fromDegrees(3.1390, 101.6869),
);
```

How different are they? Measured across 23 cities on every continent: the largest bearing gap is about 0.25° (Auckland), the median around 0.10°; the largest distance gap is about 26 km (Cape Town). The spread depends on geometry, not just distance — far cities do not always differ most. Practical reading: the spherical default is fine for facing a room correctly; the ellipsoid is the opt-in for when tenths of a degree matter (mosque construction, qibla signage, research).

Unlike Vincenty's older iteration, Karney's converges for every input — including near-antipodal points, where Vincenty famously fails. The proof case (a point in the South Pacific whose geodesic to Makkah spans half the planet) is pinned in the test suite and converges to `31.740215702°`.

## Compass headings need your declination

`direction` is measured from **true north**. A magnetic compass points at **magnetic north** instead, and the two can differ by many degrees depending on where you stand. The library does not look up your declination — models drift over time and bundling one would silently go stale — but it does the arithmetic once you supply it:

```php
$compass = $result->magneticHeading(-8.0); // 8° west declination (synthetic)
```

The sign convention follows NOAA: declination is positive east of true north, and `magnetic = true − declination_east`. Eastern values subtract; pass western values as negative and they add. Get a current value from a compass app, chart, or magnetic model (e.g. WMM, noting its epoch — model values drift year to year), and always state the model and date wherever you publish compass headings.

## Edge cases that never raise

Two places on Earth have no meaningful bearing, and the library refuses to pretend otherwise:

* **At Makkah itself** (within 1 metre of the Kaaba), every bearing is equally valid. The result clamps to direction `0.0` and distance `0.0` rather than leaking a construction-defined angle.
* **At the true antipode** (~21.42°S, 140.17°W), every bearing is equidistant. The result stays finite with a distance near 20,000 km; the bearing is a reference point, not a golden value — never navigate by it.

Qibla never raises polar or high-latitude errors (those are a prayer-times concern); Tromsø is an ordinary city here. The Kaaba coordinates (`21.4225241, 39.8261818`) live on the strategy seam, never in the astronomy engine, and 4 decimal places (`21.4225, 39.8262`, ~11 m) are canonical.

## See also

* [Timezones & Display](timezone/) — Qibla has no timezone; bearings are pure geometry
* [API Reference](api-reference/) — `QiblaCalculator`, `QiblaResult::magneticHeading()`
* [Citations](citations/) — Karney (2013), Todhunter, IUGG radius
