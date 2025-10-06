<?php

namespace AppBundle\Sylius\Product;

use AppBundle\Entity\Delivery\PricingRule;
use Sylius\Component\Product\Model\ProductOptionValueInterface as BaseProductOptionValueInterface;

interface ProductOptionValueInterface extends BaseProductOptionValueInterface
{
    public function getPrice(): int;

    public function setPrice(int $price): void;

    public function getPricingRule(): ?PricingRule;

    public function setPricingRule(?PricingRule $pricingRule): void;
}
