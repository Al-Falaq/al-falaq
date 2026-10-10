<?php

declare(strict_types=1);

require __DIR__ . "/../vendor/autoload.php";

use AlFalaq\AlFalaq\Moon\Services\CrescentCalculator;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

$verdicts = CrescentCalculator::default()->evaluate(
    Coordinates::fromDegrees(3.1390, 101.6869), // Kuala Lumpur
    CivilDate::fromYmd(2025, 2, 28),
);

echo sprintf(
    "yallop=%s odeh=%s neo=%s old=%s\n",
    $verdicts['yallop']->zone,
    $verdicts['odeh']->zone,
    $verdicts['neomabims2021']->visible ? 'true' : 'false',
    $verdicts['mabims1992']->visible ? 'true' : 'false',
);
