#!/usr/bin/env python3
"""Shared city grids for golden generators (single owner, stdlib only).

The Qibla oracles (spherical + WGS84) and the moonsighting characterization
run the same 23-city grid; the crescent grid adds the Makkah-self geometry
(Qibla excludes it as degenerate — bearing goldens never pin it). Order is
conventional: goldens pin content per row, not sequence, so keep the grids
stable and the diffs will stay clean.
"""

CITIES_QIBLA = [
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

# Moon grid mirrors the Qibla grid with the Makkah-self geometry inserted
# after Cape Town (its committed position — keep stable so regens diff cleanly).
CITIES_MOON = (
    CITIES_QIBLA[:15]
    + [("Makkah, SA", 21.4225, 39.8262)]
    + CITIES_QIBLA[15:]
)


def antipode_rows(makkah_lat: float, makkah_lon: float) -> list[tuple[str, float, float]]:
    """Degenerate Qibla rows (Makkah-self + antipode), owned once here.

    Antipode longitude unwound to [-180, 180] (robust to sign, not just
    lon - 180). Both Qibla generators assert invariants on these rows;
    bearing goldens never pin them.
    """
    return [
        ("Makkah self", makkah_lat, makkah_lon),
        (
            "Antipode of Makkah",
            -makkah_lat,
            (makkah_lon + 180.0 + 540.0) % 360.0 - 180.0,
        ),
    ]
