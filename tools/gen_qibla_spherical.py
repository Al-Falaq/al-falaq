#!/usr/bin/env python3
"""Generate spherical-earth Qibla goldens (stdlib only).

Preserved from the scratch note Qiblat_Golden_Spherical_Earth.md (2026-10-10)
into project use as the M2 oracle reference. Not run by composer ci; kept so
tests/Goldens/qibla_spherical.csv can be regenerated and diff-reviewed.

Oracle: independent initial-bearing form + vector dot-product distance.
Qibla() is a cross-check only, not the oracle.
Degenerate self/antipode rows assert invariants only (finite float in
[0, 360), distance ≈ 0 / ≈ πR), never a bearing golden.

Run from the al-falak pin repo root:
    PYTHONPATH=src python3 gen_qibla_spherical.py
"""

import math

from alfalak import Qibla
from alfalak.data.Constants import EARTH_MEAN_RADIUS_KM, MAKKAH

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

DEGENERATE = [
    ("Makkah self", MAKKAH.latitude, MAKKAH.longitude),
    ("Antipode of Makkah", -MAKKAH.latitude, MAKKAH.longitude - 180.0),
]


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


print(f"# MAKKAH=({MAKKAH.latitude}, {MAKKAH.longitude}) R={EARTH_MEAN_RADIUS_KM}")
for name, lat, lon in CITIES:
    b = bearing_independent(lat, lon)
    d = distance_vector(lat, lon)
    q = Qibla((lat, lon))  # cross-check only
    assert abs(b - q.direction) < 1e-9, (name, b, q.direction)
    assert abs(d - q.distance_to_makkah_km) < 1e-6, (name, d)
    qe = Qibla((lat, lon), method="ellipsoidal")
    delta = (qe.direction - q.direction + 540.0) % 360.0 - 180.0
    print(
        f"| {name} | {lat:.4f} | {lon:.4f} "
        f"| {b:.6f} | {d:.3f} | {delta:+.6f} |"
    )

for name, lat, lon in DEGENERATE:
    q = Qibla((lat, lon))
    assert 0.0 <= q.direction < 360.0 and math.isfinite(q.direction), name
    assert math.isfinite(q.distance_to_makkah_km), name
    print(f"# DEGENERATE {name}: sph=({q.direction!r}, {q.distance_to_makkah_km!r})")
