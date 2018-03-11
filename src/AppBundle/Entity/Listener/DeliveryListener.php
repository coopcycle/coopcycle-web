<?php

namespace AppBundle\Entity\Listener;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\DeliveryEvent;
use AppBundle\Event\DeliveryConfirmEvent;
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

    public function prePersist(Delivery $delivery, LifecycleEventArgs $args)
    {
        $pickup = $delivery->getPickup();
        $dropoff = $delivery->getDropoff();

        // Make sure legacy "originAddress" & "deliveryAddress" are set
        if (null === $delivery->getOriginAddress() && null !== $pickup) {
            $delivery->setOriginAddress($pickup->getAddress());
        }
        if (null === $delivery->getDeliveryAddress() && null !== $dropoff) {
            $delivery->setDeliveryAddress($dropoff->getAddress());
        }

        // Make sure tasks are linked
        if (null !== $pickup && null !== $dropoff && !$pickup->hasPrevious()) {
            $dropoff->setPrevious($pickup);
        }
    }

    public function postPersist(Delivery $delivery, LifecycleEventArgs $args)
    {
        $this->dispatcher->dispatch(DeliveryCreateEvent::NAME, new DeliveryCreateEvent($delivery));
    }

    public function postUpdate(Delivery $delivery, LifecycleEventArgs $args)
    {
        $unitOfWork = $args->getObjectManager()->getUnitOfWork();
        $entityChangeSet = $unitOfWork->getEntityChangeSet($delivery);

        if (isset($entityChangeSet['status'])) {

            [ $oldValue, $newValue ] = $entityChangeSet['status'];

            $hasBeenConfirmed = $oldValue === Delivery::STATUS_TO_BE_CONFIRMED && $newValue === Delivery::STATUS_CONFIRMED;

            if ($hasBeenConfirmed) {
                $this->dispatcher->dispatch(DeliveryConfirmEvent::NAME, new DeliveryConfirmEvent($delivery));
            }
        }
    }
}
