<?php

declare(strict_types=1);

arch('value objects are final')
    ->expect('AlFalaq\AlFalak\SharedKernel\ValueObjects')
    ->toBeFinal();

arch('services are final')
    ->expect('AlFalaq\AlFalak\Prayer\Services')
    ->toBeFinal();

arch('no Util namespace regrowth')
    ->expect('AlFalaq\AlFalak')
    ->not->toUse('AlFalaq\AlFalak\Util');

arch('exceptions extend base')
    ->expect('AlFalaq\AlFalak\SharedKernel\Exceptions')
    ->toExtend('AlFalaq\AlFalak\SharedKernel\Exceptions\AlFalakException');
