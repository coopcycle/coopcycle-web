<?php

namespace AppBundle\Sylius\Product;

use AppBundle\Entity\Delivery;
use AppBundle\Service\SettingsManager;
use Sylius\Component\Product\Model\ProductInterface;
use Sylius\Component\Product\Model\ProductVariantInterface;
use Sylius\Component\Product\Factory\ProductVariantFactoryInterface;
use Sylius\Component\Product\Repository\ProductRepositoryInterface;
use Sylius\Component\Product\Repository\ProductVariantRepositoryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Sylius\Component\Taxation\Repository\TaxCategoryRepositoryInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ProductVariantFactory implements ProductVariantFactoryInterface
{
    /**
     * @var ProductVariantFactoryInterface
     */
    private $factory;

    private $productRepository;

    private $productVariantRepository;

    private $taxCategoryRepository;

    private $settingsManager;

    private $translator;

    public function __construct(
        ProductVariantFactoryInterface $factory,
        ProductRepositoryInterface $productRepository,
        ProductVariantRepositoryInterface $productVariantRepository,
        TaxCategoryRepositoryInterface $taxCategoryRepository,
        SettingsManager $settingsManager,
        TranslatorInterface $translator)
    {
        $this->factory = $factory;
        $this->productRepository = $productRepository;
        $this->productVariantRepository = $productVariantRepository;
        $this->taxCategoryRepository = $taxCategoryRepository;
        $this->settingsManager = $settingsManager;
        $this->translator = $translator;
    }

    /**
     * {@inheritdoc}
     */
    public function createNew()
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

        $subjectToVat = $this->settingsManager->get('subject_to_vat');

        $taxCategory = $this->taxCategoryRepository->findOneBy([
            'code' => $subjectToVat ? 'SERVICE' : 'SERVICE_TAX_EXEMPT'
        ]);

        $productVariant = $this->createForProduct($product);

        $name = sprintf('%s, %s km',
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
}
