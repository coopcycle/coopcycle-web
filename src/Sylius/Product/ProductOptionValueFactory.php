<?php

namespace AppBundle\Sylius\Product;

use AppBundle\Entity\Delivery\PricingRule;
use Sylius\Component\Locale\Provider\LocaleProviderInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class ProductOptionValueFactory
{

    public function __construct(
        private readonly FactoryInterface $decorated,
        private readonly LocaleProviderInterface $localeProvider
    )
    {
    }

    public function createForPricingRule(PricingRule $rule, array $expressionLanguageValues, ExpressionLanguage $language = null): ProductOptionValueInterface
    {
        if (null === $language) {
            $language = new ExpressionLanguage();
        }

        $priceExpression = $rule->getPrice();
        $result = $language->evaluate($priceExpression, $expressionLanguageValues);

        //TODO: how would it work after a release but before cooperatives have added names (product options per rules)?
        //TODO: use a fake product option with a name from rule humanizer?

        /** @var ProductOptionValueInterface $productOptionValue */
        $productOptionValue = $this->decorated->createNew();

        // Set current locale before setting the name for translatable entities
        $productOptionValue->setCurrentLocale($this->localeProvider->getDefaultLocaleCode());

        $productOptionValue->setOption($rule->getProductOption());

        //TODO: for percentage process separately inside PriceCalculationVisitor
//        if (str_contains($priceExpression, 'price_percentage')) {
//            return new \AppBundle\Entity\Delivery\ProductOption(
//                $rule,
//                0,
//                $result
//            );
//        } else {
//            return new \AppBundle\Entity\Delivery\ProductOption(
//                $rule,
//                $result,
//            );
//        }

        $productOptionValue->setPrice($result);

        return $productOptionValue;
    }
}
