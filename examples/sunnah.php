<?php

declare(strict_types=1);

require __DIR__ . "/../vendor/autoload.php";

use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Services\PrayerTimesCalculator;
use AlFalaq\AlFalaq\Prayer\Services\SunnahCalculator;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

$calculator = PrayerTimesCalculator::default();
$coordinates = Coordinates::fromDegrees(3.1390, 101.6869);
$date = CivilDate::fromYmd(2025, 3, 1);
$parameters = CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters();

$sunnah = SunnahCalculator::fromCalculator($calculator, $coordinates, $date, $parameters)->calculate();
$local = $sunnah->forZone(new DateTimeZone('Asia/Kuala_Lumpur'));

echo 'middle ' . $local->middleOfNight->format('Y-m-d H:i') . PHP_EOL;
echo 'first  ' . $local->firstThird->format('Y-m-d H:i') . PHP_EOL;
echo 'last   ' . $local->lastThird->format('Y-m-d H:i') . PHP_EOL;
