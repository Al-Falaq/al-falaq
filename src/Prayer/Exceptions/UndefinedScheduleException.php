<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Prayer\Exceptions;

use AlFalaq\AlFalaq\SharedKernel\Exceptions\AstronomicalException;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

/**
 * A polar date has no valid schedule; carries context for the fix message.
 */
final class UndefinedScheduleException extends AstronomicalException
{
    /**
     * Observer location from the failing call.
     */
    public readonly ?Coordinates $coordinates;

    /**
     * Civil date with no sunrise/sunset.
     */
    public readonly ?CivilDate $date;

    /**
     * PolarCircleRule case that was active.
     */
    public readonly ?string $rule;

    /**
     * Keep the inherited construction shape; the factory supplies the context.
     *
     * @param string $message Ready-made message from the factory.
     * @param int $code Exception code.
     * @param \Throwable|null $previous Previous exception, if any.
     * @param Coordinates|null $coordinates Observer location.
     * @param CivilDate|null $date Civil date with no sunrise/sunset.
     * @param string|null $rule PolarCircleRule case that was active.
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        ?Coordinates $coordinates = null,
        ?CivilDate $date = null,
        ?string $rule = null,
    ) {
        parent::__construct($message, $code, $previous);
        $this->coordinates = $coordinates;
        $this->date = $date;
        $this->rule = $rule;
    }

    /**
     * Build a polar-undefined error pointing at the resolving rule.
     *
     * @param \AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates $coordinates Observer location.
     * @param \AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate $date Civil date with no sunrise/sunset.
     * @param string $rule PolarCircleRule case that was active.
     * @return self Ready to throw.
     */
    public static function polar(
        \AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates $coordinates,
        \AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate $date,
        string $rule,
    ): self {
        return new self(
            sprintf(
                'Undefined schedule at %s on %s (rule %s). Try PolarCircleRule::NEAREST_LATITUDE. See docs/polar.md.',
                (string) $coordinates,
                $date->isoformat(),
                $rule,
            ),
            0,
            null,
            $coordinates,
            $date,
            $rule,
        );
    }
}
