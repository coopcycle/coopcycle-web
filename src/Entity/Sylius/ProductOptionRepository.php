<?php

namespace AppBundle\Entity\Sylius;

use AppBundle\Sylius\Product\ProductOptionInterface;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Bundle\ProductBundle\Doctrine\ORM\ProductOptionRepository as BaseRepository;
use Sylius\Component\Locale\Provider\LocaleProviderInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;

class ProductOptionRepository extends BaseRepository
{
    public const PRODUCT_OPTION_CODE_PRICING_TYPE_FIXED_PRICE = 'CPCCL-ODDLVR-FIXED';
    public const PRODUCT_OPTION_CODE_PRICING_TYPE_PERCENTAGE = 'CPCCL-ODDLVR-PERCENTAGE';
    public const PRODUCT_OPTION_CODE_PRICING_TYPE_RANGE = 'CPCCL-ODDLVR-RANGE';
    public const PRODUCT_OPTION_CODE_PRICING_TYPE_PACKAGE = 'CPCCL-ODDLVR-PACKAGE';

    private EntityManagerInterface $entityManager;
    private ProductRepository $productRepository;
    private FactoryInterface $productOptionFactory;
    private LocaleProviderInterface $localeProvider;

    // As this class is created by Doctrine's ContainerRepositoryFactory we can't modify its constructor
    // and have to inject dependencies through setters

    public function setEntityManager(EntityManagerInterface $entityManager): void
    {
        $this->entityManager = $entityManager;
    }

    public function setProductRepository(ProductRepository $productRepository): void
    {
        $this->productRepository = $productRepository;
    }

    public function setProductOptionFactory(FactoryInterface $productOptionFactory): void
    {
        $this->productOptionFactory = $productOptionFactory;
    }

    public function setLocaleProvider(LocaleProviderInterface $localeProvider): void
    {
        $this->localeProvider = $localeProvider;
    }

    public function findPricingRuleProductOptionByCode(string $code): ProductOptionInterface
    {
        $onDemandDeliveryProduct = $this->productRepository->findOneBy(['code' => 'CPCCL-ODDLVR']);

        $typeConfig = $this->getPricingTypeConfig($code);

        // Look for existing option with the specific code for this type
        $existingOptions = $onDemandDeliveryProduct->getOptions();
        foreach ($existingOptions as $option) {
            if ($option->getCode() === $typeConfig['code']) {
                return $option;
            }
        }

        /** @var ProductOption $productOption */
        $productOption = $this->productOptionFactory->createNew();

        // Set current locale before setting the name for translatable entities
        $productOption->setCurrentLocale($this->localeProvider->getDefaultLocaleCode());

        $productOption->setCode($typeConfig['code']);
        $productOption->setName($typeConfig['name']);

        $productOption->setStrategy(ProductOptionInterface::STRATEGY_OPTION_VALUE);
        $productOption->setAdditional(true);

        $onDemandDeliveryProduct->addOption($productOption);

        $this->entityManager->persist($productOption);
        $this->entityManager->flush();

        return $productOption;
    }

    private function getPricingTypeConfig(string $productOptionCode): array
    {
        $configs = [
            self::PRODUCT_OPTION_CODE_PRICING_TYPE_FIXED_PRICE => [
                'code' => self::PRODUCT_OPTION_CODE_PRICING_TYPE_FIXED_PRICE,
                'name' => 'Fixed Price'
            ],
            self::PRODUCT_OPTION_CODE_PRICING_TYPE_PERCENTAGE => [
                'code' => self::PRODUCT_OPTION_CODE_PRICING_TYPE_PERCENTAGE,
                'name' => 'Percentage Price'
            ],
            self::PRODUCT_OPTION_CODE_PRICING_TYPE_RANGE => [
                'code' => self::PRODUCT_OPTION_CODE_PRICING_TYPE_RANGE,
                'name' => 'Range Price'
            ],
            self::PRODUCT_OPTION_CODE_PRICING_TYPE_PACKAGE => [
                'code' => self::PRODUCT_OPTION_CODE_PRICING_TYPE_PACKAGE,
                'name' => 'Package Price'
            ]
        ];

        if (!isset($configs[$productOptionCode])) {
            throw new \InvalidArgumentException(sprintf('Unknown pricing type: %s', $productOptionCode));
        }

        return $configs[$productOptionCode];
    }
}
