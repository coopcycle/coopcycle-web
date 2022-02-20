<?php

namespace AppBundle\Doctrine\EventSubscriber;

use AppBundle\Entity\Delivery;
use AppBundle\Message\DeliveryCreated;
use Doctrine\Common\EventSubscriber;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\Messenger\MessageBusInterface;

class DeliverySubscriber implements EventSubscriber
{
    private $messageBus;
    private $deliveries = [];

    public function __construct(MessageBusInterface $messageBus)
    {
        $this->messageBus = $messageBus;
    }

    public function getSubscribedEvents()
    {
        return array(
            Events::onFlush,
            Events::postFlush,
        );
    }

    public function onFlush(OnFlushEventArgs $args)
    {
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
    }

    public function postFlush(PostFlushEventArgs $args)
    {
        foreach ($this->deliveries as $delivery) {
            $this->messageBus->dispatch(
                new DeliveryCreated($delivery)
            );
        }
    }
}
