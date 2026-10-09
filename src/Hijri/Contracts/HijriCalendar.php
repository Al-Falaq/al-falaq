<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Hijri\Contracts;

use AlFalaq\AlFalak\Hijri\ValueObjects\HijriDate;
use AlFalaq\AlFalak\SharedKernel\ValueObjects\CivilDate;

/**
 * One Hijri calendar system: conversion both ways + metadata (driven port).
 *
 * Tabular (algorithmic), Umm al-Qura (shipped table), MABIMS country rules.
 */
interface HijriCalendar
{
    /**
     * Convert a Gregorian instant or civil date to Hijri.
     *
     * @param \DateTimeImmutable|CivilDate $date Source; zone-aware, never naive.
     * @return HijriDate Converted date.
     */
    public function fromGregorian(\DateTimeImmutable|CivilDate $date): HijriDate;

    /**
     * Days in a Hijri month (29 or 30, calendar-specific).
     *
     * @param int $year Hijri year.
     * @param int $month Hijri month 1–12.
     * @return int Month length in days.
     */
    public function monthLength(int $year, int $month): int;

    /**
     * Stable calendar key for goldens and provenance.
     *
     * @return string e.g. 'tabular', 'umm-al-qura', 'mabims'.
     */
    public function name(): string;
}
