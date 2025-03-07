<?php

namespace AppBundle\Integration\Standtrack;

enum EventType: int
{
    case COLLECTED = 1;
    case IN_TRANSIT = 2;
    case INTERMEDIATE_DELIVERY = 3;
    case AVAILABLE = 4;
    case IN_DELIVERY = 5;
    case DELIVERED = 6;
    case PARTIAL_DELIVERY = 7;
    case RETURNED = 8;
    case RECHANNELED = 9;
    case DESTROYED = 10;
    case INCIDENT = 11;
}
