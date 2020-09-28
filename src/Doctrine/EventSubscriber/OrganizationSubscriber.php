<?php

namespace AppBundle\Doctrine\EventSubscriber;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Organization;
use AppBundle\Entity\Store;
use AppBundle\Entity\Task;
use AppBundle\Security\TokenStoreExtractor;
use Doctrine\Common\EventSubscriber;
use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
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

    public function prePersist(LifecycleEventArgs $args)
    {
        $entity = $args->getObject();

        $entityManager = $args->getObjectManager();

        if ($entity instanceof LocalBusiness || $entity instanceof Store) {
            if (null === $entity->getOrganization()) {
                $organization = new Organization();
                $organization->setName($entity->getName());
                $entityManager->persist($organization);
                $entity->setOrganization($organization);
            }
        }

        if ($entity instanceof Task) {

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
