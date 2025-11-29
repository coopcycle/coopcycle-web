<?php

namespace AppBundle\Message;

class CopyProducts
{
    public function __construct(private int $srcId, private int $destId)
    {}

    public function getSrcId(): int
    {
        return $this->srcId;
    }

    public function getDestId(): int
    {
        return $this->destId;
    }
}

