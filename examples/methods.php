<?php

declare(strict_types=1);

require __DIR__ . "/../vendor/autoload.php";

// Lists supported methods (values frozen in Appendix A).
foreach (AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod::cases() as $method) {
    echo $method->value . PHP_EOL;
}
