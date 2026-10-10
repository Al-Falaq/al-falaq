<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Prayer\Enums;

use AlFalaq\AlFalaq\Prayer\ValueObjects\CalculationParameters;

/**
 * The 18 frozen calculation methods (Appendix A normative, M1 freeze).
 *
 * String values are lowercase canonical keys; NONE means "caller supplies
 * every parameter, no defaults".
 */
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
     * Shipped defaults for this method (M1; M0 throws until Appendix A frozen).
     *
     * @return CalculationParameters Defaults to customize via with*().
     */
    public function defaultParameters(): CalculationParameters
    {
        throw new \LogicException('not implemented: Appendix A freeze pending PYTHON_PIN (M1)');
    }
}
