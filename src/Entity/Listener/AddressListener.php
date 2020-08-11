<?php

namespace AppBundle\Entity\Listener;

use AppBundle\Entity\Address;
use AppBundle\Message\CalculateRoute;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Symfony\Component\Messenger\MessageBusInterface;

class AddressListener
{
    private $messageBus;

    public function __construct(MessageBusInterface $messageBus)
    {
        $this->messageBus = $messageBus;
    }

    public function preUpdate(Address $address, PreUpdateEventArgs $event)
    {
        if ($event->hasChangedField('streetAddress') || $event->hasChangedField('geo')) {
            $this->messageBus->dispatch(
                new CalculateRoute($address->getId())
            );
        }
    }
}
