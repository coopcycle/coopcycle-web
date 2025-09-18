<?php

namespace AppBundle\Sylius\Product;

use AppBundle\Entity\Delivery\PricingRule;
use AppBundle\Entity\Sylius\ProductOptionRepository;
use AppBundle\Entity\Sylius\ProductOptionValue;
use AppBundle\Pricing\PriceExpressionParser;
use AppBundle\Pricing\PriceExpressions\FixedPriceExpression;
use AppBundle\Pricing\PriceExpressions\PercentagePriceExpression;
use AppBundle\Pricing\PriceExpressions\PerPackagePriceExpression;
use AppBundle\Pricing\PriceExpressions\PerRangePriceExpression;
use AppBundle\Pricing\PriceExpressions\PriceExpression;
use Ramsey\Uuid\Uuid;
use Sylius\Component\Resource\Factory\FactoryInterface;

class ProductOptionValueFactory
{
    public function __construct(
        private readonly FactoryInterface $decorated,
        private readonly PriceExpressionParser $priceExpressionParser,
        private readonly ProductOptionRepository $productOptionRepository,
    ) {
    }

    public function createNew()
    {
        $productOptionValue = $this->decorated->createNew();
        $productOptionValue->setCode(Uuid::uuid4()->toString());
        return $productOptionValue;
    }

    /**
     * @return ProductOptionValue[]
     */
    public function createForPricingRule(PricingRule $pricingRule, ?string $name): array {
        $result = [];

        $priceExpression = $this->priceExpressionParser->parsePrice($pricingRule->getPrice());

        $pricingType = $this->determinePricingType($priceExpression);
        $productOption = $this->productOptionRepository->findPricingRuleProductOptionByCode($pricingType);

        if ($priceExpression instanceof PerPackagePriceExpression && $priceExpression->hasDiscount()) {
            /** @var ProductOptionValue $baseProductOptionValue */
            $baseProductOptionValue = $this->createNew();

            $baseProductOptionValue->setValue($name ?? '');
            $baseProductOptionValue->setPrice($priceExpression->unitPrice);

            $productOption->addValue($baseProductOptionValue);
            $result[] = $baseProductOptionValue;

            /** @var ProductOptionValue $extraProductOptionValue */
            $extraProductOptionValue = $this->createNew();

            $extraProductOptionValue->setValue($name ?? '');
            $extraProductOptionValue->setPrice($priceExpression->discountPrice);

            $productOption->addValue($extraProductOptionValue);
            $result[] = $extraProductOptionValue;
        } else {
            /** @var ProductOptionValue $productOptionValue */
            $productOptionValue = $this->createNew();

            $productOptionValue->setValue($name ?? '');
            $productOptionValue->setPrice($this->getUnitPrice($priceExpression));

            $productOption->addValue($productOptionValue);
            $result[] = $productOptionValue;
        }

        return $result;
    }

    private function determinePricingType(PriceExpression $priceExpression): string
    {
        switch (get_class($priceExpression)) {
            case PercentagePriceExpression::class:
                return ProductOptionRepository::PRODUCT_OPTION_CODE_PRICING_TYPE_PERCENTAGE;
            case PerRangePriceExpression::class:
                return ProductOptionRepository::PRODUCT_OPTION_CODE_PRICING_TYPE_RANGE;
            case PerPackagePriceExpression::class:
                return ProductOptionRepository::PRODUCT_OPTION_CODE_PRICING_TYPE_PACKAGE;
        }

        // Default to fixed price for numeric values or other expressions
        return ProductOptionRepository::PRODUCT_OPTION_CODE_PRICING_TYPE_FIXED_PRICE;
    }

    private function getUnitPrice(PriceExpression $priceExpression): int
    {
        switch (get_class($priceExpression)) {
            case FixedPriceExpression::class:
                return $priceExpression->value;
            case PercentagePriceExpression::class:
                // For percentage-based pricing, we set unit price to 1 cent and quantity to the actual price, so that the total is price * quantity
                // If the percentage is below 100% (10000 = 100.00%), we set the base price to -1 as it's a discount
                return $priceExpression->percentage < 10000 ? -1 : 1;
            case PerRangePriceExpression::class:
                return $priceExpression->price;
            case PerPackagePriceExpression::class:
                return $priceExpression->unitPrice;
        }

        // Default to 1 for unparsable expressions
        // So that a unit price is set to 1 cent and quantity to the actual price, the total is price * quantity
        return 1;
    }
}
