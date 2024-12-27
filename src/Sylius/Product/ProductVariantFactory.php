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
use Sylius\Component\Taxation\Repository\TaxCategoryRepositoryInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ProductVariantFactory implements ProductVariantFactoryInterface
{

    public function __construct(
        private readonly ProductVariantFactoryInterface $factory,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly TaxCategoryRepositoryInterface $taxCategoryRepository,
        private readonly SettingsManager $settingsManager,
        private readonly TranslatorInterface $translator
    )
    {
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
