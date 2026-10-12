<?php

declare(strict_types=1);

require __DIR__ . "/../vendor/autoload.php";

// Lists supported methods (values frozen at M1).
foreach (AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod::cases() as $method) {
    echo $method->value . PHP_EOL;
}
