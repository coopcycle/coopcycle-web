<?php

namespace AppBundle\Integration\Rdc\Enum;

enum ActionSubtype: string
{
    case LOADING = 'LOADING';
    case UNLOADING = 'UNLOADING';
}
