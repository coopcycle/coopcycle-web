<?php

namespace AppBundle\Integration\Rdc\Enum;

enum EventType: string
{
    case SCHEDULE = 'SCHEDULE';
    case TRANSPORT = 'TRANSPORT';
    case INCIDENT = 'INCIDENT';
}
