<?php

namespace AppBundle\Sylius\Product;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Sylius\ArbitraryPrice;
use AppBundle\Entity\Sylius\PriceInterface;
use AppBundle\Service\SettingsManager;
use Ramsey\Uuid\Uuid;
use Sylius\Component\Product\Model\ProductInterface;
use Sylius\Component\Product\Model\ProductVariantInterface;
use Sylius\Component\Product\Factory\ProductVariantFactoryInterface;
use Sylius\Component\Product\Repository\ProductRepositoryInterface;
use Sylius\Component\Product\Repository\ProductVariantRepositoryInterface;
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

    public function createForDelivery(Delivery $delivery, PriceInterface $price): ProductVariantInterface
    {
        $product = $this->productRepository->findOneByCode('CPCCL-ODDLVR');

        $subjectToVat = $this->settingsManager->get('subject_to_vat');

        $taxCategory = $this->taxCategoryRepository->findOneBy([
            'code' => $subjectToVat ? 'SERVICE' : 'SERVICE_TAX_EXEMPT'
        ]);

        $productVariant = $this->createForProduct($product);

        $nameParts = [];

        foreach ($delivery->getTasks() as $task) {
            $nameParts[] = sprintf('%s: %s',
                $this->translator->trans(sprintf('task.type.%s', $task->getType())),
                $task->getAddress()->getName());
        }

        $nameParts[] = sprintf('%s, %s km',
            $this->translator->trans(sprintf('vehicle.%s', $delivery->getVehicle())),
            (string) number_format($delivery->getDistance() / 1000, 2)
        );

        //TODO: add weight and packages

        $name = implode(' - ', $nameParts);

        $productVariant->setName($name);

        if ($price instanceof ArbitraryPrice) {
            if ($price->getVariantName()) {
                $productVariant->setName($price->getVariantName());
            }
            $productVariant->setCode('RBTRR-PRC-'.Uuid::uuid4()->toString());
        } else {
            $productVariant->setCode(Uuid::uuid4()->toString());
        }

        $productVariant->setPosition(1);
        $productVariant->setPrice($price->getValue());
        $productVariant->setTaxCategory($taxCategory);

        return $productVariant;
    }
}
