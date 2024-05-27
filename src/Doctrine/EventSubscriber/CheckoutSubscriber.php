<?php

namespace AppBundle\Doctrine\EventSubscriber;

use AppBundle\Service\LoggingUtils;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;
use Sylius\Component\Order\Model\AdjustmentInterface as SyliusAdjustmentInterface;
use Sylius\Component\Order\Model\OrderAwareInterface;
use Sylius\Component\Order\Model\OrderInterface;

class CheckoutSubscriber implements EventSubscriber
{

    private ?OrderInterface $order = null;

    private array $insertions = [];
    private array $updates = [];
    private array $deletions = [];

    public function __construct(
        private LoggerInterface $checkoutLogger,
        private LoggingUtils $loggingUtils)
    {
    }

    public function getSubscribedEvents(): array
    {
        return array(
            Events::onFlush,
            Events::postFlush,
        );
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();

        $isCheckoutRelated = fn ($entity) => $entity instanceof OrderInterface || $entity instanceof OrderAwareInterface || $entity instanceof SyliusAdjustmentInterface;

        $objects = array_merge(
            array_filter($uow->getScheduledEntityInsertions(), $isCheckoutRelated),
            array_filter($uow->getScheduledEntityUpdates(), $isCheckoutRelated),
            array_filter($uow->getScheduledEntityDeletions(), $isCheckoutRelated),
        );

        if (count($objects) === 0) {
            $this->order = null;
            $this->insertions = [];
            $this->updates = [];
            $this->deletions = [];
        } else {
            $orders = array_filter($objects, fn ($obj) => $obj instanceof OrderInterface);
            if (count($orders) > 0) {
                $this->order = array_values($orders)[0];
            } else {
                foreach ($objects as $object) {
                    if ($object instanceof OrderAwareInterface || $object instanceof SyliusAdjustmentInterface) {
                        $this->order = $object->getOrder();

                        // happens when an item is removed
                        if ($this->order === null) {
                            $this->order = $this->lookForOrderInChanges($uow, $object);
                        }

                        if ($this->order !== null) {
                            break;
                        }
                    }
                }
            }

            $this->insertions = array_map(fn ($entity) => new EntityItem($uow, $entity), $uow->getScheduledEntityInsertions());
            $this->updates = array_map(fn ($entity) => new EntityItem($uow, $entity), $uow->getScheduledEntityUpdates());
            $this->deletions = array_map(fn ($entity) => new EntityItem($uow, $entity), $uow->getScheduledEntityDeletions());
        }
    }

    private function lookForOrderInChanges($unitOfWork, $entity): ?OrderInterface
    {
        foreach ($unitOfWork->getEntityChangeSet($entity) as $changeSet) {
            foreach ($changeSet as $item) {
                if ($item instanceof OrderInterface) {
                    return $item;
                }
            }
        }

        return null;
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if (null === $this->order) {
            return;
        }

        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();

        $this->checkoutLogger->info('CheckoutSubscriber | postFlush',
            ['order' => $this->loggingUtils->getOrderId($this->order)]);

        foreach ($this->insertions as $entity) {
            $this->checkoutLogger->info(sprintf('CheckoutSubscriber | postFlush | inserted: %s', $this->formatEntity($uow, $entity)),
                ['order' => $this->loggingUtils->getOrderId($this->order)]);
        }

        foreach ($this->updates as $entity) {
            $this->checkoutLogger->info(sprintf('CheckoutSubscriber | postFlush | updated: %s', $this->formatEntity($uow, $entity)),
                ['order' => $this->loggingUtils->getOrderId($this->order)]);
        }

        foreach ($this->deletions as $entity) {
            $this->checkoutLogger->info(sprintf('CheckoutSubscriber | postFlush | deleted: %s', $this->formatEntity($uow, $entity)),
                ['order' => $this->loggingUtils->getOrderId($this->order)]);
        }
    }

    private function formatEntity($unitOfWork, EntityItem $entityItem): string {
        return sprintf('%s id:%s',
            get_class($entityItem->entity),
            implode(',', $entityItem->getDatabaseIdentifier($unitOfWork)));
    }
}

class EntityItem {
    private array $initialIdentifier;

    public function __construct(
        $unitOfWork,
        public $entity,
    ) {
        try {
            $this->initialIdentifier = $unitOfWork->getEntityIdentifier($entity);
        } catch (\Exception $e) {
            // happens for entities that are not inserted yet
            $this->initialIdentifier = [];
        }
    }

    public function getDatabaseIdentifier($unitOfWork) {
        $isPersisted = count($this->initialIdentifier) !== 0;

        if ($isPersisted) {
            return $this->initialIdentifier;
        } else {
            return $unitOfWork->getEntityIdentifier($this->entity);
        }
    }
}
