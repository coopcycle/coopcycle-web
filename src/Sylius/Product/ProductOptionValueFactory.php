<?php

namespace AppBundle\Sylius\Product;

use AppBundle\Entity\Delivery\PricingRule;
use AppBundle\Entity\Sylius\ProductOptionRepository;
use AppBundle\Entity\Sylius\ProductOptionValue;
use Ramsey\Uuid\Uuid;
use Sylius\Component\Resource\Factory\FactoryInterface;

class ProductOptionValueFactory
{
    public function __construct(
        private readonly FactoryInterface $decorated,
        private readonly ProductOptionRepository $productOptionRepository,
    ) {
    }

    public function createNew()
    {
        return $this->decorated->createNew();
    }

    public function createForPricingRule(PricingRule $pricingRule, string $name): ProductOptionValue {
        $priceExpression = $pricingRule->getPrice();
        $pricingType = $this->determinePricingType($priceExpression);

        $productOption = $this->productOptionRepository->findPricingRuleProductOptionByType($pricingType);

        /** @var ProductOptionValue $productOptionValue */
        $productOptionValue = $this->createNew();

        $productOptionValue->setCode(Uuid::uuid4()->toString());

        $productOptionValue->setValue($name);

        $productOption->addValue($productOptionValue);

        return $productOptionValue;
    }

    private function determinePricingType(string $priceExpression): string
    {
        if (str_contains($priceExpression, 'price_percentage(')) {
            return ProductOptionRepository::PRICING_TYPE_PERCENTAGE;
        }

        if (str_contains($priceExpression, 'price_range(')) {
            return ProductOptionRepository::PRICING_TYPE_RANGE;
        }

        if (str_contains($priceExpression, 'price_per_package(')) {
            return ProductOptionRepository::PRICING_TYPE_PACKAGE;
        }

        // Default to fixed price for numeric values or other expressions
        return ProductOptionRepository::PRICING_TYPE_FIXED_PRICE;
    }

}
