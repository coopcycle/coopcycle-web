<?php

namespace AppBundle\Sylius\Product;

use AppBundle\Entity\Delivery\PricingRule;
use AppBundle\Pricing\RuleHumanizer;
use Ramsey\Uuid\Uuid;
use Sylius\Component\Locale\Provider\LocaleProviderInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class ProductOptionValueFactory
{
    public function __construct(
        private readonly FactoryInterface $decorated,
        private readonly ProductOptionFactory $productOptionFactory,
        private readonly RuleHumanizer $ruleHumanizer,
        private readonly LocaleProviderInterface $localeProvider
    ) {
    }

    public function createNew()
    {
        return $this->decorated->createNew();
    }

    //TODO: FIX
    public function createForPricingRule(
        PricingRule $rule,
        array $expressionLanguageValues,
        ExpressionLanguage $language = null
    ): ProductOptionValueInterface {
        if (null === $language) {
            $language = new ExpressionLanguage();
        }

        $priceExpression = $rule->getPrice();
        $result = $language->evaluate($priceExpression, $expressionLanguageValues);

        $productOption = $rule->getProductOption();

        // Create a product option if none is defined
        if (is_null($productOption)) {
            $productOption = $this->productOptionFactory->createForOnDemandDelivery("");
        }

        // Generate a default name if none is defined
        if (is_null($productOption->getName()) || '' === trim($productOption->getName())) {
            $name = $this->ruleHumanizer->humanize($rule);
            $productOption->setName($name);
        }

        /** @var ProductOptionValueInterface $productOptionValue */
        $productOptionValue = $this->decorated->createNew();

        // Set current locale before setting the name for translatable entities
        $productOptionValue->setCurrentLocale($this->localeProvider->getDefaultLocaleCode());

        $productOptionValue->setCode(Uuid::uuid4()->toString());

        $productOptionValue->setValue($priceExpression);
        $productOptionValue->setPrice($result);

        $productOption->addValue($productOptionValue);

        return $productOptionValue;
    }
}
}
