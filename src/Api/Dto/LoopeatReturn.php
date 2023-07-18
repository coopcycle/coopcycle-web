<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;

final class LoopeatReturn
{
    /**
     * @Groups({"order", "update_loopeat_returns"})
     */
    public $format_id;

    /**
     * @Groups({"order", "update_loopeat_returns"})
     */
    public $quantity;
}


