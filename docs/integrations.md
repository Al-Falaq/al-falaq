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
use AlFalaq\AlFalaq\Prayer\Services\PrayerTimesCalculator;
use AlFalaq\AlFalaq\Prayer\Strategies\MiddleOfNightCorrection;
use AlFalaq\AlFalaq\Prayer\Strategies\NearestLatitudeHandler;
use AlFalaq\AlFalaq\Prayer\Strategies\SeasonAdjustedTwilight;

$calculator = new PrayerTimesCalculator(
    new MiddleOfNightCorrection(),
    new NearestLatitudeHandler(),
    new SeasonAdjustedTwilight(),
);
```

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
