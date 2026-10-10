<?php

declare(strict_types=1);

require __DIR__ . "/../vendor/autoload.php";

// M0 placeholder: lists supported methods (values frozen in Appendix A, M1).
foreach (AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod::cases() as $method) {
    echo $method->value . PHP_EOL;
}
