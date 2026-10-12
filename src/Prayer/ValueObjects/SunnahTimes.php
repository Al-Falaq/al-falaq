<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Prayer\ValueObjects;

use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Minutes;

/**
 * Sunnah night markers in UTC (middle, thirds, tahajjud window, maghrib anchor).
 *
 * Anchored at tonight's maghrib and tomorrow's fajr; degenerate intervals
 * (fajr ≤ maghrib, fajr ≤ lastThird) are rejected, never clamped.
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
     * @param \DateTimeImmutable $maghrib Anchoring maghrib (UTC).
     */
    public function __construct(
        public \DateTimeImmutable $middleOfNight,
        public \DateTimeImmutable $firstThird,
        public \DateTimeImmutable $lastThird,
        public \DateTimeImmutable $fajr,
        public \DateTimeImmutable $maghrib,
    ) {
        if ($fajr <= $maghrib) {
            throw new ValidationException('Degenerate Sunnah night: fajr must be after maghrib.');
        }

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
     * UTC-normalized then converted back, so forZone() commutes with it.
     * The minute-rounded result is clamped strictly inside the night, so
     * maghrib < result < fajr holds for every valid fraction on nights
     * longer than two minutes (shorter nights admit no minute-aligned
     * interior instant).
     *
     * @param float $fraction Exclusive (0, 1); 0.5 = middle of night.
     * @return \DateTimeImmutable Instant at that fraction (maghrib zone).
     */
    public function nightFraction(float $fraction): \DateTimeImmutable
    {
        if (! is_finite($fraction) || $fraction <= 0.0 || $fraction >= 1.0) {
            throw new ValidationException('nightFraction expects f in (0, 1) exclusive.');
        }

        $zone = $this->maghrib->getTimezone();

        if ($zone === false) {
            $zone = new \DateTimeZone('UTC');
        }

        $utc = new \DateTimeZone('UTC');
        $maghribUtc = $this->maghrib->setTimezone($utc);
        $fajrUtc = $this->fajr->setTimezone($utc);

        $nightSeconds = $fajrUtc->getTimestamp() - $maghribUtc->getTimestamp();

        $moved = $maghribUtc->modify(sprintf('+%d seconds', (int) round($nightSeconds * $fraction)));

        if ($moved === false) {
            throw new ValidationException('Unable to compute Sunnah fraction.');
        }

        $rounded = Minutes::roundedHalfUp($moved);

        if ($rounded <= $maghribUtc) {
            return Minutes::add(Minutes::roundedHalfUp($maghribUtc), 1)->setTimezone($zone);
        }

        if ($rounded >= $fajrUtc) {
            return Minutes::add(Minutes::roundedHalfUp($fajrUtc), -1)->setTimezone($zone);
        }

        return $rounded->setTimezone($zone);
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
            $this->maghrib->setTimezone($zone),
        );
    }
}
