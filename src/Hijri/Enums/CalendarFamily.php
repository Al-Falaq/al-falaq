<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Hijri\Enums;

/**
 * Underlying calendar tradition behind a Hijri calendar key.
 *
 * `TABULAR` is the arithmetic rule; `UMM_AL_QURA` and `MABIMS` are
 * table-backed families whose generated month starts are shipped with the
 * library. A custom calendar may omit its family entirely.
 */
enum CalendarFamily: string
{
    case TABULAR = 'tabular';
    case UMM_AL_QURA = 'umm_al_qura';
    case MABIMS = 'mabims';
}
