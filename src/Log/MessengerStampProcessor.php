<?php

namespace AppBundle\Log;

use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * This class is used to enhance log messages created by the Messenger component
 * with extra data added to a message (via a stamp) by the StampMiddleware class.
 */
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
