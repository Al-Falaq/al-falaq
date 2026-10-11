<?php

declare(strict_types=1);

require __DIR__ . "/../vendor/autoload.php";

use AlFalaq\AlFalaq\Hijri\Enums\MabimsCountry;
use AlFalaq\AlFalaq\Hijri\Services\GregorianToHijriConverter;
use AlFalaq\AlFalaq\Hijri\Strategies\MabimsCalendar;
use AlFalaq\AlFalaq\Hijri\Strategies\TabularCalendar;
use AlFalaq\AlFalaq\Hijri\Strategies\UmmAlQuraCalendar;

$converter = GregorianToHijriConverter::default();
$instant = new DateTimeImmutable('2025-03-01 12:00:00', new DateTimeZone('UTC'));

echo 'tabular   ' . $converter->convert($instant, new TabularCalendar())->isoformat() . PHP_EOL;
echo 'uqu       ' . $converter->convert($instant, new UmmAlQuraCalendar())->isoformat() . PHP_EOL;
echo 'mabims/MY ' . $converter->convert($instant, new MabimsCalendar(MabimsCountry::MY))->isoformat() . PHP_EOL;
