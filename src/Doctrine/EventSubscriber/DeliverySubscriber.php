<?php

namespace AppBundle\Doctrine\EventSubscriber;

use AppBundle\Entity\Delivery;
use AppBundle\Service\DeliveryCreatedNotifier;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::onFlush, connection: 'default')]
#[AsDoctrineListener(event: Events::postFlush, connection: 'default')]
class DeliverySubscriber
{
    private $deliveries = [];
    private $onFlushCalled = false;

    public function __construct(private readonly DeliveryCreatedNotifier $deliveryCreatedNotifier)
    {
    }

    public function onFlush(OnFlushEventArgs $args)
    {
        // When a store has tags, TaggableSubscriber will be called.
        // In this case, DeliverySubscriber::onFlush() will be called twice.
        if ($this->onFlushCalled) {
            return;
        }

        $this->deliveries = [];

        $em = $args->getEntityManager();
        $uow = $em->getUnitOfWork();

        $entities = $uow->getScheduledEntityInsertions();

        $this->deliveries = array_filter($entities, function ($entity) {

            if (!$entity instanceof Delivery) {
                return false;
            }

            if (null === $entity->getOrder()) {
                return true;
            }

            return !$entity->getOrder()->hasVendor();
        });

        $this->onFlushCalled = true;
    }

    public function postFlush(PostFlushEventArgs $args)
    {
        foreach ($this->deliveries as $delivery) {
            $this->deliveryCreatedNotifier->notify($delivery);
        }

        $this->deliveries = [];
        $this->onFlushCalled = false;
    }
}
