<?php

namespace AppBundle\Sylius\Product;

use AppBundle\Entity\Delivery\PricingRule;
use AppBundle\Entity\Sylius\ProductOption;
use AppBundle\Entity\Sylius\ProductOptionRepository;
use AppBundle\Entity\Sylius\ProductOptionValue;
use AppBundle\Pricing\RuleHumanizer;
use Ramsey\Uuid\Uuid;
use Sylius\Component\Locale\Provider\LocaleProviderInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class ProductOptionValueFactory
{
    public function __construct(
        private readonly FactoryInterface $decorated,
//        private readonly ProductOptionFactory $productOptionFactory,
//        private readonly RuleHumanizer $ruleHumanizer,
        private readonly ProductOptionRepository $productOptionRepository,
        private readonly LocaleProviderInterface $localeProvider
    ) {
    }

    public function createNew()
    {
        return $this->decorated->createNew();
    }

    public function createForPricingRule(string $name): ProductOptionValue {
        $productOption = $this->productOptionRepository->findAdditivePricingRuleProductOption();

        /** @var ProductOptionValue $productOptionValue */
        $productOptionValue = $this->createNew();

        // Set current locale before setting the value for translatable entities
        $productOptionValue->setCurrentLocale($this->localeProvider->getDefaultLocaleCode());

        $productOptionValue->setCode(Uuid::uuid4()->toString());
        $productOptionValue->setValue($name);
        $productOptionValue->setOption($productOption);

        //TODO: set price

        //TODO: use a different option type for percentage

        return $productOptionValue;
    }

}
