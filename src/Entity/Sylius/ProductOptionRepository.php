<?php

namespace AppBundle\Entity\Sylius;

use AppBundle\Sylius\Product\ProductOptionInterface;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Bundle\ProductBundle\Doctrine\ORM\ProductOptionRepository as BaseRepository;
use Sylius\Component\Locale\Provider\LocaleProviderInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;

class ProductOptionRepository extends BaseRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProductRepository $productRepository,
        private readonly FactoryInterface $productOptionFactory,
        private readonly LocaleProviderInterface $localeProvider
    ) {
        parent::__construct($entityManager, $entityManager->getClassMetadata(ProductOption::class));
    }

    public function findPricingRuleProductOption(): ProductOptionInterface
    {
        /** @var Product $product */
        $product = $this->productRepository->findOnDemandDeliveryProduct();

        $existingOptions = $product->getOptions();
        if (!$existingOptions->isEmpty()) {
            // Return the first option (there should only be one for pricing rules)
            /** @var ProductOptionInterface $firstOption */
            $firstOption = $existingOptions->first();
            return $firstOption;
        }

        /** @var ProductOption $productOption */
        $productOption = $this->productOptionFactory->createNew();

        // Set current locale before setting the name for translatable entities
        $productOption->setCurrentLocale($this->localeProvider->getDefaultLocaleCode());

        // Set basic properties
        $productOption->setCode('CPCCL-ODDLVR-PR');
        $productOption->setName('Pricing Rules');

        // Set default strategy and additional flag
        $productOption->setStrategy('free');
        $productOption->setAdditional(false);

        // Associate the ProductOption with the CPCCL-ODDLVR product
        $product->addOption($productOption);

        // Persist the new ProductOption
        $this->entityManager->persist($productOption);
        $this->entityManager->flush();

        return $productOption;
    }
}
