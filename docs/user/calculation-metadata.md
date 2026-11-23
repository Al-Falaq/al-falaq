---
title: Calculation Metadata
description: Opt-in provenance for prayer, Qibla, and crescent results — what ran, which inputs were used, and which warnings fired.
---

# Calculation Metadata

`calculate()` and `monthly()` answer *when* to pray. The metadata siblings
answer *how a result was produced*: the inputs you requested, the inputs that
were actually used, the algorithm identities behind it, and any warnings.

Three contexts ship metadata: **prayer** (schedules and monthly timetables),
**Qibla** (direction and distance), and **Moon** (sunset geometry and crescent
verdicts). Hijri metadata remains a later phase. The compact methods stay the
default. Use metadata when a result is stored, audited, shown to an authority,
or debugged; skip it on high-volume display paths that only need the numbers.

## One prayer calculation

```php
use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Services\PrayerTimesCalculator;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

$calculator = PrayerTimesCalculator::default();
$parameters = CalculationMethod::SINGAPORE->defaultParameters();

$outcome = $calculator->calculateWithMetadata(
    Coordinates::fromDegrees(3.1390, 101.6869),
    CivilDate::fromYmd(2026, 11, 15),
    $parameters,
);

$schedule = $outcome->result;              // same PrayerSchedule as calculate()
$metadata = $outcome->metadata;            // provenance
$json = json_encode($outcome->toArray(), JSON_THROW_ON_ERROR);
```

The result is identical to `calculate()` with the same arguments — the two
methods share one numeric pipeline. Only the envelope is new.

Here is the shape, with illustrative values for Singapore on 2026-11-15. Note
that `result` carries UTC instants; 2026-11-14 21:28 UTC is 2026-11-15 05:28
in Singapore.

```json
{
  "result": {
    "imsak": "2026-11-14T21:28:00+00:00",
    "fajr": "2026-11-14T21:38:00+00:00",
    "sunrise": "2026-11-14T22:58:00+00:00",
    "syuruk": "2026-11-14T22:58:00+00:00",
    "ishraq": "2026-11-14T23:13:00+00:00",
    "dhuha": "2026-11-14T23:26:00+00:00",
    "dhuhr": "2026-11-15T04:59:00+00:00",
    "asr": "2026-11-15T08:21:00+00:00",
    "maghrib": "2026-11-15T10:57:00+00:00",
    "isha": "2026-11-15T12:10:00+00:00"
  },
  "metadata": {
    "schema_version": "1.0",
    "context": "prayer",
    "status": "complete",
    "library_version": "0.3.0-dev",
    "warnings": [],
    "sources": [
      {"id": "prayer.high-latitude.middle_of_night", "kind": "algorithm", "version": null, "digest": null, "range": null},
      {"id": "prayer.isha.angle", "kind": "algorithm", "version": null, "digest": null, "range": null},
      {"id": "prayer.maghrib.interval", "kind": "algorithm", "version": null, "digest": null, "range": null},
      {"id": "prayer.method.singapore", "kind": "algorithm", "version": "method-defaults-v1", "digest": null, "range": null},
      {"id": "solar.mean-meeus", "kind": "algorithm", "version": "meeus-2ed", "digest": null, "range": null}
    ],
    "input": {
      "coordinates": {"latitude": 3.139, "longitude": 101.6869},
      "date": "2026-11-15"
    },
    "effective_input": {
      "coordinates": {"latitude": 3.139, "longitude": 101.6869},
      "date": "2026-11-15"
    },
    "policy": {
      "method": "singapore",
      "madhab": "shafi",
      "high_latitude_rule": "middle_of_night",
      "polar_circle_rule": "nearest_latitude",
      "fajr_angle_degrees": 20.0,
      "isha_rule": {"type": "angle", "angle_degrees": 18.0, "minutes": null},
      "maghrib_rule": {"type": "interval", "angle_degrees": null, "minutes": 0},
      "dhuhr_offset_minutes": 0,
      "imsak_offset_minutes": 10,
      "ishraq_offset_minutes": 15,
      "dhuha_offset_minutes": 28,
      "elevation_m": 0.0,
      "is_ramadan": false,
      "method_adjustments": {
        "imsak": 0, "fajr": 0, "sunrise": 0, "syuruk": 0, "ishraq": 0,
        "dhuha": 0, "dhuhr": 1, "asr": 0, "maghrib": 0, "isha": 0
      },
      "adjustments": {
        "imsak": 0, "fajr": 0, "sunrise": 0, "syuruk": 0, "ishraq": 0,
        "dhuha": 0, "dhuhr": 0, "asr": 0, "maghrib": 0, "isha": 0
      }
    },
    "fallback": {"applied": false, "kind": null, "rule": null},
    "rounding": "half_up_to_minute_utc"
  }
}
```

