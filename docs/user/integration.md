---
title: Framework Integration
description: Wire al-falaq into plain PHP, Laravel, Symfony, and WordPress with plain-new construction.
---

# Framework Integration

al-falaq has no service providers, no bundles, no config files, and no facades. Every service is constructed with plain `new` or a named factory, which means it wires the same way in every framework — the only thing that changes is where you put the construction code. This page shows the idiomatic spot in each ecosystem.

## The universal pattern

Default wiring covers nearly everyone. Reach for explicit construction only when you need a different strategy (say, WGS84 Qibla or a custom high-latitude rule):

```php
use AlFalaq\AlFalaq\Astro\Services\MeanSolarCalculator;
use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Services\PrayerTimesCalculator;
use AlFalaq\AlFalaq\Prayer\Strategies\MiddleOfNightCorrection;
use AlFalaq\AlFalaq\Prayer\Strategies\NearestLatitudeHandler;
use AlFalaq\AlFalaq\Prayer\Strategies\SeasonAdjustedTwilight;
use AlFalaq\AlFalaq\Qibla\Services\QiblaCalculator;

// Defaults — what most apps want:
$prayers = PrayerTimesCalculator::default();
$qibla = QiblaCalculator::default(); // spherical
$precise = QiblaCalculator::wgs84(); // ellipsoidal opt-in

// Rule-mapped — strategies follow your parameters:
$prayers = PrayerTimesCalculator::defaultFor($params);

// Explicit — the day engine comes first:
$prayers = new PrayerTimesCalculator(
    new MeanSolarCalculator(),
    new MiddleOfNightCorrection(),
    new NearestLatitudeHandler(),
    new SeasonAdjustedTwilight(),
);
```

`MeanSolarCalculator` is the stable default adapter for the internal day-engine port — naming it in explicit wiring is supported, and implementing the port is how test doubles plug in. Never import `Astro/` from userland otherwise; go through the feature services.

## Plain PHP and shared hosting

This is the first-class consumer. One file, no container:

```php
use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Services\PrayerTimesCalculator;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

$calculator = PrayerTimesCalculator::default();
$params = CalculationMethod::JAKIM->defaultParameters();

$schedule = $calculator->calculate(
    Coordinates::fromDegrees(3.1390, 101.6869),
    CivilDate::fromYmd((int) date('Y'), (int) date('m'), (int) date('d')),
    $params,
);

header('Content-Type: application/json');
echo json_encode($schedule->forZone(new DateTimeZone('Asia/Kuala_Lumpur'))->toArray());
```

No extensions beyond `date`, no network — it runs on the most restricted shared host.

## Laravel

Bind the calculator once, inject it where you need it. Because parameters are immutable values, keep them out of the container and build them per request (per user preference, per mosque setting):

```php
// AppServiceProvider::register()
use AlFalaq\AlFalaq\Prayer\Services\PrayerTimesCalculator;

$this->app->singleton(PrayerTimesCalculator::class, fn () => PrayerTimesCalculator::default());
```

```php
// A controller method
use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Enums\Prayer;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

public function show(PrayerTimesCalculator $calculator)
{
    $params = CalculationMethod::JAKIM->defaultParameters();
    $schedule = $calculator->calculate(
        Coordinates::fromDegrees((float) $this->mosque->lat, (float) $this->mosque->lng),
        CivilDate::fromYmd(...explode('-', now()->toDateString())),
        $params,
    );

    return response()->json(
        $schedule->forZone(new DateTimeZone($this->mosque->timezone))->toArray(),
    );
}
```

Read the clock with Carbon at the edge and pass UTC into the resolver — never let domain code see a Carbon instance:

```php
$nowUtc = now('UTC')->toImmutable();
$next = (new NextPrayerResolver())->resolve($today, $tomorrow, $nowUtc);
```

Cache monthly timetables per mosque per method (they are deterministic), and refresh on method change rather than on a timer.

## Symfony

Same shape, autowiring-friendly. Register the default as a service and let constructor injection carry it:

```yaml
# config/services.yaml
services:
    AlFalaq\AlFalaq\Prayer\Services\PrayerTimesCalculator:
        factory: ['AlFalaq\AlFalaq\Prayer\Services\PrayerTimesCalculator', 'default']
```

Then type-hint `PrayerTimesCalculator` in your service or controller, build `CalculationParameters` from the user's settings entity, and render with `forZone()`. For `psr/clock` users, Symfony's clock service plugs straight into the edge pattern: `$clock->now()` → normalise to UTC → pass in.

## WordPress

Shortcodes and REST endpoints both work; the constraints are old PHP habits, not the library. Guard the minimum version, keep computation out of the render path (transients for timetables), and always escape on output:

```php
add_shortcode('prayer_times', function ($atts) {
    if (version_compare(PHP_VERSION, '8.2', '<')) {
        return '';
    }
    $schedule = PrayerTimesCalculator::default()->calculate(
        Coordinates::fromDegrees(3.1390, 101.6869),
        CivilDate::fromYmd((int) date('Y'), (int) date('m'), (int) date('d')),
        CalculationMethod::JAKIM->defaultParameters(),
    );
    $fajr = $schedule->forZone(wp_timezone())->timeFor(Prayer::FAJR)->format('H:i');
    return esc_html($fajr);
});
```

## Output shapes for APIs

`PrayerSchedule::all()` keys are exactly `imsak, fajr, sunrise, syuruk, ishraq, dhuha, dhuhr, asr, maghrib, isha` in order (UTC); `toArray()` maps them to ISO-8601 strings; `DisplaySchedule::toArray()` renders the same keys in the display zone. No other keys arrive without a minor version bump — safe to serialise directly.

## See also

- [Getting Started](getting-started/) — the three inputs in plain PHP
- [Timezones & Display](timezone/) — the UTC discipline behind every snippet here
- [Errors](errors/) — what to catch at framework boundaries
