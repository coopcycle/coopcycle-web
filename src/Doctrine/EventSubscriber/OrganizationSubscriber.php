<?php

namespace AppBundle\Doctrine\EventSubscriber;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Organization;
use AppBundle\Entity\Store;
use AppBundle\Entity\Task;
use AppBundle\Security\TokenStoreExtractor;
use Doctrine\Common\EventSubscriber;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;

class OrganizationSubscriber implements EventSubscriber
{
    private $storeExtractor;

    public function __construct(
        TokenStoreExtractor $storeExtractor)
    {
        $this->storeExtractor = $storeExtractor;
    }

    public function getSubscribedEvents()
    {
        return array(
            Events::prePersist,
        );
    }

    /**
     * Tries to find an organization to attach the Task to.
     */
    public function prePersist(LifecycleEventArgs $args)
    {
        $entity = $args->getObject();

        $entityManager = $args->getObjectManager();

        // If the persisted entiy is a LocalBusiness or a Store,
        // and it is not linked to an Organization,
        // create an Organization with the same name
        if ($entity instanceof LocalBusiness || $entity instanceof Store) {
            if (null === $entity->getOrganization()) {
                $organization = new Organization();
                $organization->setName($entity->getName());
                $entityManager->persist($organization);
                $entity->setOrganization($organization);
            }
        }

        // If the persisted entiy is a Task, and it's not linked to an Organization,
        // try to find an Organization depending on the context
        if ($entity instanceof Task) {

            // If the Task is already attached to an Organization, ignore
            if (null !== $entity->getOrganization()) {
                return;
            }

            $store = $this->storeExtractor->extractStore();
            if (null !== $store) {
                $entity->setOrganization($store->getOrganization());
                return;
            }

            $delivery = $entity->getDelivery();
            if (null !== $delivery) {
                $store = $delivery->getStore();
                $order = $delivery->getOrder();
                if (null !== $store) {
                    $entity->setOrganization($store->getOrganization());
                } elseif (null !== $order) {
                    $restaurant = $order->getRestaurant();
                    if (null !== $restaurant) {
                        $entity->setOrganization($restaurant->getOrganization());
                    }
                }
            }
        }
    }
}