What each metadata block is for:

| Key | Use it to |
|---|---|
| `schema_version` | decide whether you understand the payload before parsing further |
| `context` | dispatch `prayer`, `qibla`, `moon`, `hijri` envelopes |
| `status` | gate display: `complete` is routine; anything higher needs attention |
| `library_version` | reproduce with the release that produced the result |
| `warnings` | branch application behavior on `code` and `details` |
| `sources` | record the algorithm/data dependency set that ran |
| `input` | show what the user asked for |
| `effective_input` | show what was actually computed with |
| `policy` | store the full method + overrides, not just the enum |
| `fallback` | detect that a requested place, date, or both were replaced |
| `rounding` | document that reported instants are minute-rounded UTC |

### Monthly timetables

```php
use AlFalaq\AlFalaq\Prayer\Services\TimetableService;

$outcomes = TimetableService::default()->monthlyWithMetadata(
    Coordinates::fromDegrees(3.1390, 101.6869),
    2025,
    3,
    $parameters,
);

foreach ($outcomes as $outcome) {
    $day = $outcome->metadata->requestedDate->isoformat();
    $status = $outcome->metadata->common->status->value;
    // store or display per day
}
```

Each entry carries the provenance of its own day, so a polar fallback that
applied on only some days stays visible. `monthly()` is unchanged and keeps
returning schedules.

## Qibla

```php
use AlFalaq\AlFalaq\Qibla\Services\QiblaCalculator;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

$outcome = QiblaCalculator::wgs84()->calculateWithMetadata(
    Coordinates::fromDegrees(3.1390, 101.6869),
);

$result = $outcome->result;      // same QiblaResult as calculate()
$metadata = $outcome->metadata;  // provenance
```

```json
{
  "result": {
    "direction_degrees": 292.44237633878004,
    "distance_km": 6979.153497454788
  },
  "metadata": {
    "schema_version": "1.0",
    "context": "qibla",
    "status": "complete",
    "library_version": "0.3.0-dev",
    "warnings": [],
    "sources": [
      {"id": "qibla.wgs84-karney", "kind": "algorithm", "version": "geographiclib-2.1", "digest": null, "range": null}
    ],
    "input": {"coordinates": {"latitude": 3.139, "longitude": 101.6869}},
    "heading_reference": "true_north",
    "magnetic_declination_degrees": null
  }
}
```

