<?php

namespace AppBundle\Sylius\Product;

use AppBundle\Entity\Delivery\PricingRule;
use AppBundle\Entity\Sylius\ProductOptionRepository;
use AppBundle\Entity\Sylius\ProductOptionValue;
use AppBundle\Pricing\PriceExpressionParser;
use AppBundle\Pricing\PriceExpressions\FixedPriceExpression;
use AppBundle\Pricing\PriceExpressions\PricePercentageExpression;
use AppBundle\Pricing\PriceExpressions\PricePerPackageExpression;
use AppBundle\Pricing\PriceExpressions\PriceRangeExpression;
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

        if ($priceExpression instanceof PricePerPackageExpression && $priceExpression->hasDiscount()) {
            /** @var ProductOptionValue $baseProductOptionValue */
            $baseProductOptionValue = $this->createNew();

            $baseProductOptionValue->setValue($name ?? '');
            $baseProductOptionValue->setPrice($priceExpression->unitPrice);

            $result[] = $baseProductOptionValue;

            /** @var ProductOptionValue $extraProductOptionValue */
            $extraProductOptionValue = $this->createNew();

            $extraProductOptionValue->setValue($name ?? '');
            $extraProductOptionValue->setPrice($priceExpression->discountPrice);

            $result[] = $extraProductOptionValue;
        } else {
            /** @var ProductOptionValue $productOptionValue */
            $productOptionValue = $this->createNew();

            $productOptionValue->setValue($name ?? '');
            $productOptionValue->setPrice($this->getUnitPrice($priceExpression));

            $result[] = $productOptionValue;
        }

        foreach ($result as $productOptionValue) {
            $productOption->addValue($productOptionValue);
            $pricingRule->addProductOptionValue($productOptionValue);
        }

        return $result;
    }

    private function determinePricingType(PriceExpression $priceExpression): string
    {
        switch (get_class($priceExpression)) {
            case PricePercentageExpression::class:
                return ProductOptionRepository::PRODUCT_OPTION_CODE_PRICE_PERCENTAGE;
            case PriceRangeExpression::class:
                return ProductOptionRepository::PRODUCT_OPTION_CODE_PRICE_RANGE;
            case PricePerPackageExpression::class:
                return ProductOptionRepository::PRODUCT_OPTION_CODE_PRICE_PER_PACKAGE;
        }

        // Default to fixed price for numeric values or other expressions
        return ProductOptionRepository::PRODUCT_OPTION_CODE_FIXED_PRICE;
    }

    private function getUnitPrice(PriceExpression $priceExpression): int
    {
        switch (get_class($priceExpression)) {
            case FixedPriceExpression::class:
                return $priceExpression->value;
            case PricePercentageExpression::class:
                // For percentage-based pricing, we set unit price to 1 cent and quantity to the actual price, so that the total is price * quantity
                // If the percentage is below 100% (10000 = 100.00%), we set the base price to -1 as it's a discount
                return $priceExpression->percentage < 10000 ? -1 : 1;
            case PriceRangeExpression::class:
                return $priceExpression->price;
            case PricePerPackageExpression::class:
                return $priceExpression->unitPrice;
        }

        // Default to 1 for unparsable expressions
        // So that a unit price is set to 1 cent and quantity to the actual price, the total is price * quantity
        return 1;
    }
}
