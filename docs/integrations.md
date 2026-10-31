# integrations — wiring this library into any PHP app

There are no service providers, bundles, or config files. Every service is
constructed with plain `new` or a named factory, in any app — framework or
not.

Every PHP block below is self-contained and is executed **verbatim** by
`tests/Wiring/CalculatorWiringTest.php`. A renamed constructor, an
incompatible signature change, or an unqualified global class in a snippet
fails the build.

## Prayer times

```php
use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Services\PrayerTimesCalculator;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

$calculator = PrayerTimesCalculator::default();
$params = CalculationMethod::SINGAPORE->defaultParameters();
$schedule = $calculator->calculate(
    Coordinates::fromDegrees(3.1390, 101.6869),
    CivilDate::fromYmd(2025, 3, 1),
    $params,
);
```

## Prayer times — explicit strategy wiring

Inject strategies explicitly instead of `default()`:

```php
use AlFalaq\AlFalaq\Astro\Services\MeanSolarCalculator;
use AlFalaq\AlFalaq\Prayer\Services\PrayerTimesCalculator;
use AlFalaq\AlFalaq\Prayer\Strategies\MiddleOfNightCorrection;
use AlFalaq\AlFalaq\Prayer\Strategies\NearestLatitudeHandler;
use AlFalaq\AlFalaq\Prayer\Strategies\SeasonAdjustedTwilight;

$calculator = new PrayerTimesCalculator(
    new MeanSolarCalculator(),
    new MiddleOfNightCorrection(),
    new NearestLatitudeHandler(),
    new SeasonAdjustedTwilight(),
);
```

`MeanSolarCalculator` is the stable default `SolarDayEngine` adapter: naming
it here for explicit wiring is supported (implement the port instead for test
doubles).

## Qibla

```php
use AlFalaq\AlFalaq\Qibla\Services\QiblaCalculator;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

$spherical = QiblaCalculator::default(); // spherical earth
$precise = QiblaCalculator::wgs84();     // WGS84/Karney

$result = $spherical->calculate(Coordinates::fromDegrees(3.1390, 101.6869));
$preciseResult = $precise->calculate(Coordinates::fromDegrees(3.1390, 101.6869));
```

## Timetable

```php
use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Services\TimetableService;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

$month = TimetableService::default()->monthly(
    Coordinates::fromDegrees(3.1390, 101.6869),
    2025,
    3,
    CalculationMethod::SINGAPORE->defaultParameters(),
);
```

`monthly()` returns one UTC `PrayerSchedule` per calendar day, in order.
There is no yearly helper by design: loop `monthly()`.

## Sunnah

```php
use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Services\PrayerTimesCalculator;
use AlFalaq\AlFalaq\Prayer\Services\SunnahCalculator;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

$sunnah = SunnahCalculator::fromCalculator(
    PrayerTimesCalculator::default(),
    Coordinates::fromDegrees(3.1390, 101.6869),
    CivilDate::fromYmd(2025, 3, 1),
    CalculationMethod::SINGAPORE->defaultParameters(),
)->calculate();
```

The night runs tonight's maghrib → tomorrow's fajr. `forZone()` converts the
markers for display; the values are UTC.

## Crescent visibility

```php
use AlFalaq\AlFalaq\Moon\Services\CrescentCalculator;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

$crescent = CrescentCalculator::default();
$coordinates = Coordinates::fromDegrees(3.1390, 101.6869);
$date = CivilDate::fromYmd(2025, 2, 28);

$geometry = $crescent->atSunset($coordinates, $date);
$verdicts = $crescent->evaluate($coordinates, $date);
```

`$verdicts` carries one `CrescentVerdict` per criterion under the keys
`yallop`, `odeh`, `mabims1992`, and `neomabims2021`. Pass
`$crescent->evaluateGeometry($geometry)` to reuse geometry you already hold.

## Hijri conversion

```php
use AlFalaq\AlFalaq\Hijri\Services\GregorianToHijriConverter;
use AlFalaq\AlFalaq\Hijri\Strategies\UmmAlQuraCalendar;
use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

$converter = GregorianToHijriConverter::default();

// The caller's own zone decides the civil date (a local wall-clock date):
$hijri = $converter->convert(
    new \DateTimeImmutable('2025-03-01 12:00:00', new \DateTimeZone('Asia/Kuala_Lumpur')),
    new UmmAlQuraCalendar(),
);

// Or roll the UTC date at the observer's maghrib:
$hijriAtSunset = $converter->convert(
    new \DateTimeImmutable('2025-03-01 12:00:00', new \DateTimeZone('UTC')),
    new UmmAlQuraCalendar(),
    Coordinates::fromDegrees(3.1390, 101.6869),
    CalculationMethod::SINGAPORE->defaultParameters(),
    changeAtSunset: true,
);
```

Other calendars are `TabularCalendar()` and
`MabimsCalendar(MabimsCountry::MY)`. An optional `OffsetStore`
implementation applies official day corrections after conversion.

## Clock input (edges only)

The domain never reads the clock. Callers pass time explicitly:

```php
use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Services\NextPrayerResolver;
use AlFalaq\AlFalaq\Prayer\Services\PrayerTimesCalculator;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

$calculator = PrayerTimesCalculator::default();
$params = CalculationMethod::SINGAPORE->defaultParameters();
$coordinates = Coordinates::fromDegrees(3.1390, 101.6869);
$today = $calculator->calculate($coordinates, CivilDate::fromYmd(2025, 3, 1), $params);
$tomorrow = $calculator->calculate($coordinates, CivilDate::fromYmd(2025, 3, 2), $params);
$nowUtc = new \DateTimeImmutable('2025-03-01 12:00:00', new \DateTimeZone('UTC'));

$next = NextPrayerResolver::default()->resolve($today, $tomorrow, $nowUtc);
```

If the host app uses `psr/clock` (suggested, never required), call
`$clock->now()` at the edge and pass the result in — the library never
type-hints it.

## Output shapes (JSON)

`PrayerSchedule::all()` keys `['imsak','fajr','sunrise','syuruk','ishraq','dhuha','dhuhr','asr','maghrib','isha']`
in this order, UTC `DateTimeImmutable`; `toArray(): array<string,string>` maps
same keys to `Y-m-d\TH:i:s+00:00`; `DisplaySchedule::toArray()` same keys in
display zone; `json_encode($schedule->toArray())` for consumers; no other keys
without a minor bump.

## See also

- [Integration](user/integration.md) — the published user guide
