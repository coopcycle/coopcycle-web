<?php

namespace AppBundle\Entity;

use Doctrine\ORM\EntityRepository;


class PackageRepository extends EntityRepository
{
    public function findOneByNameAndStore(string $name, null|Store $store)
    {   
        if (!is_null($store)) {
            return $this->findOneBy([
                'name' => $name,
                'packageSet' => $store->getPackageSet()
            ]);
        } else {
            return $this->findOneBy(
                ['name' => $name]
            );
        }
    }
}
