<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Hijri\Exceptions;

use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;

/**
 * Date outside a calendar's supported range (e.g. past an observational table edge).
 *
 * Boundary violations throw; they never saturate and never wrap (spec §3.2).
 */
final class HijriOutOfRangeException extends ValidationException
{
    /**
     * Calendar key (e.g. 'uqu') from the failing call.
     */
    public readonly string $calendar;

    /**
     * ISO date or year-month that fell outside.
     */
    public readonly string $iso;

    /**
     * Human-readable supported span (e.g. '1423–1500 AH').
     */
    public readonly string $range;

    /**
     * Keep the inherited construction shape; the factory supplies the context.
     *
     * @param string $message Ready-made message from the factory.
     * @param int $code Exception code.
     * @param \Throwable|null $previous Previous exception, if any.
     * @param string $calendar Calendar key (e.g. 'uqu').
     * @param string $iso ISO date or year-month that fell outside.
     * @param string $range Human-readable supported span (e.g. '1423–1500 AH').
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        string $calendar = '',
        string $iso = '',
        string $range = '',
    ) {
        parent::__construct($message, $code, $previous);
        $this->calendar = $calendar;
        $this->iso = $iso;
        $this->range = $range;
    }

    /**
     * Build a range error naming the calendar, the rejected date and the supported span.
     *
     * @param string $calendar Calendar key (e.g. 'uqu').
     * @param string $iso ISO date or year-month that fell outside.
     * @param string $range Human-readable supported span (e.g. '1423–1500 AH').
     * @return self Ready to throw.
     */
    public static function forDate(string $calendar, string $iso, string $range): self
    {
        return new self(
            sprintf('Date %s out of range for %s calendar (%s).', $iso, $calendar, $range),
            0,
            null,
            $calendar,
            $iso,
            $range,
        );
    }
}
