<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\SharedKernel\Exceptions;

/**
 * Coordinates failed validation; the message states the valid range, the
 * received value, and a doc link.
 */
final class InvalidCoordinatesException extends ValidationException
{
    /**
     * Axis named by the failing call ('lat' or 'lon').
     */
    public readonly string $which;

    /**
     * Offending value echoed back for debugging.
     */
    public readonly mixed $received;

    /**
     * Keep the inherited construction shape; factories supply the context.
     *
     * @param string $message Ready-made message from a factory.
     * @param int $code Exception code.
     * @param \Throwable|null $previous Previous exception, if any.
     * @param string $which Either 'lat' or 'lon' (empty when inapplicable).
     * @param mixed $received The offending value (null when inapplicable).
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        string $which = '',
        mixed $received = null,
    ) {
        parent::__construct($message, $code, $previous);
        $this->which = $which;
        $this->received = $received;
    }

    /**
     * Build an out-of-range error naming the axis and what was received.
     *
     * @param string $which Either 'lat' or 'lon'.
     * @param mixed $received The offending value, echoed back for debugging.
     * @return self Ready to throw.
     */
    public static function outOfRange(string $which, mixed $received): self
    {
        $range = $which === 'lat' ? '[-90, 90]' : '[-180, 180]';
        $seen = is_scalar($received) ? var_export($received, true) : gettype($received);

        return new self(
            sprintf(
                'Invalid %s %s: expected %s, see docs/prayer.md. Received: %s.',
                $which === 'lat' ? 'latitude' : 'longitude',
                $which,
                $range,
                $seen,
            ),
            0,
            null,
            $which,
            $received,
        );
    }

    /**
     * Build a misshapen-array error (assoc arrays are rejected to avoid swaps).
     *
     * @return self Ready to throw.
     */
    public static function malformedArray(): self
    {
        return new self(
            'Invalid coordinates array: expected [lat, lon] with integer keys 0 and 1. Assoc ["lat"=>..,"lon"=>..] is rejected to avoid lat/lon swaps. See docs/prayer.md.',
        );
    }
}
