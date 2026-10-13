#!/usr/bin/env python3
"""Generate spherical-earth Qibla goldens (stdlib only).

Preserved from the scratch note Qiblat_Golden_Spherical_Earth.md (2026-10-10)
into project use as the M2 oracle reference. Not run by composer ci; kept so
tests/Goldens/qibla_spherical.csv can be regenerated and diff-reviewed.

Oracle: independent initial-bearing form + vector dot-product distance.
Qibla() is a cross-check only, not the oracle.
Degenerate self/antipode rows assert invariants only (finite float in
[0, 360), distance ≈ 0 / ≈ πR), never a bearing golden.

Contract: CSV goldens go to stdout (byte-identical to
tests/Goldens/qibla_spherical.csv); diagnostics (pin line, ellipsoidal
divergence table, degenerate invariants) go to stderr.

Gate (asserted on every run): angular bearing diff ≤ 1e-12 deg,
distance diff ≤ 1e-9 km. Observed maxima on the 23 cities are tighter
(≤ 2.9e-14 deg / ≤ 4.7e-12 km); the gate keeps ~100x margin for
cross-platform float order.

Run from this repo root against a checkout of the alfalak Python pin:
    PYTHONPATH=<path-to-alfalak-pin>/src python3 tools/gen_qibla_spherical.py > /tmp/qibla_regen.csv
    diff tests/Goldens/qibla_spherical.csv /tmp/qibla_regen.csv
"""

import csv
import math
import sys

from alfalak import Qibla
from alfalak.data.Constants import EARTH_MEAN_RADIUS_KM, MAKKAH
from golden_cities import CITIES_QIBLA as CITIES

BEARING_GATE_DEG = 1e-12
DISTANCE_GATE_KM = 1e-9


# Antipode longitude unwound to [-180, 180] (robust to sign, not just lon - 180).
ANTIPODE = [
    ("Makkah self", MAKKAH.latitude, MAKKAH.longitude),
    (
        "Antipode of Makkah",
        -MAKKAH.latitude,
        (MAKKAH.longitude + 180.0 + 540.0) % 360.0 - 180.0,
    ),
]


def ang_diff_deg(a: float, b: float) -> float:
    """Signed angular difference in (-180, 180] (wraparound-safe)."""
    return (a - b + 540.0) % 360.0 - 180.0


def bearing_independent(lat: float, lon: float) -> float:
    p1 = math.radians(lat)
    p2 = math.radians(MAKKAH.latitude)
    dl = math.radians(MAKKAH.longitude - lon)
    y = math.sin(dl) * math.cos(p2)
    x = math.cos(p1) * math.sin(p2) - math.sin(p1) * math.cos(p2) * math.cos(dl)
    return math.degrees(math.atan2(y, x)) % 360.0


def distance_vector(lat: float, lon: float) -> float:
    p1 = math.radians(lat)
    p2 = math.radians(MAKKAH.latitude)
    dl = math.radians(MAKKAH.longitude - lon)
    dot = math.sin(p1) * math.sin(p2) + math.cos(p1) * math.cos(p2) * math.cos(dl)
    dot = min(1.0, max(-1.0, dot))
    return EARTH_MEAN_RADIUS_KM * math.acos(dot)


source = (
    "py:alfalak-spherical-2026-10-10;"
    f"MAKKAH={MAKKAH.latitude},{MAKKAH.longitude};R={EARTH_MEAN_RADIUS_KM}"
)
print(f"# MAKKAH=({MAKKAH.latitude}, {MAKKAH.longitude}) R={EARTH_MEAN_RADIUS_KM}",
      file=sys.stderr)

writer = csv.writer(sys.stdout, lineterminator="\n")
writer.writerow(["city", "latitude", "longitude", "bearing_deg", "distance_km", "source"])
for name, lat, lon in CITIES:
    b = bearing_independent(lat, lon)
    d = distance_vector(lat, lon)
    q = Qibla((lat, lon))  # cross-check only
    assert abs(ang_diff_deg(b, q.direction)) < BEARING_GATE_DEG, (name, b, q.direction)
    assert abs(d - q.distance_to_makkah_km) < DISTANCE_GATE_KM, (name, d)
    qe = Qibla((lat, lon), method="ellipsoidal")
    delta = ang_diff_deg(qe.direction, q.direction)
    print(
        f"| {name} | {lat:.4f} | {lon:.4f} "
        f"| {b:.6f} | {d:.3f} | {delta:+.6f} |",
        file=sys.stderr,
    )
    writer.writerow([name, f"{lat:.4f}", f"{lon:.4f}", f"{b:.6f}", f"{d:.3f}", source])

for name, lat, lon in ANTIPODE:
    q = Qibla((lat, lon))
    assert 0.0 <= q.direction < 360.0 and math.isfinite(q.direction), name
    assert math.isfinite(q.distance_to_makkah_km), name
    print(f"# DEGENERATE {name}: sph=({q.direction!r}, {q.distance_to_makkah_km!r})",
          file=sys.stderr)
