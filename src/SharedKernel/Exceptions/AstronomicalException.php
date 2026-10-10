<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\SharedKernel\Exceptions;

/**
 * The sky has no answer for these inputs (polar day/night, no convergence).
 *
 * Never carries NaN or null; catch it and pick a handler, don't guess.
 */
class AstronomicalException extends AlFalaqException {}
