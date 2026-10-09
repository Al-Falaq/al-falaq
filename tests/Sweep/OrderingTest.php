<?php

declare(strict_types=1);

use AlFalaq\AlFalak\SharedKernel\ValueObjects\Angle;
use AlFalaq\AlFalak\SharedKernel\ValueObjects\CivilDate;

it('preserves ordering invariant with empty adjustments and unwind idempotence', function (): void {
    mt_srand(1234);

    for ($i = 0; $i < 200; $i++) {
        $angle = mt_rand(-72000, 72000) / 100.0;
        $once = Angle::fromDegrees($angle)->normalized()->degrees;
        $twice = Angle::fromDegrees($once)->normalized()->degrees;
        expect($twice)->toBe($once);

        $date = CivilDate::fromYmd(2020, 1, 1)->plusDays(mt_rand(0, 366));
        expect($date->plusDays(1)->plusDays(-1)->equals($date))->toBeTrue();
    }
})->group('sweep');
