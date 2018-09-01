<?php

namespace AppBundle\EventSubscriber;

use AppBundle\Domain\Restaurant\Event\StateChanged;
use AppBundle\Entity\Restaurant;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;
use SimpleBus\Message\Recorder\ContainsRecordedMessages;

final class EntityChangeSetSubscriber implements EventSubscriber, ContainsRecordedMessages
{
    private $collectedEvents = array();

    public function __construct($eventBus, LoggerInterface $logger)
    {
        $this->eventBus = $eventBus;
        $this->logger = $logger;
    }

    public function getSubscribedEvents()
    {
        return array(
            Events::onFlush,
            // Events::postFlush,
        );
    }

    public function onFlush(OnFlushEventArgs $args)
    {
        $em = $args->getEntityManager();
        $unitOfWork = $em->getUnitOfWork();

        $restaurants = array_filter($unitOfWork->getScheduledEntityUpdates(), function ($entity) {
            return $entity instanceof Restaurant;
        });

        $this->logger->debug(sprintf('event_bus: %d', count($restaurants)));

        foreach ($restaurants as $restaurant) {

            $entityChangeSet = $unitOfWork->getEntityChangeSet($restaurant);

            if (isset($entityChangeSet['state'])) {
                [ $oldValue, $newValue ] = $entityChangeSet['state'];

                $this->logger->debug('event_bus: COLLECTED EVENT');

                // $this->collectedEvents[] = new StateChanged();
                $this->eventBus->handle(new StateChanged());
            }
        }

    }

    public function recordedMessages()
    {
        return $this->collectedEvents;
    }

    public function eraseMessages()
    {
        $this->collectedEvents = array();
    }

    public function postFlush(PostFlushEventArgs $args)
    {
        $this->logger->debug('event_bus: IT WORKS');
    }
}
