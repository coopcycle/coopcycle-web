<?php

namespace AppBundle\Doctrine\EventSubscriber;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Organization;
use AppBundle\Entity\Store;
use Doctrine\Common\EventSubscriber;
use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;

class OrganizationSubscriber implements EventSubscriber
{
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
    }
}
