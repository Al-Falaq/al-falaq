<?php

declare(strict_types=1);

require __DIR__ . "/../vendor/autoload.php";

use AlFalaq\AlFalak\Hijri\Strategies\TabularCalendar;
use AlFalaq\AlFalak\Hijri\Services\GregorianToHijriConverter;

try {
    $hijri = (new GregorianToHijriConverter())->convert(
        new DateTimeImmutable('2025-03-01 12:00:00', new DateTimeZone('UTC')),
        new TabularCalendar(),
    );
    echo $hijri->isoformat() . PHP_EOL;
} catch (LogicException $e) {
    echo 'M0: ' . $e->getMessage() . PHP_EOL;
}
