<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Moon\ValueObjects;

/**
 * One criterion's answer: visible or not, with score and zone/class.
 */
final readonly class CrescentVerdict
{
    /**
     * One criterion's answer tuple.
     *
     * @param string $criterion Evaluator key (e.g. 'mabims1992').
     * @param bool $visible Visibility verdict.
     * @param float $score Criterion score (q, V, or elongation).
     * @param string $zone Zone/class label ('visible', 'A'…'E', …).
     */
    public function __construct(
        public string $criterion,
        public bool $visible,
        public float $score,
        public string $zone,
    ) {}
}
