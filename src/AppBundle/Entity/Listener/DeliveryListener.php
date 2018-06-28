<?php

namespace AppBundle\Entity\Listener;

use AppBundle\Entity\Delivery;
use AppBundle\Event\DeliveryCreateEvent;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class DeliveryListener
{
    private $dispatcher;

    public function __construct(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    public function postPersist(Delivery $delivery, LifecycleEventArgs $args)
    {
        $this->dispatcher->dispatch(DeliveryCreateEvent::NAME, new DeliveryCreateEvent($delivery));
    }
}
