<?php

namespace AppBundle\Pricing;

use AppBundle\Entity\Delivery\PricingRule;
use AppBundle\Entity\Sylius\ProductOptionValue;
use AppBundle\Sylius\Product\ProductOptionValueFactory;

class ProductOptionValueHelper
{
    public function __construct(
        private readonly ProductOptionValueFactory $productOptionValueFactory,
        private readonly RuleHumanizer $ruleHumanizer,
    ) {
    }

    public function getProductOptionValue(
        PricingRule $rule,
    ): ProductOptionValue {
        //TODO: handle multiple product option values
        $productOptionValue = $rule->getProductOptionValues()->first();

        // Create a product option if none is defined
        if (false === $productOptionValue) {
            $productOptionValue = $this->productOptionValueFactory->createForPricingRule(
                $rule,
                $this->ruleHumanizer->humanize($rule)
            );
        }

        // Generate a default name if none is defined
        if (is_null($productOptionValue->getName()) || '' === trim(
                $productOptionValue->getName()
            )) {
            $name = $this->ruleHumanizer->humanize($rule);
            $productOptionValue->setValue($name);
        }

        return $productOptionValue;
    }
}
