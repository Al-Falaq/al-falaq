<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\SharedKernel\Exceptions;

use AlFalaq\AlFalaq\SharedKernel\Contracts\DomainException;

/**
 * Base class for every exception thrown by this library.
 */
abstract class AlFalaqException extends \RuntimeException implements DomainException {}
