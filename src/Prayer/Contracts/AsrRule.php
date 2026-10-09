<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Prayer\Contracts;

/**
 * Asr juristic variant: shadow-length factor past solar noon.
 *
 * Implemented by ShafiAsr (×1) and HanafiAsr (×2); picked via Madhab.
 */
interface AsrRule
{
    /**
     * Shadow length in object-heights defining Asr start.
     *
     * @return float 1.0 (Shafi'i) or 2.0 (Hanafi).
     */
    public function shadowLength(): float;
}
