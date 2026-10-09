<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\SharedKernel\Contracts;

/**
 * Marker for every domain exception in this library.
 *
 * Lets host apps catch `DomainException` once instead of listing each type.
 */
interface DomainException extends \Throwable {}
