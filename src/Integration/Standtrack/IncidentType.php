<?php

namespace AppBundle\Integration\Standtrack;


enum IncidentType: int
{
    case CUSTOMS_MERCHANDISE = 1;
    case ABSENT = 2;
    case CLOSED = 3;
    case INCORRECT_ADDRESS = 4;
    case DELIVERY_DEFERRED = 5;
    case DAMAGED_PACKAGE = 6;
    case LOSS_OF_GOODS = 7;
    case MISSING_PACKAGES = 8;
    case LACK_OF_DISPATCH = 9;
    case LOCAL_HOLIDAY = 10;
    case INCIDENT_FROM_ORIGIN = 11;
    case POORLY_CHANNELED = 12;
    case RECIPIENT_REFUSES = 13;
    case NO_MERCHANDISE = 14;
    case NO_SUITCASE = 15;
    case ARRANGED_PICKUP = 16;
    case PICKUP_AT_DELEGATION = 17;
    case IMPROPER_VEHICLE = 18;
    case IMPROPER_MERCHANDISE = 19;
    case LACK_OF_DOCUMENTATION = 20;
}
