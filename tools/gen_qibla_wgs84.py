#!/usr/bin/env python3
"""Generate WGS84-ellipsoidal Qibla goldens (stdlib only).

Not run by composer ci; kept so tests/Goldens/qibla_wgs84.csv can be
regenerated and diff-reviewed. Companion to gen_qibla_spherical.py — same
23-city grid, same CSV shape — but the oracle is the pin's `geodesic_inverse`
(GeographicLib 2.1 / Karney 2013 reference implementation for this slice)
instead of the independent spherical formulas, and the distance is the
ellipsoidal great-circle arc in km.

Degenerate self/antipode rows assert invariants only, never a bearing
golden: at the Makkah-self point the azimuth is construction-defined (the
engine's raw bearing there is one of two equally valid answers, and the PHP
strategy pins the public contract by clamping to 0.0), and the antipode has
no single half-circumference (infinitely many geodesics of equal length), so
neither is a meaningful oracle row. The PHP side pins these invariants —
finite direction/distance, self → ~0 distance — in
tests/Qibla/QiblaCalculatorTest.php, which is the reason the excludes-test
in tests/Goldens/QiblaGoldensTest.php must stay WGS84-compatible.

Contract: CSV goldens go to stdout (byte-identical to
tests/Goldens/qibla_wgs84.csv); diagnostics (pin line, per-city raw
engine output, degenerate invariants) go to stderr.

The bearing unwind mirrors src/Qibla/Strategies/Wgs84KarneyCalculator.php
exactly — `fmod`-style [0, 360) winding plus a re-clamp to 0.0, so generator
and implementation agree on the 360.0-ulp edge rather than diverging by
exactly one turn.

Run from this repo root against the checkout of the alfalak Python pin
pinned at a7baa8b202790d3bc84c47a610ac763be6c0b7e7:
    PYTHONPATH=<path-to-alfalak-pin>/src python3 tools/gen_qibla_wgs84.py > /tmp/qibla_wgs84_regen.csv
    diff tests/Goldens/qibla_wgs84.csv /tmp/qibla_wgs84_regen.csv
"""

import csv
import math
import sys

from alfalak.astronomy.Geodesy import geodesic_inverse
from alfalak.data.Constants import MAKKAH

CITIES = [
    ("Raleigh, US", 35.7750, -78.6336),
    ("New York, US", 40.7128, -74.0060),
    ("Los Angeles, US", 34.0522, -118.2437),
    ("Anchorage, US", 61.2181, -149.9003),
    ("Honolulu, US", 21.3099, -157.8581),
    ("Sao Paulo, BR", -23.5558, -46.6396),
    ("Buenos Aires, AR", -34.6037, -58.3816),
    ("Reykjavik, IS", 64.1466, -21.9426),
    ("London, UK", 51.5074, -0.1278),
    ("Tromso, NO", 69.6492, 18.9553),
    ("Moscow, RU", 55.7558, 37.6173),
    ("Istanbul, TR", 41.0082, 28.9784),
    ("Cairo, EG", 30.0444, 31.2357),
    ("Nairobi, KE", -1.2921, 36.8219),
    ("Cape Town, ZA", -33.9249, 18.4241),
    ("Madinah, SA", 24.5247, 39.5692),
    ("Delhi, IN", 28.6139, 77.2090),
    ("Singapore, SG", 1.3521, 103.8198),
    ("Kuala Lumpur, MY", 3.1390, 101.6869),
    ("Jakarta, ID", -6.2088, 106.8456),
    ("Tokyo, JP", 35.6762, 139.6503),
    ("Sydney, AU", -33.8688, 151.2093),
    ("Auckland, NZ", -36.8485, 174.7633),
]

# Antipode longitude unwound to [-180, 180] (robust to sign, not just lon - 180).
ANTIPODE = [
    ("Makkah self", MAKKAH.latitude, MAKKAH.longitude),
    (
        "Antipode of Makkah",
        -MAKKAH.latitude,
        (MAKKAH.longitude + 180.0 + 540.0) % 360.0 - 180.0,
    ),
]


def unwind(azi: float) -> float:
    """Wind a (-180, 180] azimuth into [0, 360), mirroring the PHP strategy.

    The re-clamp is not cosmetic: for an azimuth that unwinds to exactly
    360.0 (ulp(360) ≈ 5.7e-14, reachable via tiny negative azimuths) the
    result would otherwise leave [0, 360). PHP does
    ``fmod($azi, 360.0) + 360.0`` and then clamps, so the same rounding is
    reproduced here.
    """
    bearing = azi + 360.0 if azi < 0.0 else azi
    if bearing >= 360.0:
        bearing = 0.0
    return bearing


source = (
    "py:alfalak-a7baa8b;wgs84-karney;"
    f"MAKKAH={MAKKAH.latitude},{MAKKAH.longitude};a=6378137.0;f=1/298.257223563"
)
print(f"# MAKKAH=({MAKKAH.latitude}, {MAKKAH.longitude}) a=6378137.0 f=1/298.257223563",
      file=sys.stderr)

writer = csv.writer(sys.stdout, lineterminator="\n")
writer.writerow(["city", "latitude", "longitude", "bearing_deg", "distance_km", "source"])
for name, lat, lon in CITIES:
    azi, s12 = geodesic_inverse(lat, lon, MAKKAH.latitude, MAKKAH.longitude)
    bearing = unwind(azi)
    distance_km = s12 / 1000.0
    assert math.isfinite(bearing) and 0.0 <= bearing < 360.0, (name, azi, bearing)
    assert math.isfinite(distance_km) and distance_km > 0.0, (name, s12)
    print(f"# {name}: azi={azi!r} s12={s12!r}", file=sys.stderr)
    writer.writerow([name, f"{lat:.4f}", f"{lon:.4f}", f"{bearing:.6f}", f"{distance_km:.3f}", source])

for name, lat, lon in ANTIPODE:
    azi, s12 = geodesic_inverse(lat, lon, MAKKAH.latitude, MAKKAH.longitude)
    bearing = unwind(azi)
    assert math.isfinite(azi), name
    assert math.isfinite(bearing) and 0.0 <= bearing < 360.0, (name, azi, bearing)
    assert math.isfinite(s12), name
    if name == "Makkah self":
        assert abs(s12) < 1e-6, (name, s12)
    print(f"# DEGENERATE {name}: azi={azi!r} bearing={bearing!r} s12={s12!r}",
          file=sys.stderr)
