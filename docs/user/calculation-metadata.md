---
title: Calculation Metadata
description: Opt-in provenance for prayer results — what ran, which inputs were used, and which warnings fired.
---

# Calculation Metadata

`calculate()` and `monthly()` answer *when* to pray. The metadata siblings
answer *how those times were produced*: the inputs you requested, the inputs
that were actually used, the full policy that ran, the algorithm identities
behind it, and any warnings.

The compact path stays the default. Use metadata when a result is stored,
audited, shown to an authority, or debugged; skip it on high-volume display
paths that only need the times.

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
| `fallback` | detect that a requested place was replaced |
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
both keys and set the unused one to null; pick the non-null one. Then call
`calculateWithMetadata()` again with the stored `input` and compare. Feeding
back `effective_input` would hide the fallback instead of reproducing it.

## Handling warnings at your boundary

The legacy methods expose no warnings and keep their exception behavior. A
caller that wants to react opts in and branches on `code`:

```php
$outcome = $calculator->calculateWithMetadata($coordinates, $date, $parameters);
$status = $outcome->metadata->common->status->value;

if ($status === 'fallback_applied') {
    // Your place was replaced: tell the user which inputs were used.
    foreach ($outcome->metadata->common->warnings as $warning) {
        if ($warning->code->value === 'polar_fallback_applied') {
            $effective = $outcome->metadata->effectiveCoordinates;
        }
    }
}
```

Rules of thumb:

- branch on `code` and `details`, never on `message` (prose is not stable);
- a warning does not invalidate the result — `fallback_applied` and
  `extrapolated` still carry valid times;
- `decision_required` (A2 Moon evaluation) means the calculation cannot make
  the decision for you;
- promote codes to exceptions at your own boundary if your product requires
  it; the library does not install a global warning mode.

Status order, lowest to highest:
`complete`, `extrapolated`, `fallback_applied`, `indeterminate`,
`decision_required`. The highest warning rank wins; the full warning list is
kept.

## True north is not magnetic north

The Qibla direction is reported relative to **true north**. A magnetic compass
reads magnetic north, which differs by the local magnetic declination. A1 does
not ship a magnetic model. If your application shows a compass bearing, apply
a declination from a source appropriate to the user's location and date, and
state that you did so; do not silently relabel the true-north bearing as a
compass bearing. A2 adds an explicit, caller-supplied declination path with its
own metadata field.

## Moon criteria are not observations

The crescent evaluators implement published criteria (Yallop, Odeh, MABIMS
1992, Neo-MABIMS 2021). A verdict is the output of a criterion, not an
observation and not a religious authority's announcement. A2's metadata marks
evaluation results with `crescent_criterion_is_not_observation` and
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
and the library version, so storing it twice produces the same bytes.
