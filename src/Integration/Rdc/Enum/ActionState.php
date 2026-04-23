<?php

namespace AppBundle\Integration\Rdc\Enum;

enum ActionState: string
{
    case REQUESTED = 'REQUESTED';
    case SCHEDULED = 'SCHEDULED';
    case ACTUAL = 'ACTUAL';
}
