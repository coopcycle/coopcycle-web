<?php

namespace AppBundle\Entity\Sylius;

use AppBundle\Entity\Menu\MenuItem;
use Doctrine\ORM\Query\Expr;
use Sylius\Bundle\ProductBundle\Doctrine\ORM\ProductVariantRepository as BaseProductVariantRepository;
use Sylius\Component\Product\Model\Product;
use Sylius\Component\Product\Model\ProductOptionValue;

class ProductVariantRepository extends BaseProductVariantRepository
{
    public function findOneByMenuItem(MenuItem $menuItem)
    {
        return $this->findOneByCode(sprintf('CPCCL-FDTCH-%d-001', $menuItem->getId()));
    }

    public function findOneByMenuItemWithModifiers(MenuItem $menuItem, array $modifiers)
    {
        $modifiersIds = array_map(function ($modifier) {
            return $modifier->getId();
        }, $modifiers);

        sort($modifiersIds);

        $code = sprintf('CPCCL-FDTCH-%d-MOD-%s', $menuItem->getId(), implode('-', $modifiersIds));

        return $this->findOneByCode($code);
    }
}
