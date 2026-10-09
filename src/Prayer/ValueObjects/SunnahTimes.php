<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Prayer\ValueObjects;

use AlFalaq\AlFalak\SharedKernel\Exceptions\ValidationException;

/**
 * Sunnah night markers in UTC (middle, thirds, tahajjud window).
 *
 * Anchored at tonight's maghrib and tomorrow's fajr; degenerate intervals
 * (fajr ≤ maghrib) are rejected, never clamped.
 */
final readonly class SunnahTimes
{
    /**
     * Bind tonight's maghrib-to-fajr anchors (all UTC).
     *
     * @param \DateTimeImmutable $middleOfNight Mid-maghrib→fajr (UTC).
     * @param \DateTimeImmutable $firstThird One-third point (UTC).
     * @param \DateTimeImmutable $lastThird Two-thirds point (UTC).
     * @param \DateTimeImmutable $fajr Anchoring fajr (UTC).
     */
    public function __construct(
        public \DateTimeImmutable $middleOfNight,
        public \DateTimeImmutable $firstThird,
        public \DateTimeImmutable $lastThird,
        public \DateTimeImmutable $fajr,
    ) {
        if ($fajr <= $lastThird) {
            throw new ValidationException('Degenerate Sunnah night: fajr must be after last third.');
        }
    }

    /**
     * Tahajjud window: last third up to (excluding) fajr.
     *
     * @return array{from: \DateTimeImmutable, to: \DateTimeImmutable} Window bounds (UTC).
     */
    public function tahajjudWindow(): array
    {
        return ['from' => $this->lastThird, 'to' => $this->fajr];
    }

    /**
     * Arbitrary night fraction between maghrib and fajr (M2).
     *
     * @param float $fraction Exclusive (0, 1); 0.5 = middle of night.
     * @return \DateTimeImmutable Instant at that fraction (UTC).
     */
    public function nightFraction(float $fraction): \DateTimeImmutable
    {
        if (! is_finite($fraction) || $fraction <= 0.0 || $fraction >= 1.0) {
            throw new ValidationException('nightFraction expects f in (0, 1) exclusive.');
        }

        throw new \LogicException('not implemented: SunnahCalculator pending M2');
    }

    /**
     * Convert all markers to a display zone (leaf conversion).
     *
     * @param \DateTimeZone $zone Display zone.
     * @return self Same markers in the new zone.
     */
    public function forZone(\DateTimeZone $zone): self
    {
        return new self(
            $this->middleOfNight->setTimezone($zone),
            $this->firstThird->setTimezone($zone),
            $this->lastThird->setTimezone($zone),
            $this->fajr->setTimezone($zone),
        );
    }
}
