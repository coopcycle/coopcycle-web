<?php

namespace AppBundle\Integration\Rdc\Enum;

enum ServiceStatus: string
{
    case DRAFT = 'DRAFT';
    case PROPOSED = 'PROPOSED';
    case ACCEPTED = 'ACCEPTED';
    case CANCELLED = 'CANCELLED';
}
