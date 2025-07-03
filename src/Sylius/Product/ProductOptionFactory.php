<?php

namespace AppBundle\Sylius\Product;

use AppBundle\Entity\Sylius\ProductOption;
use Ramsey\Uuid\Uuid;
use Sylius\Component\Locale\Provider\LocaleProviderInterface;
use Sylius\Component\Product\Repository\ProductRepositoryInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;

class ProductOptionFactory
{
    private ProductInterface $product;

    public function __construct(
        ProductRepositoryInterface $productRepository,
        private readonly FactoryInterface $productOptionFactory,
        private readonly LocaleProviderInterface $localeProvider,
    ) {
        $this->product = $productRepository->findOneByCode('CPCCL-ODDLVR');
    }

    public function createForOnDemandDelivery(string $name): ProductOption
    {
        /** @var ProductOption $productOption */
        $productOption = $this->productOptionFactory->createNew();

        // Set current locale before setting the name for translatable entities
        $productOption->setCurrentLocale($this->localeProvider->getDefaultLocaleCode());

        // Set basic properties
        $productOption->setCode(Uuid::uuid4()->toString());
        $productOption->setName($name);

        // Set default strategy and additional flag
        $productOption->setStrategy('free');
        $productOption->setAdditional(false);

        $this->product->addOption($productOption);

        return $productOption;
    }
}
