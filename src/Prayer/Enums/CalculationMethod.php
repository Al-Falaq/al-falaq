<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Prayer\Enums;

use AlFalaq\AlFalak\Prayer\ValueObjects\CalculationParameters;

enum CalculationMethod: string
{
    case NONE = 'none';
    case MUSLIM_WORLD_LEAGUE = 'muslim_world_league';
    case EGYPTIAN = 'egyptian';
    case KARACHI = 'karachi';
    case UMM_AL_QURA = 'umm_al_qura';
    case DUBAI = 'dubai';
    case MOON_SIGHTING_COMMITTEE = 'moon_sighting_committee';
    case NORTH_AMERICA = 'north_america';
    case KUWAIT = 'kuwait';
    case QATAR = 'qatar';
    case SINGAPORE = 'singapore';
    case TURKEY = 'turkey';
    case TEHRAN = 'tehran';
    case MOROCCO = 'morocco';
    case JAKIM = 'jakim';
    case KEMENAG = 'kemenag';
    case MUIS = 'muis';
    case BRUNEI = 'brunei';

    /**
     * M0 stub: throws until Appendix A is frozen from PYTHON_PIN (M1).
     */
    public function defaultParameters(): CalculationParameters
    {
        throw new \LogicException('not implemented: Appendix A freeze pending PYTHON_PIN (M1)');
    }
}
