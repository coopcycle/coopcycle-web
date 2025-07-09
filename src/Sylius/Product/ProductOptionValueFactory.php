<?php

namespace AppBundle\Sylius\Product;

use AppBundle\Entity\Delivery\PricingRule;
use AppBundle\Entity\Sylius\ProductOptionRepository;
use AppBundle\Entity\Sylius\ProductOptionValue;
use Ramsey\Uuid\Uuid;
use Sylius\Component\Locale\Provider\LocaleProviderInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;

class ProductOptionValueFactory
{
    public function __construct(
        private readonly FactoryInterface $decorated,
        private readonly ProductOptionRepository $productOptionRepository,
        private readonly LocaleProviderInterface $localeProvider
    ) {
    }

    public function createNew()
    {
        return $this->decorated->createNew();
    }

    public function createForPricingRule(PricingRule $pricingRule, string $name): ProductOptionValue {
        $priceExpression = $pricingRule->getPrice();

        $productOption = $this->productOptionRepository->findPricingRuleProductOption();

        /** @var ProductOptionValue $productOptionValue */
        $productOptionValue = $this->createNew();

        // Set current locale before setting the value for translatable entities
        $productOptionValue->setCurrentLocale($this->localeProvider->getDefaultLocaleCode());

        //FIXME: a workaround to distinguish between percentage-based and 'static' product option values
        // until we have a dedicated ProductOption type for percentage-based rules
        if (str_contains($priceExpression, 'price_percentage')) {
            $productOptionValue->setCode('PERCENTAGE-' . Uuid::uuid4()->toString());
        } else {
            $productOptionValue->setCode(Uuid::uuid4()->toString());
        }

        $productOptionValue->setValue($name);

        $productOptionValue->setOption($productOption);

        return $productOptionValue;
    }
}
