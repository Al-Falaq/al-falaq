#!/usr/bin/env python3
"""Export M1 prayer parity goldens from the pinned al-falak source.

Usage:
    python3 tools/export_goldens.py --pin <sha> --path /path/to/pin-worktree --out tests/Goldens

Writes one CSV per pin method present in the PHP freeze (UOIF skipped:
no pin cell — the PHP preset is drift-gated via gen_self_prayer_goldens.php, Polar rows use the pin default rule NEAREST_LATITUDE so
every cell is defined; handler-specific semantics are unit-tested in PHP.
"""
import argparse
import csv
import sys
from datetime import datetime, timezone

MARKERS = ["imsak", "fajr", "sunrise", "syuruk", "ishraq",
           "dhuha", "dhuhr", "asr", "maghrib", "isha"]

LOCATIONS = [  # (label, lat, lon)
    ("kuala_lumpur", 3.1390, 101.6869),
    ("new_york", 40.7128, -74.0060),
    ("london", 51.5074, -0.1278),
    ("oslo", 59.9139, 10.7522),
    ("tromsoe", 69.6496, 18.9553),
    ("sydney", -33.8688, 151.2093),
    ("makkah", 21.4225, 39.8262),
    ("jakarta", -6.2000, 106.8500),
]

DATES = ["2025-01-15", "2025-03-20", "2025-06-21",
         "2025-09-22", "2025-12-15", "2025-03-02"]

# method value -> (pin Method attr, extra PrayerTimes kwargs per date-index hook)
METHODS = ["MUSLIM_WORLD_LEAGUE", "EGYPTIAN", "KARACHI", "UMM_AL_QURA",
           "DUBAI", "MOON_SIGHTING_COMMITTEE", "NORTH_AMERICA", "KUWAIT",
           "QATAR", "SINGAPORE", "JAKIM"]


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--pin", required=True)
    parser.add_argument("--path", required=True)
    parser.add_argument("--out", default="tests/Goldens")
    args = parser.parse_args()

    sys.path.insert(0, f"{args.path}/src")
    from alfalak.calculation.CalculationMethod import CalculationMethod
    from alfalak.calculation.CalculationParameters import CalculationParameters
    from alfalak.PrayerTimes import PrayerTimes

    header = (["method", "date", "latitude", "longitude"] + MARKERS + ["source"])
    for name in METHODS:
        method = CalculationMethod[name]
        rows = []
        for datestr in DATES:
            for _label, lat, lon in LOCATIONS:
                params = CalculationParameters(method=method)
                if name == "UMM_AL_QURA" and datestr == "2025-03-02":
                    # Ramadan 1446 UQU variant: same grid, Ramadan mode on.
                    params = CalculationParameters(method=method, is_ramadan=True)
                dt = datetime.strptime(datestr, "%Y-%m-%d").replace(tzinfo=timezone.utc)
                pt = PrayerTimes((lat, lon), dt, calculation_parameters=params)
                row = [method.name.lower(), datestr, f"{lat:.4f}", f"{lon:.4f}"]
                for marker in MARKERS:
                    value = getattr(pt, marker)
                    row.append(value.astimezone(timezone.utc).strftime("%Y-%m-%dT%H:%M:%S+00:00"))
                row.append(f"py:{args.pin}")
                rows.append(row)
        slug = method.name.lower()
        with open(f"{args.out}/prayer_{slug}.csv", "w", newline="") as handle:
            writer = csv.writer(handle)
            writer.writerow(header)
            writer.writerows(rows)
        print(f"wrote {args.out}/prayer_{slug}.csv ({len(rows)} rows)")


if __name__ == "__main__":
    main()
