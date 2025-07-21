<?php

namespace AppBundle\Pricing;

use AppBundle\Entity\Delivery\PricingRule;
use AppBundle\Sylius\Product\ProductVariantInterface;

class PriceCalculationOutput
{
    /**
     * @param PricingRule[] $matchedRules
     * @param ProductVariantInterface[] $productVariants
     */
    public function __construct(
        public readonly ?Calculation $calculation,
        public readonly array $matchedRules,
        public readonly array $productVariants,
    ) {
    }

    public function getPrice(): ?int
    {
        if (null === $this->calculation || count($this->productVariants) === 0) {
            return null;
        }

        return array_reduce(
            $this->productVariants,
            fn(int $carry, ProductVariantInterface $item) => $carry + $item->getOptionValuesPrice(),
            0
        );
    }
}
