<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;

final class LoopeatReturns
{
    /**
     * @var LoopeatReturn[]
     * @Groups({"order", "update_loopeat_returns"})
     */
    public array $returns = [];
}

