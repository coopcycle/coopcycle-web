<?php

namespace AppBundle\Doctrine\EventSubscriber;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Task;
use AppBundle\Message\IndexDeliveries;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

#[AsDoctrineListener(event: Events::onFlush, connection: 'default')]
#[AsDoctrineListener(event: Events::postFlush, connection: 'default')]
class SearchDeliveriesSubscriber
{
    private $deliveries = [];

    public function __construct(private MessageBusInterface $messageBus)
    {}

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

        $envelope = new Envelope(new IndexDeliveries($ids));

        // When flushing inside a wider transaction (i.e when importing deliveries),
        // the deliveries are not committed yet, and the worker would not find them.
        // Wait for the current message to be handled, it's dropped if it fails.
        if ($args->getObjectManager()->getConnection()->isTransactionActive()) {
            $envelope = $envelope->with(new DispatchAfterCurrentBusStamp());
        }

        $this->messageBus->dispatch($envelope);
    }
}
