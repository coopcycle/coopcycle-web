<?php

namespace AppBundle\Sylius\Product;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Sylius\ArbitraryPrice;
use AppBundle\Entity\Sylius\PriceInterface;
use AppBundle\Entity\Sylius\PricingRulesBasedPrice;
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

        if ($delivery->hasPackages()) {
            foreach ($delivery->getPackages() as $packageQuantity) {
                $nameParts[] = sprintf('%d × %s', $packageQuantity->getQuantity(), $packageQuantity->getPackage()->getName());
            }
        }

        if ($delivery->getWeight()) {
            $nameParts[] = $this->gramsToKilos($delivery->getWeight());
        }

        $nameParts[] = $this->metersToKilometers($delivery->getDistance());

        $name = implode(' - ', $nameParts);

        $productVariant->setName($name);

        $productVariant->setCode('CPCCL-ODDLVR-'.Uuid::uuid4()->toString());

        if ($price instanceof ArbitraryPrice) {
            if ($price->getVariantName()) {
                $productVariant->setName($price->getVariantName());
            }
        } else if ($price instanceof PricingRulesBasedPrice) {
            $productVariant->setPricingRuleSet($price->getPricingRuleSet());
        }

        $productVariant->setPosition(1);
        $productVariant->setPrice($price->getValue());
        $productVariant->setTaxCategory($taxCategory);

        return $productVariant;
    }

    private function metersToKilometers($meters)
    {
        return sprintf('%s km', number_format($meters / 1000, 2));
    }

    private function gramsToKilos($grams)
    {
        return sprintf('%s kg', number_format($grams / 1000, 2));
    }
}
