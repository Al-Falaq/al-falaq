<?php

declare(strict_types=1);

require __DIR__ . "/../vendor/autoload.php";

use AlFalaq\AlFalak\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalak\SharedKernel\ValueObjects\Coordinates;

$coords = Coordinates::fromDegrees(3.1390, 101.6869); // Kuala Lumpur
$date = CivilDate::fromDateTime(new DateTimeImmutable('now', new DateTimeZone('UTC')));

echo sprintf("Coordinates: %s\nDate (UTC): %s\n", (string) $coords, $date->isoformat());
echo "M0 skeleton: PrayerTimesCalculator::calculate() throws LogicException until M1 (Appendix A freeze).\n";
echo "See Technical-PRD.md §5.2 + Appendix A.\n";
