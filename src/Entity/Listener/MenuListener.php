<?php

namespace AppBundle\Entity\Listener;

use AppBundle\Entity\Sylius\Taxon;
use AppBundle\Entity\Sylius\TaxonRepository;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::preRemove, entity: Taxon::class)]
class MenuListener
{
    public function __construct(private TaxonRepository $taxonRepository)
    {}

    public function preRemove(Taxon $entity, PreRemoveEventArgs $event): void
    {
        if (!$entity->isRoot()) {
            return;
        }

        $restaurant = $this->taxonRepository->getRestaurantForMenu($entity);

        $restaurant->removeTaxon($entity);
    }
}
