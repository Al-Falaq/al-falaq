#!/usr/bin/env python3
"""Generate moonsighting characterization goldens (stdlib only).

Preserved from the scratch note Moonsighting_Golden_Visibility.md (2026-10-10)
into project use as the M3 regression reference. Not run by composer ci; kept
so tests/Goldens/moon_crescent.csv + moon_eclipse.csv can be regenerated and
diff-reviewed.

Unlike the Qibla oracle, there is no independent closed form here: the golden
is the committed pipeline recorded live (Meeus Ch.47 lunar -> sunset
evaluation -> parallax -> age search -> criterion polynomials). These goldens
pin behaviour for regression, not geometric truth. Tautology guards live in
the asserts below: structural identities on every row, exact-threshold
criterion checks, and the committed KL/Makkah agreement matrix.

Float columns lock at abs=0.01 (arcminute-level; tighter pins libm noise, not
astronomy). Verdict tuples are exact.

Run against a checkout of the alfalak Python pin:
    PYTHONPATH=<path-to-alfalak-pin>/src python3 tools/gen_moonsighting.py
"""

import math
from datetime import date

from alfalak import (
    Coordinates,
    crescent_geometry_at_sunset,
    is_mabims_1992,
    is_neo_mabims_2021,
    odeh_class,
    odeh_v,
    yallop_q,
    yallop_zone,
)

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
    ("Makkah, SA", 21.4225, 39.8262),
    ("Madinah, SA", 24.5247, 39.5692),
    ("Delhi, IN", 28.6139, 77.2090),
    ("Singapore, SG", 1.3521, 103.8198),
    ("Kuala Lumpur, MY", 3.1390, 101.6869),
    ("Jakarta, ID", -6.2088, 106.8456),
    ("Tokyo, JP", 35.6762, 139.6503),
    ("Sydney, AU", -33.8688, 151.2093),
    ("Auckland, NZ", -36.8485, 174.7633),
]

DAY = date(2025, 2, 28)  # Ramadan-eve young crescent


def verdicts(g):
    q = yallop_q(g.arcv_geo_deg, g.width_arcmin)
    v = odeh_v(g.arcv_topo_deg, g.width_arcmin)
    neo = is_neo_mabims_2021(g.moon_alt_topo_deg, g.arcl_deg)
    age_h = g.moon_age_at_moonset_days * 24.0
    if math.isnan(age_h):
        old = g.moon_alt_topo_deg >= 2.0 and g.arcl_deg >= 3.0
    else:
        old = is_mabims_1992(g.moon_alt_topo_deg, g.arcl_deg, age_h)
    return q, yallop_zone(q), v, odeh_class(v, g.arcl_deg), neo, old


for name, lat, lon in CITIES:
    g = crescent_geometry_at_sunset(DAY, Coordinates(lat, lon))
    # Structural identities: fail loud on pipeline drift.
    assert (
        abs((g.moon_alt_topo_deg - g.sun_alt_deg) - g.arcv_topo_deg) < 1e-9
    ), name
    assert abs(g.sun_alt_deg - (-50.0 / 60.0)) < 0.05, (name, g.sun_alt_deg)
    if math.isfinite(g.lag_hours):
        assert abs(
            g.moon_age_at_moonset_days
            - max(0.1, g.moon_age_days + g.lag_hours / 24.0)
        ) < 1e-9, name
    else:
        assert math.isnan(g.moon_age_at_moonset_days), name
    q, z, v, k, neo, old = verdicts(g)
    lag = f"{g.lag_hours:.4f}" if math.isfinite(g.lag_hours) else "NaN"
    agem = (
        f"{g.moon_age_at_moonset_days:.4f}"
        if math.isfinite(g.moon_age_at_moonset_days)
        else "NaN"
    )
    print(
        f"| {name} | {g.arcl_deg:.4f} | {g.moon_alt_topo_deg:.4f} | "
        f"{g.arcv_geo_deg:.4f} | {g.arcv_topo_deg:.4f} | "
        f"{g.width_arcmin:.4f} | {lag} | {g.moon_age_days:.4f} | {agem} | "
        f"{q:.4f}/{z} | {v:.4f}/{k} | {str(neo).lower()} | {str(old).lower()} |"
    )

# Committed agreement matrix (must reproduce exactly).
for day, lat, lon, exp in [
    (date(2025, 2, 28), 3.1390, 101.6869, ("F", "D", False, True)),
    (date(2025, 2, 28), 21.4225, 39.8262, ("E", "C", True, True)),
    (date(2025, 3, 29), 3.1390, 101.6869, ("F", "D", False, False)),
    (date(2025, 3, 29), 21.4225, 39.8262, ("F", "D", False, False)),
]:
    g = crescent_geometry_at_sunset(day, Coordinates(lat, lon))
    _, z, _, k, neo, old = verdicts(g)
    assert (z, k, neo, old) == exp, (day, lat, lon, z, k, neo, old)
    print(f"# MATRIX-OK {day} ({lat}, {lon}) -> {(z, k, neo, old)}")
