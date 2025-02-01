<?php

namespace AppBundle\Log;

use Symfony\Component\Messenger\Stamp\StampInterface;

abstract class MessengerStampProcessor
{
    private ?StampInterface $stamp = null;

    public function getStamp(): ?StampInterface
    {
        return $this->stamp;
    }

    public function setStamp(?StampInterface $stamp): void
    {
        $this->stamp = $stamp;
    }
}
