<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Prayer\ValueObjects;

use AlFalaq\AlFalak\Prayer\Enums\Prayer;
use AlFalaq\AlFalak\SharedKernel\Exceptions\ValidationException;

final readonly class PrayerAdjustments
{
    /** @param array<string, int> $offsets */
    private function __construct(public array $offsets) {}

    public static function empty(): self
    {
        return new self([]);
    }

    public function with(Prayer $prayer, int $minutes): self
    {
        if ($minutes < -60 || $minutes > 60) {
            throw new ValidationException(sprintf('Adjustment for %s must be in [-60, 60], got %d.', $prayer->value, $minutes));
        }

        $offsets = $this->offsets;
        $offsets[$prayer->value] = $minutes;

        return new self($offsets);
    }

    public function for(Prayer $prayer): int
    {
        return $this->offsets[$prayer->value] ?? 0;
    }

    public function combinedWith(self $other): self
    {
        $merged = $this->offsets;

        foreach ($other->offsets as $key => $value) {
            $merged[$key] = ($merged[$key] ?? 0) + $value;
        }

        return new self($merged);
    }
}
