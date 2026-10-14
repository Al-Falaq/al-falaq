---
title: API Reference
description: Every public class, method, and contract in al-falaq, grouped by context.
slug: v0.1.0/api-reference
---

# API Reference

This page maps the entire public surface — what to call, what to implement, and what to leave alone. Public contexts are `SharedKernel`, `Prayer`, `Qibla`, `Moon`, and `Hijri`. `Astro/` is `@internal`: the engine behind the services, never imported from userland (the one stable exception is the `SolarDayEngine` port with its `MeanSolarCalculator` adapter for explicit prayer wiring).

Conventions: services are `final` with explicit constructor injection and `default()` / `defaultFor()` factories; value objects are `final readonly` and validate at construction; service contracts carry the `Interface` suffix (a PHP same-short-name rule — do not "simplify" it away); everything time-bearing is UTC `DateTimeImmutable`.

## SharedKernel — the foundation

| Class | Key members |
| --- | --- |
| `SharedKernel\ValueObjects\Coordinates` | `fromDegrees(mixed $lat, mixed $lon): self` (lat −90…90, lon −180…180); `fromArray(array): self` (exactly `[0 => lat, 1 => lon]`); `equals()`, `toArray()` |
| `SharedKernel\ValueObjects\CivilDate` | `fromYmd(int, int, int): self` (1900–2100); `fromDateTime(DateTimeImmutable): self` (own-zone date); `plusDays(int): self`; `dayOfYear(): int`; `toUtcMidnight()`; `isoformat()` |
| `SharedKernel\ValueObjects\Angle` | `fromDegrees(mixed): self`; `normalized(): self` (`[0, 360)` with ulp re-clamp); `toRadians(): float` |
| `SharedKernel\ValueObjects\Minutes` | `add(DateTimeImmutable, int): DateTimeImmutable`; `roundedHalfUp(DateTimeImmutable $utc): DateTimeImmutable` (UTC-only, half-up) |
| `SharedKernel\Exceptions\*` | `AlFalaqException` (abstract base), `ValidationException`, `InvalidCoordinatesException::outOfRange()/malformedArray()`, `ConfigurationException`, `AstronomicalException` |

## Prayer — times, timetables, nights

| Class | Key members |
| --- | --- |
| `Prayer\Enums\CalculationMethod` (19 cases) | `MUSLIM_WORLD_LEAGUE, EGYPTIAN, KARACHI, UMM_AL_QURA, DUBAI, MOON_SIGHTING_COMMITTEE, NORTH_AMERICA, KUWAIT, QATAR, SINGAPORE, TURKEY, TEHRAN, MOROCCO, JAKIM, KEMENAG, MUIS, BRUNEI, UOIF, NONE`; `defaultParameters(): CalculationParameters` (throws on `NONE`) |
| `Prayer\Enums\Prayer` (10 cases) | `IMSAK, FAJR, SUNRISE, SYURUK, ISHRAQ, DHUHA, DHUHR, ASR, MAGHRIB, ISHA` (schedule order) |
| `Prayer\Enums\Madhab` | `SHAFI->shadowLength() = 1.0`, `HANAFI = 2.0` |
| `Prayer\Enums\HighLatitudeRule` | `NONE, MIDDLE_OF_NIGHT, SEVENTH_OF_NIGHT, TWILIGHT_ANGLE, SEASONAL_TWILIGHT` |
| `Prayer\Enums\PolarCircleRule` | `NONE, NEAREST_LATITUDE, NEAREST_DAY, MAKKAH` |
| `Prayer\ValueObjects\CalculationParameters` | `create(...)` full factory; `withMadhab/withElevationM/withAdjustments/withMethodAdjustments/withRamadan/withFajrAngle/withIshaRule/withMaghribRule/withHighLatitudeRule/withPolarCircleRule/withImsakOffset/withIshraqOffset/withDhuhaOffset/withDhuhrOffset()` |
| `Prayer\ValueObjects\PrayerAdjustments` | `empty(): self`; `with(Prayer, int [-60,60]): self`; `for(Prayer): int`; `combinedWith(self): self` |
| `Prayer\Strategies\AngleIsha(float $angle)`, `IntervalIsha(int $minutes)` | `IshaRule` implementations |
| `Prayer\Strategies\AngleMaghrib(float $angle)`, `MinutesAfterSunsetMaghrib(int $minutes = 0)` | `MaghribRule` implementations |
| `Prayer\Services\PrayerTimesCalculator` | `default(): self`; `defaultFor(CalculationParameters): self`; `calculate(Coordinates\|array, CivilDate, CalculationParameters): PrayerSchedule`; `maghribAt(...): DateTimeImmutable` |
| `Prayer\ValueObjects\PrayerSchedule` | `timeFor(Prayer)`, `all()` (ordered UTC), `toArray()` (UTC ISO-8601), `forZone(DateTimeZone): DisplaySchedule`, `nightLength()` |
| `Prayer\ValueObjects\DisplaySchedule` | `timeFor(Prayer)`, `all()`, `toArray()` (display-zone ISO-8601) |
| `Prayer\Services\TimetableService` | `monthly(Coordinates, int $year, int $month, CalculationParameters): list<PrayerSchedule>` |
| `Prayer\Services\NextPrayerResolver` | `resolve(PrayerSchedule $today, PrayerSchedule $tomorrow, DateTimeImmutable $nowUtc): NextPrayer` (`$nowUtc` must be UTC) |
| `Prayer\ValueObjects\NextPrayer` | `__construct(Prayer $prayer, DateTimeImmutable $at, int $inSeconds)` |
| `Prayer\Services\SunnahCalculator` | `fromCalculator(PrayerTimesCalculatorInterface, Coordinates, CivilDate, CalculationParameters): self`; `__construct(PrayerSchedule $today, PrayerSchedule $tomorrow)`; `calculate(): SunnahTimes` |
| `Prayer\ValueObjects\SunnahTimes` | `__construct(middleOfNight, firstThird, lastThird, fajr, maghrib)`; `tahajjudWindow()`; `nightFraction(float $f in (0,1))`; `forZone(DateTimeZone): self` |
| `Prayer\Contracts\*Interface` | `PrayerTimesCalculatorInterface::calculate/maghribAt()`, `TimetableServiceInterface::monthly()`, `NextPrayerResolverInterface::resolve()` (all `Coordinates`-narrowed) |
| `Prayer\Exceptions\UndefinedScheduleException` | `polar(Coordinates, CivilDate, ?string $rule)`; extends `AstronomicalException` |

