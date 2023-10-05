<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;

final class LoopeatFormat
{
    /**
     * @Groups({"order", "update_loopeat_formats"})
     */
    public $orderItem;

    /**
     * @Groups({"order", "update_loopeat_formats"})
     */
    public array $formats = [];
}

