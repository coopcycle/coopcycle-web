<?php

namespace AppBundle\Entity\Sylius;

use AppBundle\Entity\Menu\MenuItem;
use Sylius\Bundle\ProductBundle\Doctrine\ORM\ProductVariantRepository as BaseProductVariantRepository;

class ProductVariantRepository extends BaseProductVariantRepository
{
    public function findOneByMenuItem(MenuItem $menuItem)
    {
        return $this->findOneByCode(sprintf('CPCCL-FDTCH-%d-001', $menuItem->getId()));
    }
}
