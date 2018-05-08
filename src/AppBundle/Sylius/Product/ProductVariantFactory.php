<?php

namespace AppBundle\Sylius\Product;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Menu\MenuItem;
use AppBundle\Service\SettingsManager;
use Sylius\Component\Product\Model\ProductInterface;
use Sylius\Component\Product\Model\ProductVariantInterface;
use Sylius\Component\Product\Factory\ProductVariantFactoryInterface;
use Sylius\Component\Product\Repository\ProductOptionRepositoryInterface;
use Sylius\Component\Product\Repository\ProductRepositoryInterface;
use Sylius\Component\Product\Repository\ProductVariantRepositoryInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Sylius\Component\Taxation\Repository\TaxCategoryRepositoryInterface;
use Symfony\Component\Translation\TranslatorInterface;

class ProductVariantFactory implements ProductVariantFactoryInterface
{
    /**
     * @var ProductVariantFactoryInterface
     */
    private $factory;

    private $productRepository;

    private $productVariantRepository;

    private $taxCategoryRepository;

    private $productOptionRepository;

    private $productOptionValueRepository;

    private $settingsManager;

    private $translator;

    /**
     * @param ProductVariantFactoryInterface $factory
     */
    public function __construct(
        ProductVariantFactoryInterface $factory,
        ProductRepositoryInterface $productRepository,
        ProductVariantRepositoryInterface $productVariantRepository,
        TaxCategoryRepositoryInterface $taxCategoryRepository,
        ProductOptionRepositoryInterface $productOptionRepository,
        RepositoryInterface $productOptionValueRepository,
        FactoryInterface $productOptionFactory,
        FactoryInterface $productOptionValueFactory,
        SettingsManager $settingsManager,
        TranslatorInterface $translator)
    {
        $this->factory = $factory;

        $this->productRepository = $productRepository;
        $this->productVariantRepository = $productVariantRepository;
        $this->taxCategoryRepository = $taxCategoryRepository;
        $this->productOptionRepository = $productOptionRepository;
        $this->productOptionValueRepository = $productOptionValueRepository;

        $this->productOptionFactory = $productOptionFactory;
        $this->productOptionValueFactory = $productOptionValueFactory;

        $this->settingsManager = $settingsManager;
        $this->translator = $translator;
    }

    /**
     * {@inheritdoc}
     */
    public function createNew(): ProductVariantInterface
    {
        return $this->factory->createNew();
    }

    /**
     * {@inheritdoc}
     */
    public function createForProduct(ProductInterface $product): ProductVariantInterface
    {
        return $this->factory->createForProduct($product);
    }

    /**
     * @param Delivery $delivery
     * @param int $price
     */
    public function createForDelivery(Delivery $delivery, int $price): ProductVariantInterface
    {
        $hash = sprintf('%s-%d-%d', $delivery->getVehicle(), $delivery->getDistance(), $price);
        $code = sprintf('CPCCL-ODDLVR-%s', strtoupper(substr(sha1($hash), 0, 7)));

        if ($productVariant = $this->productVariantRepository->findOneByCode($code)) {

            return $productVariant;
        }

        $product = $this->productRepository->findOneByCode('CPCCL-ODDLVR');

        $taxCategory = $this->taxCategoryRepository->findOneBy([
            'code' => $this->settingsManager->get('default_tax_category')
        ]);

        $productVariant = $this->createForProduct($product);

        $name = sprintf('Livraison %s, %s km',
            $this->translator->trans(sprintf('vehicle.%s', $delivery->getVehicle())),
            (string) number_format($delivery->getDistance() / 1000, 2)
        );

        $productVariant->setName($name);
        $productVariant->setPosition(1);

        $productVariant->setPrice($price);
        $productVariant->setTaxCategory($taxCategory);
        $productVariant->setCode($code);

        return $productVariant;
    }

    public function createForMenuItem(MenuItem $menuItem): ProductVariantInterface
    {
        $productVariant = $this->createNew();

        $code = sprintf('CPCCL-FDTCH-%d-001', $menuItem->getId());

        $productVariant->setCode($code);
        $productVariant->setName($menuItem->getName());
        $productVariant->setPrice((int) ($menuItem->getPrice() * 100));
        $productVariant->setTaxCategory($menuItem->getTaxCategory());
        $productVariant->setPosition(1);

        return $productVariant;
    }

    public function createForMenuItemWithModifiers(MenuItem $menuItem, array $modifiers): ProductVariantInterface
    {
        $product = $this->productRepository->findOneByCode(sprintf('CPCCL-FDTCH-%d', $menuItem->getId()));
        $productVariant = $this->createForProduct($product);

        $price = (int) ($menuItem->getPrice() * 100);
        foreach ($modifiers as $modifier) {

            $menuItemModifier = $modifier->getMenuItemModifier();

            $optionCode = sprintf('CPCCL-FDTCH-%d-OPT-%d', $menuItem->getId(), $menuItemModifier->getId());
            $optionValueCode = sprintf('%s-%d', $optionCode, $modifier->getId());

            $option = $this->productOptionRepository->findOneByCode($optionCode);
            if (!$option) {
                $option = $this->productOptionFactory->createNew();
                $option->setName($menuItemModifier->getName());
                $option->setCode($optionCode);
                $option->setPosition(1);

                $product->addOption($option);
            }

            $optionValue = $this->productOptionValueRepository->findOneByCode($optionValueCode);
            if (!$optionValue) {
                $optionValue = $this->productOptionValueFactory->createNew();
                $optionValue->setCode($optionValueCode);
                $optionValue->setValue($modifier->getName());
            }

            $option->addValue($optionValue);
            $productVariant->addOptionValue($optionValue);

            $price += (int) ($menuItemModifier->getModifierPrice($modifier) * 100);
        }

        $modifiersIds = array_map(function ($modifier) {
            return $modifier->getId();
        }, $modifiers);
        sort($modifiersIds);

        $code = sprintf('CPCCL-FDTCH-%d-MOD-%s', $menuItem->getId(), implode('-', $modifiersIds));

        $productVariant->setCode($code);
        $productVariant->setName($menuItem->getName());
        $productVariant->setPrice($price);
        $productVariant->setTaxCategory($menuItem->getTaxCategory());
        $productVariant->setPosition(1);

        return $productVariant;
    }
}
