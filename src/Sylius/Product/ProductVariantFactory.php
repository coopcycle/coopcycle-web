<?php

namespace AppBundle\Sylius\Product;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Entity\Sylius\ArbitraryPrice;
use AppBundle\Entity\Sylius\PriceInterface;
use AppBundle\Entity\Sylius\PricingRulesBasedPrice;
use AppBundle\Pricing\ProductOptionValueWithQuantity;
use AppBundle\Service\SettingsManager;
use Ramsey\Uuid\Uuid;
use Sylius\Component\Product\Factory\ProductVariantFactoryInterface;
use Sylius\Component\Product\Repository\ProductRepositoryInterface;
use Sylius\Component\Taxation\Repository\TaxCategoryRepositoryInterface;

class ProductVariantFactory implements ProductVariantFactoryInterface
{

    private ProductInterface $product;

    public function __construct(
        private readonly ProductVariantFactoryInterface $factory,
        ProductRepositoryInterface $productRepository,
        private readonly TaxCategoryRepositoryInterface $taxCategoryRepository,
        private readonly SettingsManager $settingsManager
    )
    {
        $this->product = $productRepository->findOneByCode('CPCCL-ODDLVR');
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
    public function createForProduct(\Sylius\Component\Product\Model\ProductInterface $product): \Sylius\Component\Product\Model\ProductVariantInterface
    {
        return $this->factory->createForProduct($product);
    }

    /**
     * @param ProductOptionValueWithQuantity[] $productOptionValues
     */
    public function createWithProductOptions(array $productOptionValues, PricingRuleSet $ruleSet): ProductVariantInterface
    {
        $productVariant = $this->createForOnDemandDelivery();

        $productVariant->setPricingRuleSet($ruleSet);

        foreach ($productOptionValues as $productOptionValue) {
            $productVariant->addOptionValueWithQuantity($productOptionValue->productOptionValue, $productOptionValue->quantity);
        }

        return $productVariant;
    }

    //TODO: merge with the new implementation
    public function createForDelivery(Delivery $delivery, PriceInterface $price): ProductVariantInterface
    {
        $productVariant = $this->createForOnDemandDelivery();

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

        if ($price instanceof ArbitraryPrice) {
            if ($price->getVariantName()) {
                $productVariant->setName($price->getVariantName());
            }
        } else if ($price instanceof PricingRulesBasedPrice) {
            $productVariant->setPricingRuleSet($price->getPricingRuleSet());
        }

        $productVariant->setPrice($price->getValue());

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

    public function createForOnDemandDelivery(): ProductVariantInterface
    {
        /** @var ProductVariantInterface $productVariant */
        $productVariant = $this->createForProduct($this->product);
        $productVariant->setCode('CPCCL-ODDLVR-'.Uuid::uuid4()->toString());

        $productVariant->setName($this->product->getName());

        $subjectToVat = $this->settingsManager->get('subject_to_vat');
        $taxCategory = $this->taxCategoryRepository->findOneBy([
            'code' => $subjectToVat ? 'SERVICE' : 'SERVICE_TAX_EXEMPT'
        ]);
        $productVariant->setTaxCategory($taxCategory);

        $productVariant->setPosition(1);

        return $productVariant;
    }
}
