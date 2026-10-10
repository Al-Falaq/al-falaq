<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Prayer\ValueObjects;

use AlFalaq\AlFalaq\Prayer\Enums\Prayer;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;

/**
 * Per-prayer minute offsets keyed by Prayer value (method + caller layers).
 */
final readonly class PrayerAdjustments
{
    /**
     * @param array<string, int> $offsets Prayer value → minutes (-60…60).
     */
    private function __construct(public array $offsets) {}

    /**
     * Zero adjustments everywhere (ordering invariant holds).
     *
     * @return self Empty set.
     */
    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * Set one prayer's offset, keeping the rest.
     *
     * @param Prayer $prayer Which marker.
     * @param int $minutes Offset in [-60, 60].
     * @return self New set; this instance is unchanged.
     */
    public function with(Prayer $prayer, int $minutes): self
    {
        if ($minutes < -60 || $minutes > 60) {
            throw new ValidationException(sprintf('Adjustment for %s must be in [-60, 60], got %d.', $prayer->value, $minutes));
        }

        $offsets = $this->offsets;
        $offsets[$prayer->value] = $minutes;

        return new self($offsets);
    }

    /**
     * Read one prayer's offset (0 when unset).
     *
     * @param Prayer $prayer Which marker.
     * @return int Offset minutes.
     */
    public function for(Prayer $prayer): int
    {
        return $this->offsets[$prayer->value] ?? 0;
    }

    /**
     * Add two layers together (method + caller = effective offset).
     *
     * @param self $other Layer to add on top.
     * @return self Merged set; neither input changes.
     */
    public function combinedWith(self $other): self
    {
        $merged = $this->offsets;

        foreach ($other->offsets as $key => $value) {
            $merged[$key] = ($merged[$key] ?? 0) + $value;
        }

        return new self($merged);
    }
}
