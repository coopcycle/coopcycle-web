<?php

namespace AppBundle\Integration\Rdc\Enum;

enum ExecutionStatus: string
{
    case SCHEDULED = 'SCHEDULED';
    case NOT_SCHEDULED = 'NOT SCHEDULED';
    case STARTED = 'STARTED';
    case FINISHED = 'FINISHED';
}
