<?php

namespace AppBundle\Service;

use AppBundle\Entity\DeliveryForm;
use AppBundle\Entity\PackageSet;
use AppBundle\Entity\Store;
use Doctrine\ORM\EntityManagerInterface;


class PackageSetManager
{
    public function __construct(private EntityManagerInterface $entityManager) {}

    /**
     * @return mixed[]
     */
    public function getPackageSetApplications(PackageSet $packageSet) {
        /*
            Returns an array of entities related to the packageSet.
        */

        $this->entityManager->getFilters()->enable('soft_deleteable');

        $array = array_merge(
            $this->getStores($packageSet),
            $this->getDeliveryForms($packageSet)
        );

        $this->entityManager->getFilters()->disable('soft_deleteable');

        return $array;

    }

    /**
     * @return Store[]
     */
    public function getStores(PackageSet $packageSet) {
        return $this->entityManager->getRepository(Store::class)->findBy(['packageSet' => $packageSet]);
    }

    /**
     * @return DeliveryForm[]
     */
    public function getDeliveryForms(PackageSet $packageSet) {
        return $this->entityManager->getRepository(DeliveryForm::class)->findBy(['packageSet' => $packageSet]);
    }
}