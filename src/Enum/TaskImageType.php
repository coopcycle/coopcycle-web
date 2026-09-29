<?php

declare(strict_types=1);

namespace AppBundle\Enum;

enum TaskImageType: string
{
    case SIGNATURE = 'signature';
    case PHOTO = 'photo';
}
