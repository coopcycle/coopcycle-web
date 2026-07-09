<?php

declare(strict_types=1);

namespace AppBundle\Integration\Rdc\Enum;

enum ResponseStatus: string
{
    case DRAFT = 'DRAFT';
    case PROPOSED = 'PROPOSED';
    case RESPONDED = 'RESPONDED';
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';
    case CANCELLED = 'CANCELLED';
}