# integrations — wiring this library into any PHP app

There are no service providers, bundles, or config files. Every service is
constructed with plain `new` or a named factory, in any app — framework or
not. These snippets are smoke-tested by `tests/Wiring/`.

## Prayer times (M1)

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

Advanced use: inject strategies explicitly instead of `default()`.

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

## Qibla (M2)

```php
use AlFalaq\AlFalaq\Qibla\Services\QiblaCalculator;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

$spherical = QiblaCalculator::default(); // spherical earth
$precise = QiblaCalculator::wgs84();     // WGS84/Karney
$result = $spherical->calculate(Coordinates::fromDegrees(3.1390, 101.6869));
```

## Clock input (edges only)

The domain never reads the clock. Callers pass time explicitly:

```php
use AlFalaq\AlFalaq\Prayer\Services\NextPrayerResolver;

$next = (new NextPrayerResolver())->resolve($today, $tomorrow, new DateTimeImmutable('now', new DateTimeZone('UTC')));
```

If the host app uses `psr/clock` (suggested, never required), call
`$clock->now()` at the edge and pass the result in — the library never
type-hints it.

## Output shapes (JSON)

`PrayerSchedule::all()` keys `['imsak','fajr','sunrise','syuruk','ishraq','dhuha','dhuhr','asr','maghrib','isha']`
in this order, UTC `DateTimeImmutable`; `toArray(): array<string,string>` maps
same keys to `Y-m-d\TH:i:s+00:00`; `DisplaySchedule::toArray()` same keys in
display zone; `json_encode($sched->toArray())` for consumers; no other keys
without a minor bump.