Exactly one geodesy source is listed: `qibla.spherical` for `default()` or
`qibla.wgs84-karney` for `wgs84()`. `heading_reference` is always
`true_north`, and `magnetic_declination_degrees` is always `null` until a
sourced magnetic model ships — see [True north is not magnetic
north](#true-north-is-not-magnetic-north).

## Crescent geometry

```php
use AlFalaq\AlFalaq\Moon\Services\CrescentCalculator;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

$moon = CrescentCalculator::default();
$coordinates = Coordinates::fromDegrees(3.1390, 101.6869);
$date = CivilDate::fromYmd(2025, 2, 28);

$outcome = $moon->atSunsetWithMetadata($coordinates, $date);
$geometry = $outcome->result;      // same CrescentGeometry as atSunset()
$metadata = $outcome->metadata;
```

```json
{
  "result": {
    "sun_altitude_degrees": -0.8325033650264293,
    "moon_altitude_degrees": 4.254467140388303,
    "elongation_degrees": 6.173972051753963,
    "moon_age_hours": 10,
    "lag_minutes": 24.72566096343835,
    "delta_azimuth_degrees": 1.0278791375318974,
    "illuminated_fraction": 0.002900038687049744,
    "arcv_geocentric_degrees": 6.08801822286506,
    "width_arcminutes": 0.09531389154651397,
    "age_at_moonset_hours": 10.412094349390639
  },
  "metadata": {
    "schema_version": "1.0",
    "context": "moon",
    "status": "complete",
    "library_version": "0.3.0-dev",
    "warnings": [],
    "sources": [
      {"id": "moon.conjunction-search.meeus-hourly", "kind": "algorithm", "version": "meeus-hourly", "digest": null, "range": null},
      {"id": "moon.delta-t.espenak-2005-2050", "kind": "algorithm", "version": null, "digest": null, "range": "2005..2050"},
      {"id": "moon.ephemeris.meeus-2ed", "kind": "algorithm", "version": "meeus-2ed", "digest": null, "range": null},
      {"id": "solar.mean-meeus", "kind": "algorithm", "version": "meeus-2ed", "digest": null, "range": null}
    ],
    "input": {
      "coordinates": {"latitude": 3.139, "longitude": 101.6869},
      "date": "2025-02-28"
    },
    "delta_t": {
      "seconds": 74.56305038786272,
      "origin": "provider",
      "calibrated_from_year": 2005,
      "calibrated_to_year": 2050
    },
    "geometry_source": "calculated",
    "criteria": []
  }
}
```

The geometry outcome lists the solar engine, ephemeris, conjunction search,
and ΔT source. `criteria` stays empty because no criterion ran.

## Crescent evaluation

```php
$outcome = $moon->evaluateWithMetadata($coordinates, $date);

$geometry = $outcome->result;
$verdicts = $outcome->verdicts;    // same four CrescentVerdict objects as evaluate()
$metadata = $outcome->metadata;

// Re-score geometry you already hold; provenance covers the criteria only.
$reused = $moon->evaluateGeometryWithMetadata($geometry);
```

```json
{
  "result": {
    "geometry": {
      "sun_altitude_degrees": -0.8325033650264293,
      "moon_altitude_degrees": 4.254467140388303,
      "elongation_degrees": 6.173972051753963,
      "moon_age_hours": 10,
      "lag_minutes": 24.72566096343835,
      "delta_azimuth_degrees": 1.0278791375318974,
      "illuminated_fraction": 0.002900038687049744,
      "arcv_geocentric_degrees": 6.08801822286506,
      "width_arcminutes": 0.09531389154651397,
      "age_at_moonset_hours": 10.412094349390639
    },
    "verdicts": {
      "yallop": {"criterion": "yallop", "visible": false, "score": -0.5153011137332267, "zone": "F"},
      "odeh": {"criterion": "odeh", "visible": false, "score": -1.4820588547825952, "zone": "D"},
      "mabims1992": {"criterion": "mabims1992", "visible": true, "score": 6.173972051753963, "zone": "visible"},
      "neomabims2021": {"criterion": "neomabims2021", "visible": false, "score": 6.173972051753963, "zone": "not-visible"}
    }
  },
  "metadata": {
    "schema_version": "1.0",
    "context": "moon",
    "status": "decision_required",
    "library_version": "0.3.0-dev",
    "warnings": [
      {"code": "crescent_criterion_is_not_observation", "message": "Crescent criteria are computed verdicts, not observations.", "details": {"criteria": "yallop,odeh,mabims1992,neomabims2021"}}
    ],
    "sources": [
      {"id": "moon.conjunction-search.meeus-hourly", "kind": "algorithm", "version": "meeus-hourly", "digest": null, "range": null},
      {"id": "moon.criterion.mabims-1992", "kind": "algorithm", "version": null, "digest": null, "range": null},
      {"id": "moon.criterion.neo-mabims-2021", "kind": "algorithm", "version": null, "digest": null, "range": null},
      {"id": "moon.criterion.odeh", "kind": "algorithm", "version": null, "digest": null, "range": null},
      {"id": "moon.criterion.yallop", "kind": "algorithm", "version": null, "digest": null, "range": null},
      {"id": "moon.delta-t.espenak-2005-2050", "kind": "algorithm", "version": null, "digest": null, "range": "2005..2050"},
      {"id": "moon.ephemeris.meeus-2ed", "kind": "algorithm", "version": "meeus-2ed", "digest": null, "range": null},
      {"id": "solar.mean-meeus", "kind": "algorithm", "version": "meeus-2ed", "digest": null, "range": null}
    ],
    "input": {
      "coordinates": {"latitude": 3.139, "longitude": 101.6869},
      "date": "2025-02-28"
    },
    "delta_t": {
      "seconds": 74.56305038786272,
      "origin": "provider",
      "calibrated_from_year": 2005,
      "calibrated_to_year": 2050
    },
    "geometry_source": "calculated",
    "criteria": ["yallop", "odeh", "mabims1992", "neomabims2021"]
  }
}
```

An evaluation adds the four criterion sources and always emits
`crescent_criterion_is_not_observation`, so its status is at least
`decision_required`. The verdict order is fixed and independent of the order
the criteria ran in.

The caller-supplied variant reports no invented provenance: the geometry
bundle carries no coordinates, date, or ΔT, so `input` fields are null,
`delta_t` is null, `geometry_source` is `caller_supplied`, and only the four
criterion sources are listed.

```json
{
  "input": {"coordinates": null, "date": null},
  "delta_t": null,
  "geometry_source": "caller_supplied",
  "sources": [
    {"id": "moon.criterion.mabims-1992", "kind": "algorithm", "version": null, "digest": null, "range": null},
    {"id": "moon.criterion.neo-mabims-2021", "kind": "algorithm", "version": null, "digest": null, "range": null},
    {"id": "moon.criterion.odeh", "kind": "algorithm", "version": null, "digest": null, "range": null},
    {"id": "moon.criterion.yallop", "kind": "algorithm", "version": null, "digest": null, "range": null}
  ]
}
```

## ΔT and no-moonset

The Moon pipeline needs TT−UT1 (ΔT). By default it uses the calibrated
Espenak 2005–2050 polynomial and reports `delta_t.origin = "provider"` with
the calibrated bounds. A non-null override reports `origin = "override"`,
lists `caller.delta-t`, and leaves the calibrated years null.

The provider's decimal year is the same value used for the range check:
`2050-01-01` is exactly `2050.0` and stays complete, while `2050-01-02` is
past the calibrated endpoint and derives `extrapolated` with a
`delta_t_extrapolated` warning. An override suppresses that warning because
no provider was consulted.

When the moon does not set on the requested civil date, the geometry is still
finite but `lag_minutes` and `age_at_moonset_hours` are null. Metadata derives
`indeterminate` and adds `moonset_indeterminate` with
`details = {"date": "2025-01-15", "reason": "no_moonset_on_civil_date"}`.
Caller-supplied geometry has no date, so that detail serializes as `null` —
the warning still fires because the null window is in the bundle.

## Storing a result for reproduction

Store the request and the outcome together:

1. `input` (requested coordinates and date), not `effective_input`;
2. `policy` (the full snapshot, including both adjustment layers and the
   elevation);
3. the whole `result`;
4. `library_version` alongside the payload — schema `1.0` stays compatible, but
   the astronomic engine and preset defaults can still change across releases;
5. optionally `status`, `warnings`, and `sources` as the audit record.

To replay, reconstruct `CalculationParameters` from `policy` through
`create()` and its withers. The rule blocks (`isha_rule`, `maghrib_rule`) carry
both keys and set the unused one to null; pick the non-null one. Keep
`PrayerAdjustments::toArray()` in mind: it lists all ten prayers with zeros for
unset entries. Applying every entry with `with()` makes an originally empty
caller layer non-empty, which adds `caller.adjustments` to `sources`. Leave the
layer empty when the stored `sources` does not list `caller.adjustments`; when
it does, apply the recorded values. Then call `calculateWithMetadata()` again
with the stored `input` and compare. Feeding back `effective_input` would hide
the fallback instead of reproducing it.

The payload also does not record injected components. Replay with the same
calculator factory or inject the same engines and strategies with the same
configuration; a different custom implementation can change the times or the
reported source identities even when `input`, `policy`, and `library_version`
match. `fallback.rule` names the fallback that actually ran, while
`policy.polar_circle_rule` is the configuration you passed; `default()` always
wires nearest-latitude, so use `defaultFor($parameters)` when the two should
agree.

## Handling warnings at your boundary

The legacy methods expose no warnings and keep their exception behavior. A
caller that wants to react opts in and branches on `code`:

```php
$outcome = $calculator->calculateWithMetadata($coordinates, $date, $parameters);
$status = $outcome->metadata->common->status->value;

if ($status === 'fallback_applied') {
    // A requested input was replaced: show the effective date and place.
    foreach ($outcome->metadata->common->warnings as $warning) {
        if ($warning->code->value === 'polar_fallback_applied') {
            $effectiveCoordinates = $outcome->metadata->effectiveCoordinates;
            $effectiveDate = $outcome->metadata->effectiveDate;
        }
    }
}
```

Rules of thumb:

- branch on `code` and `details`, never on `message` (prose is not stable);
- a warning does not invalidate the result — `fallback_applied`,
  `extrapolated`, and `indeterminate` still carry usable values;
- Moon warnings to expect: `delta_t_extrapolated` (provider outside
  2005–2050), `moonset_indeterminate` (null lag/age), and
  `crescent_criterion_is_not_observation` (every evaluation);
- `decision_required` (Moon evaluation) means the calculation cannot make the
  decision for you;
- an `unidentified_calculation_source` warning means a custom wired component
  has no declared identity; its source is reported as `custom.<component>`;
- promote codes to exceptions at your own boundary if your product requires
  it; the library does not install a global warning mode.

Status order, lowest to highest:
`complete`, `extrapolated`, `fallback_applied`, `indeterminate`,
`decision_required`. The highest warning rank wins; the full warning list is
kept.

## True north is not magnetic north

The Qibla direction is reported relative to **true north**. A magnetic compass
reads magnetic north, which differs by the local magnetic declination. The
library does not ship a magnetic model. If your application shows a compass
bearing, apply a declination from a source appropriate to the user's location
and date, and state that you did so; do not silently relabel the true-north
bearing as a compass bearing. The Qibla metadata path reports
`heading_reference: true_north` and always serializes
`magnetic_declination_degrees: null`, making the absence of a model explicit.
A sourced magnetic model remains a later phase (P2.3).

## Moon criteria are not observations

The crescent evaluators implement published criteria (Yallop, Odeh, MABIMS
1992, Neo-MABIMS 2021). A verdict is the output of a criterion, not an
observation and not a religious authority's announcement. Moon evaluation
metadata marks results with `crescent_criterion_is_not_observation` and
`decision_required` so a consuming application cannot present a criterion as a
sighting. Local authority announcements remain the deciding input for
practice.

## Generated and official Hijri tables

The Umm al-Qura and MABIMS calendars ship generated month-start tables; the
tabular calendar is arithmetic. A3 marks generated tables with
`generated_calendar_table` (plus a data digest) and the arithmetic calendar
with `tabular_calendar_estimate`. An official table is only labelled as such
when it ships its own digest, coverage range, source, and license. Until then,
treat a converted Hijri date as a computed estimate, not an official decree.

## Schema compatibility

- `schema_version` is `1.0` and is independent of the package version.
- Adding a context or a field is a minor schema change: ignore unknown keys
  instead of rejecting the payload.
- Removing or renaming a key, changing a unit, or changing a key's meaning
  requires a major schema bump.
- `library_version` records the release, not the schema. Reproduce with the
  same release when exact times matter.
- Warning codes are stable within schema major version 1; human messages are
  not.

## What metadata does not include

No wall-clock timestamp, host name, random id, or timezone conversion. The
library never reads a clock. Metadata depends only on the inputs, the policy,
the library version, and the calculator wiring, so storing it twice with the
same inputs, policy, release, and injected components produces the same bytes.