## Qibla — bearings and distances

| Class | Key members |
| --- | --- |
| `Qibla\Services\QiblaCalculator` | `default(): self` (spherical); `wgs84(): self` (Karney); `calculate(Coordinates\|array): QiblaResult` |
| `Qibla\Contracts\GeodesyCalculator` | `MAKKAH_LATITUDE = 21.4225241`, `MAKKAH_LONGITUDE = 39.8261818`, `SELF_DISTANCE_KM = 0.001`; `inverse(Coordinates): array{direction, distanceKm}` |
| `Qibla\ValueObjects\QiblaResult` | `__construct(float $direction [0,360), float $distanceKm)`; `magneticHeading(mixed $declinationEast): float` |

## Moon — crescent geometry and criteria

| Class | Key members |
| --- | --- |
| `Moon\Services\CrescentCalculator` | `default(): self`; `atSunset(Coordinates, CivilDate, ?float $deltaTOverride): CrescentGeometry`; `evaluate(...): array{yallop, odeh, mabims1992, neomabims2021}`; `evaluateGeometry(CrescentGeometry): same` |
| `Moon\ValueObjects\CrescentGeometry` | `sunAltitude, moonAltitude, elongationDeg, moonAgeHours, ?lagMinutes, deltaAzimuthDeg, illuminatedFraction, arcvGeoDeg, widthArcmin, ?ageAtMoonsetHours` |
| `Moon\ValueObjects\CrescentVerdict` | `__construct(string $criterion, bool $visible, float $score, string $zone)` |
| `Moon\Contracts\CrescentEvaluator` | `evaluate(CrescentGeometry): CrescentVerdict` |
| `Moon\Strategies\*Evaluator` | `YallopEvaluator`, `OdehEvaluator`, `Mabims1992Evaluator`, `NeoMabims2021Evaluator` |
| `Moon\Contracts\ConjunctionSearch` | Port for moon-age search (tests substitute doubles) |

## Hijri — calendars and the bridge

| Class | Key members |
| --- | --- |
| `Hijri\Services\GregorianToHijriConverter` | `default(): self`; `convert(DateTimeImmutable, HijriCalendar, ?Coordinates, ?CalculationParameters, bool $changeAtSunset = false, ?OffsetStore $offsets = null): HijriDate` |
| `Hijri\Contracts\HijriCalendar` | `fromGregorian(CivilDate): HijriDate`; `toGregorian(HijriDate): CivilDate`; `monthLength(int, int): int`; `shift(HijriDate, int): HijriDate`; `name(): string` |
| `Hijri\Strategies\TabularCalendar` | `__construct(int $adjustmentDays = 0 [-2,2])`; `name() = 'tabular'` |
| `Hijri\Strategies\UmmAlQuraCalendar` | `name() = 'uqu'`; coverage 1423-01…1500-12 AH |
| `Hijri\Strategies\MabimsCalendar` | `__construct(MabimsCountry)`; `name() = 'mabims'`; coverage 1445-01…1500-12 AH |
| `Hijri\Contracts\OffsetStore` | `offsetDaysFor(HijriDate, HijriCalendar): int` (caller-implemented; 0 = no-op) |
| `Hijri\ValueObjects\HijriDate` | `__construct(int $year >= 1, int $month 1–12, int $day 1–30)`; `isoformat()`; `compareTo()`; `equals()` |
| `Hijri\Enums\HijriMonth` | int enum 1–12; `label(string $locale): string` (`en`/`ar`) |
| `Hijri\Enums\MabimsCountry` | `MY, ID, SG, BN`; `referenceCoordinates()`; `fromString(string)` (case-insensitive) |
| `Hijri\Exceptions\HijriOutOfRangeException` | `forDate(string $calendar, string $iso, string $range)`; extends `ValidationException` |

## What not to touch

`Astro/` (`MeanSolarCalculator`, `CrescentEphemeris`, `GeodesyEngine`, `KarneyInverse`, `DeltaTProvider`, `SolarDayEngine` port) is `@internal` — covered by semantic versioning only insofar as the public services behave identically. The `SolarDayEngine` port plus `MeanSolarCalculator` adapter are the stable exception for explicit prayer wiring and test doubles.
