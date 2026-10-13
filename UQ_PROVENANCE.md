# Umm al-Qura & MABIMS Provenance (M4)

## What ships

`src/Hijri/Data/UmmAlQuraMonthStarts.php` and
`src/Hijri/Data/MabimsMonthStarts.php` are **generated data classes**, not
transcriptions of any published table. They hold the month-start Gregorian days
produced by evaluating the pinned reference implementation's rules, so
conversion matches the pin exactly. There is no third-party table to license.

## Rules encoded

- **Umm al-Qura** (evaluated at Makkah, 1423-01 … 1500-12 AH): on the 29th
  evening the month completes 29 days iff the moonset lag is positive and the
  moon age is below 15 days at Makkah sunset; otherwise 30.
- **Neo-MABIMS** (evaluated at each member state's national proxy point,
  1445-01 … 1500-12 AH): crescent altitude ≥ 3° and elongation ≥ 6.4° at sunset.
  Proxies are recorded in `MabimsCountry::referenceCoordinates()`.

## Reproducibility

- Pin: `PYTHON_PIN = a7baa8b202790d3bc84c47a610ac763be6c0b7e7`
  (`nexusnv/al-falak`, module path `src/alfalak/calendar/`).
- Generator: `python3 tools/gen_hijri.py --path <pinned-checkout>`.
- Canonical digest: `sha256("\n".join(starts))`, embedded as
  `UmmAlQuraMonthStarts::SHA256` and `MabimsMonthStarts::SHA256_{MY,ID,SG,BN}`.
- Current digests (from the current generator run):

| Table | Months | SHA256 |
|---|---|---|
| UQ 1423–1500 AH | 936 | `21903bf9442b546908ab18a62f173e2011c760d1247a6cfcf46bb8f7584c0f9f` |
| MABIMS MY 1445–1500 AH | 672 | `4b3e2a9a62ea1683616219b4373e988364dddb4f710bd6793369c2e3a5bd70fc` |
| MABIMS ID 1445–1500 AH | 672 | `56e6ea28b91c77503e2577d35d41013033790bff7740a2c48bfc8bc5008658bc` |
| MABIMS SG 1445–1500 AH | 672 | `38d2042f37ae6b380ad68083843b716d2bf8fed27ec45f34ed0130bab07b6197` |
| MABIMS BN 1445–1500 AH | 672 | `6f1ddde50b696caa517575bde93008a9d6ae98e517221bd40dd886ba0885c20f` |

The digests are embedded in the shipped classes, so the tests compare the
constants against these rows instead of hardcoding them twice. They change if
and only if a table changes; that diff is the review signal for a pin bump.

## Known caveat

The tables run to 1500-12 AH (Gregorian ≈ 2077), but the pin's ΔT polynomial is
only calibrated for 2005–2050 and the pin warns on stderr for every month beyond
that window. The far-future rows therefore inherit the pin's extrapolated ΔT,
and a future pin revision could move a month boundary near the end of the span.
That is acceptable because the span is a published design decision and parity
with the pin is the requirement; it is recorded here so a boundary shift at the
tail is recognised as a pin change rather than a library bug.

## Regeneration gate

Regeneration is a deliberate, reviewable act, not part of CI:

```bash
python3 tools/gen_hijri.py --path <pinned-checkout>   # must be a no-op
php vendor/bin/phpunit --no-coverage tests/Goldens/HijriGoldensTest.php
```

Two consecutive runs must be byte-identical. CI only recomputes the digest over
the committed arrays (`testTableDigestsMatch`), which catches hand edits without
paying for a full rule evaluation.

## License

Facts are not copyrightable and no third-party table is redistributed; the data
is the deterministic output of an algorithm this repository pins. The generated
files carry the pin SHA in their docblocks.
