<?php

namespace AppBundle\Doctrine\EventSubscriber;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Task;
use AppBundle\Message\IndexDeliveries;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class SearchDeliveriesSubscriber implements EventSubscriber
{
    private $deliveries = [];

    public function __construct(private MessageBusInterface $messageBus)
    {}

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

        $isDeliveryOrTask = fn ($entity) => $entity instanceof Delivery || $entity instanceof Task;

        $objects = array_merge(
            array_filter($uow->getScheduledEntityInsertions(), $isDeliveryOrTask),
            array_filter($uow->getScheduledEntityUpdates(), $isDeliveryOrTask)
        );

        if (count($objects) === 0) {
            return;
        }

        foreach ($objects as $object) {

            $delivery = ($object instanceof Task) ? $object->getDelivery() : $object;

            if (null === $delivery) {
                continue;
            }

            $hash = spl_object_hash($delivery);

            if (!isset($this->deliveries[$hash])) {
                $this->deliveries[$hash] = $delivery;
            }
        }
    }

    public function postFlush(PostFlushEventArgs $args)
    {
        if (count($this->deliveries) === 0) {
            return;
        }

        $ids = array_map(fn (Delivery $d) => $d->getId(), $this->deliveries);

        $this->messageBus->dispatch(
            new IndexDeliveries($ids)
        );
    }
}
