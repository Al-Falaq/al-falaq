# qibla

M2 scope: spherical earth only (WGS84/Karney deferred — see Technical-PRD.md §5.3, §18).

## Pinned inputs

- Kaaba / Makkah: `21.4225241, 39.8261818` (pin `src/alfalak/data/Constants.py:8`).
  Canonical precision is 4 dp (`21.4225, 39.8262`, ~11 m); goldens are tied to
  the exact stored digits — any re-canonicalization invalidates them.
- Mean radius: `6371.0088 km` (IUGG `R1 = (2a+b)/3`, pin `Constants.py:12`).
- Shipped formula: Todhunter p.50 bearing + haversine `atan2` distance
  (pin `src/alfalak/Qibla.py:81-104`).
- Oracle (independent float order, not a tautology): initial-bearing form +
  vector dot-product distance; script gates every run at angular bearing
  diff `≤ 1e-12 deg` / distance diff `≤ 1e-9 km` against `Qibla()`
  (observed maxima on the 23 cities are tighter: `≤ 2.9e-14 deg` /
  `≤ 4.7e-12 km`; the gate keeps ~100x margin for cross-platform float order).
- Goldens: `tests/Goldens/qibla_spherical.csv` (23 cities, 6-dp bearings
  `abs=1e-6`, 3-dp distances `abs=1e-3`); generator: `tools/gen_qibla_spherical.py`.
  The generator writes CSV goldens to stdout (byte-identical to the committed
  file) and diagnostics (pin line, ellipsoidal divergence table, degenerate
  invariants) to stderr; regenerate and diff-review with:
  `PYTHONPATH=<path-to-alfalak-pin>/src python3 tools/gen_qibla_spherical.py > /tmp/qibla_regen.csv`
  then `diff tests/Goldens/qibla_spherical.csv /tmp/qibla_regen.csv`.
  `tests/Goldens/QiblaGoldensTest.php` gates CSV structure (header, 23 rows,
  ranges, precision, source tags, no degenerate rows) plus `magneticHeading`
  vectors and numeric engine parity against the PHP spherical
  implementation (bearings ≤ 1e-6, distances ≤ 1e-3).

## Degenerate contract (invariants only, never bearing goldens)

- Makkah self: finite bearing in `[0, 360)`, distance `≈ 0`. The shipped
  Todhunter order yields `180.0` by construction (`atan2(0, tiny-negative)`);
  the independent order yields `0.0` — both equally valid, exclude from goldens.
- True antipode `(-21.4225241, -140.1738182)`: finite bearing in `[0, 360)`,
  no raise, no NaN; spherical distance golden `≈ πR ≈ 20015.114 km`
  (meaningful on the sphere; WGS84 differs with no single half-circumference).
- No golden within ~1° of the antipode (hyper-sensitive bearings); test
  invariants only there.
- `Qibla` never raises polar/high-latitude errors (a `PrayerTimes` concern);
  Tromsø `69.65N` is an ordinary golden.
- `magneticHeading(declinationEast)` feeds true-north goldens as
  `unwind(true − declination_east)` (untouched by the spherical oracle).
