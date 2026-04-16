<?php

namespace AppBundle\Integration\Rdc\Enum;

enum ContactRole: string
{
    case CUSTOMER_DISPATCH = 'CUSTOMER_DISPATCH';
    case PROVIDER_DISPATCH = 'PROVIDER_DISPATCH';
    case RECIPIENT = 'RECIPIENT';
    case BIKE_COURIER = 'BIKE_COURIER';
}
