# moon

M3 scope: crescent geometry at local sunset + Yallop/Odeh/MABIMS evaluators.
No weather, optics, horizon, or religious-ruling content: scores only.

## Characterization goldens (regression, not geometric truth)

Unlike Qibla (two float orders of one closed form), no independent
implementation exists: the golden is the committed pipeline recorded live
(~720 ephemeris evaluations per call). Tautology guards: structural
identities per row, exact-threshold criterion checks, and the committed
KL/Makkah agreement matrix — see `tools/gen_moonsighting.py`.

- `tests/Goldens/moon_crescent.csv` — 24 cities, crescent evening 2025-02-28
  (Ramadan-eve young crescent; mixed verdicts exercise every branch).
- `tests/Goldens/moon_eclipse.csv` — solar-eclipse new moon 2025-03-29
  (KL + Makkah; invisible everywhere).
- Float lock-in `abs=0.01` (arcminute-level; tighter pins libm noise);
  verdict tuples exact. Agreement matrix: KL Feb-28 `(F,D,false,true)`,
  Makkah Feb-28 `(E,C,true,true)`, both Mar-29 `(F,D,false,false)`.

## Pinned inputs

- Sunset depression `-50/60 deg`; earth equatorial radius `6378.137 km`
  (parallax); moon-age search 30-day window, hourly steps, `0.1`-day floor,
  youngest-minimum + forward-slope guard.
- Odeh: limit `-0.1018w³ + 0.7319w² − 6.3226w + 7.1651`, `V = arcv_topo − limit`,
  width epsilon `1e-6`, Danjon floor `ARCL < 6.4 → D`.
- Yallop: same cubic with `+ 11.8371`, `q = (arcv_geo − limit)/10`,
  zones A–F at `0.216/−0.014/−0.160/−0.232/−0.293`.
- MABIMS 1992: `(alt ≥ 2 and elong ≥ 3) or age_at_moonset ≥ 8 h`;
  Neo-2021: `alt ≥ 3 and elong ≥ 6.4`.
- Ephemeris: Meeus 2nd ed. Ch.47 lunar, Ch.15 transit/hour-angle; Delta-T
  polynomial (quote `used_delta_t_s` with any copied golden).

## Frame discipline (deliberate guards, not bugs)

- Yallop takes **geocentric** ARCV, Odeh takes **topocentric** ARCV, MABIMS
  takes **altitude** (never ARCV-minus-sunset), 1992 takes age **at moonset**.
  Swapping any moves boundary calls by arcminutes.
- TT/UTC split is load-bearing: sidereal time on the UTC instant, ephemeris
  on TT (~70 s Delta-T; ~0.3° altitude bias if merged). Never simplify to one
  instant.
- Zero width clamps to `1e-6′`; negative width raises `ValidationException`.
- `illumination = (1 − cos ARCL)/2`, `width = semi_diameter × (1 − cos ARCL)`
  with `semi_diameter = 0.27245 × parallax × 60′` — geometric proxies.

## Degenerate contract (invariants only, never geometry goldens)

- Polar night with no sunset (Tromsø 2025-01-01): raises
  `AstronomicalException` — no sunset to evaluate at.
- Sunset with circumpolar/no moonset (Tromsø 2025-01-15 `arcl≈160.86`,
  Reykjavik 2025-04-02 `arcl≈61.26`): finite geometry, `lag = NaN`,
  `age_at_moonset = NaN`. Never feed NaN age into the 1992 evaluator —
  fall back to the altitude/elongation branch.
- Eclipse-day ages hit the `0.1`-day floor; pre-conjunction evenings read
  ~29 d (previous lunation) and can spuriously pass the 1992 age branch —
  check `lag_hours` first.
- Sunset `q` understates visibility by design (best-time definition; ARCV
  grows after sunset). Makkah `E/C` on Feb-28 is conservative, not a bug.
