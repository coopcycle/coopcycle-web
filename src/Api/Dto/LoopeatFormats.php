<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;

final class LoopeatFormats
{
    /**
     * @var LoopeatFormat[]
     * @Groups({"order", "update_loopeat_formats"})
     */
    public array $items = [];
}
