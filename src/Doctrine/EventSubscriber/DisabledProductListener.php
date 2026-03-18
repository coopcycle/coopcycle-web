<?php

namespace AppBundle\Doctrine\EventSubscriber;

use AppBundle\Entity\Sylius\Product;
use AppBundle\Entity\Sylius\ProductOptionValue;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Events;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

#[AsDoctrineListener(event: Events::onFlush, connection: 'default')]
class DisabledProductListener
{
    public function __construct(private EventDispatcherInterface $eventDispatcher)
    {}

    public function onFlush(OnFlushEventArgs $args): void
    {
        /** @var \Doctrine\ORM\EntityManagerInterface */
        $objectManager = $args->getObjectManager();
        $unitOfWork = $objectManager->getUnitOfWork();

        $scheduledUpdates = $unitOfWork->getScheduledEntityUpdates();

        foreach ($scheduledUpdates as $entity) {
            if (!$entity instanceof Product) {
                continue;
            }

            $unitOfWork->computeChangeSet($objectManager->getClassMetadata(Product::class), $entity);

            $changeset = $unitOfWork->getEntityChangeSet($entity);

            if (isset($changeset['enabled'])) {
                [, $newValue] = $changeset['enabled'];
                $optionValues = $objectManager->getRepository(ProductOptionValue::class)->findBy(['product' => $entity]);
                foreach ($optionValues as $optionValue) {
                    $optionValue->setEnabled($newValue);
                    $unitOfWork->scheduleForUpdate($optionValue);
                }

                $restaurant = $entity->getRestaurant();
                if (null !== $restaurant) {
                    $this->eventDispatcher->dispatch(new GenericEvent($restaurant), 'catalog.updated');
                }
            }
        }
    }
}
