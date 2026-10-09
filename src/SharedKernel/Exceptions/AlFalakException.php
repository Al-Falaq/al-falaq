<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\SharedKernel\Exceptions;

use AlFalaq\AlFalak\SharedKernel\Contracts\DomainException;

abstract class AlFalakException extends \RuntimeException implements DomainException {}
