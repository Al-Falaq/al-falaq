<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\SharedKernel\ValueObjects;

use AlFalaq\AlFalaq\SharedKernel\Enums\WarningCode;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;

/**
 * One machine-readable warning with a human explanation and scalar context.
 *
 * The code and detail keys are the stable contract; the message is
 * explanatory and may change in a minor release.
 */
final readonly class CalculationWarning
{
    /**
     * Bind a warning (message and details validated here).
     *
     * @param WarningCode $code Stable machine identifier.
     * @param string $message Human explanation; non-empty after trimming.
     * @param array<string, int|float|string|bool|null> $details Scalar context; no nesting.
     */
    public function __construct(
        public WarningCode $code,
        public string $message,
        public array $details = [],
    ) {
        if (trim($message) === '') {
            throw new ValidationException('Warning message must not be blank.');
        }

        foreach ($details as $key => $value) {
            if (! is_string($key) || trim($key) === '') {
                throw new ValidationException('Warning detail keys must be non-empty strings.');
            }

            self::assertDetailValue($value);
        }
    }

    /**
     * Wire shape with detail keys sorted for deterministic output.
     *
     * @return array{code: string, message: string, details: array<string, int|float|string|bool|null>}
     */
    public function toArray(): array
    {
        $details = $this->details;
        ksort($details, SORT_STRING);

        return [
            'code' => $this->code->value,
            'message' => $this->message,
            'details' => $details,
        ];
    }

    /**
     * Accept a finite scalar or null; reject arrays, objects, and non-finite floats.
     *
     * @param mixed $value Runtime detail value.
     * @return void Throws when the value is not representable in JSON metadata.
     */
    private static function assertDetailValue(mixed $value): void
    {
        if ($value === null || is_int($value) || is_string($value) || is_bool($value)) {
            return;
        }

        if (is_float($value) && is_finite($value)) {
            return;
        }

        throw new ValidationException('Warning detail values must be finite scalars or null.');
    }
}
