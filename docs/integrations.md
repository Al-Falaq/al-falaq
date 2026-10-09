# integrations — wiring this library into any PHP app

There are no service providers, bundles, or config files. Every service is
constructed with plain `new` or a named factory, in any app — framework or
not. These snippets are smoke-tested by `tests/Wiring/`.

## Prayer times (M1)

```php
use AlFalaq\AlFalak\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalak\Prayer\Services\PrayerTimesCalculator;
use AlFalaq\AlFalak\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalak\SharedKernel\ValueObjects\Coordinates;

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
use AlFalaq\AlFalak\Prayer\Services\PrayerTimesCalculator;
use AlFalaq\AlFalak\Prayer\Strategies\MiddleOfNightCorrection;
use AlFalaq\AlFalak\Prayer\Strategies\NearestLatitudeHandler;
use AlFalaq\AlFalak\Prayer\Strategies\SeasonAdjustedTwilight;

$calculator = new PrayerTimesCalculator(
    new MiddleOfNightCorrection(),
    new NearestLatitudeHandler(),
    new SeasonAdjustedTwilight(),
);
```

## Qibla (M2)

```php
use AlFalaq\AlFalak\Qibla\Services\QiblaCalculator;
use AlFalaq\AlFalak\SharedKernel\ValueObjects\Coordinates;

$spherical = QiblaCalculator::default(); // spherical earth
$precise = QiblaCalculator::wgs84();     // WGS84/Karney
$result = $spherical->calculate(Coordinates::fromDegrees(3.1390, 101.6869));
```

## Clock input (edges only)

The domain never reads the clock. Callers pass time explicitly:

```php
use AlFalaq\AlFalak\Prayer\Services\NextPrayerResolver;

$next = (new NextPrayerResolver())->resolve($today, $tomorrow, new DateTimeImmutable('now', new DateTimeZone('UTC')));
```

If the host app uses `psr/clock` (suggested, never required), call
`$clock->now()` at the edge and pass the result in — the library never
type-hints it.
