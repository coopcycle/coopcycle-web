<?php

namespace AppBundle\Integration\Rdc\Enum;

enum EventCode: string
{
    case SERVICE_STARTED = 'SERVICE_STARTED';
    case SERVICE_FINISHED = 'SERVICE_FINISHED';
    case DEPARTURE = 'DEPARTURE';
    case DELIVERY = 'DELIVERY';
    case LATE_ARRIVAL = 'LATE_ARRIVAL';
    case GOODS_ISSUE = 'GOODS_ISSUE';
    case ACCIDENT = 'ACCIDENT';
    case ACTIVITY_STARTED = 'ACTIVITY_STARTED';
    case ACTIVITY_FINISHED = 'ACTIVITY_FINISHED';
}