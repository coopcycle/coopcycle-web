<?php

namespace AppBundle\Integration\Rdc\Enum;

enum ReasonCode: string
{
    case HUMAN_MEAN = 'HUMAN_MEAN';
    case MATERIAL_ISSUE = 'MATERIAL_ISSUE';
    case MATERIAL_MEAN = 'MATERIAL_MEAN';
    case MISSING_DOCUMENT = 'MISSING_DOCUMENT';
    case SANITY_CHECK = 'SANITY_CHECK';
    case SECURITY_CHECK = 'SECURITY_CHECK';
    case TRANSPORT_DAMAGE = 'TRANSPORT_DAMAGE';
    case ACCIDENT = 'ACCIDENT';
    case TRAFIC = 'TRAFIC';
    case WEATHER = 'WEATHER';
    case MECHANICAL = 'MECHANICAL';
    case OTHER = 'OTHER';
}
