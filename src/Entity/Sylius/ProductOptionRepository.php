<?php

namespace AppBundle\Entity\Sylius;

use AppBundle\Sylius\Product\ProductOptionInterface;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Bundle\ProductBundle\Doctrine\ORM\ProductOptionRepository as BaseRepository;
use Sylius\Component\Locale\Provider\LocaleProviderInterface;
use Sylius\Component\Product\Repository\ProductRepositoryInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;

class ProductOptionRepository extends BaseRepository
{

    //TODO: figure out how to inject properly
    public function findPricingRuleProductOption(
        EntityManagerInterface $entityManager,
        ProductRepositoryInterface $productRepository,
        FactoryInterface $productOptionFactory,
        LocaleProviderInterface $localeProvider
    ): ProductOptionInterface {
        /** @var Product $product */
        $product = $productRepository->findOnDemandDeliveryProduct();

        $existingOptions = $product->getOptions();
        if (!$existingOptions->isEmpty()) {
            // Return the first option (there should only be one for pricing rules)
            return $existingOptions->first();
        }

        /** @var ProductOption $productOption */
        $productOption = $productOptionFactory->createNew();

        // Set current locale before setting the name for translatable entities
        $productOption->setCurrentLocale($localeProvider->getDefaultLocaleCode());

        // Set basic properties
        $productOption->setCode('CPCCL-ODDLVR-PR');
        $productOption->setName('Pricing Rules');

        // Set default strategy and additional flag
        $productOption->setStrategy('free');
        $productOption->setAdditional(false);

        // Associate the ProductOption with the CPCCL-ODDLVR product
        $product->addOption($productOption);

        // Persist the new ProductOption
        $entityManager->persist($productOption);
        $entityManager->flush();

        return $productOption;
    }
}
