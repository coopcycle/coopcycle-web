<?php

namespace AppBundle\Entity\Sylius;

use AppBundle\Sylius\Product\ProductInterface;
use AppBundle\Sylius\Product\ProductOptionInterface;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Bundle\ProductBundle\Doctrine\ORM\ProductOptionRepository as BaseRepository;
use Sylius\Component\Locale\Provider\LocaleProviderInterface;
use Sylius\Component\Product\Repository\ProductRepositoryInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;

class ProductOptionRepository extends BaseRepository
{
    const ADDITIVE_PRICING_RULE_OPTION_CODE = 'CPCCL-ODDLVR-DDTV-PR';

    private ProductInterface $onDemandDeliveryProduct;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        ProductRepositoryInterface $productRepository,
        private readonly FactoryInterface $productOptionFactory,
        private readonly LocaleProviderInterface $localeProvider,
    ) {
        parent::__construct($entityManager, $entityManager->getClassMetadata(ProductOption::class));
        $this->onDemandDeliveryProduct = $productRepository->findOneBy(['code' => 'CPCCL-ODDLVR']);
    }

    public function findAdditivePricingRuleProductOption(): ProductOptionInterface
    {
        $productOption = $this->findOneBy(['code' => self::ADDITIVE_PRICING_RULE_OPTION_CODE]);
        if (null !== $productOption) {
            return $productOption;
        }

        /** @var ProductOption $productOption */
        $productOption = $this->productOptionFactory->createNew();

        // Set current locale before setting the name for translatable entities
        $productOption->setCurrentLocale($this->localeProvider->getDefaultLocaleCode());

        // Set basic properties
        $productOption->setCode(self::ADDITIVE_PRICING_RULE_OPTION_CODE);
        $productOption->setName('Additive pricing rule');

        // Set default strategy and additional flag
        $productOption->setStrategy('free');
        $productOption->setAdditional(false);

        $this->onDemandDeliveryProduct->addOption($productOption);

        $this->entityManager->persist($productOption);
        $this->entityManager->flush();

        return $productOption;
    }
}
