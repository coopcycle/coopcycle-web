<?php

namespace AppBundle\Integration\Rdc\Enum;

enum ContainerType: string
{
    case PARCEL = 'PARCEL';
    case PALLET = 'PALLET';
    case CONTAINER_ISO = 'CONTAINER_ISO';
}
