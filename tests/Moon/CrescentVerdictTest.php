<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Moon;

use AlFalaq\AlFalaq\Moon\ValueObjects\CrescentVerdict;
use PHPUnit\Framework\TestCase;

/**
 * CrescentVerdict wire serializer.
 */
final class CrescentVerdictTest extends TestCase
{
    public function testToArrayHasTheWireShape(): void
    {
        self::assertSame(
            [
                'criterion' => 'yallop',
                'visible' => true,
                'score' => 1.2,
                'zone' => 'A',
            ],
            (new CrescentVerdict('yallop', true, 1.2, 'A'))->toArray(),
        );
    }
}
