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

    public function __construct(
        private readonly ProductVariantFactoryInterface $factory,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly TaxCategoryRepositoryInterface $taxCategoryRepository,
        private readonly SettingsManager $settingsManager
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
    public function createForProduct(\Sylius\Component\Product\Model\ProductInterface $product): \Sylius\Component\Product\Model\ProductVariantInterface
    {
        return $this->factory->createForProduct($product);
    }

    /**
     * @param ProductOptionValueWithQuantity[] $productOptionValues
     */
    public function createWithProductOptions(string $name, array $productOptionValues, PricingRuleSet $ruleSet): ProductVariantInterface
    {
        $productVariant = $this->createForOnDemandDelivery();

        $productVariant->setPricingRuleSet($ruleSet);

        $productVariant->setName($name);

        foreach ($productOptionValues as $productOptionValue) {
            $productVariant->addOptionValueWithQuantity($productOptionValue->productOptionValue, $productOptionValue->quantity);
        }

        return $productVariant;
    }

    public function createWithPrice(Delivery $delivery, PriceInterface $price): ProductVariantInterface
    {
        $productVariant = $this->createForOnDemandDelivery();

        $productVariant->setName($this->generateLegacyProductVariantName($delivery));

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

    public function generateLegacyProductVariantName(Delivery $delivery): string {
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

        return implode(' - ', $nameParts);
    }

    private function metersToKilometers($meters)
    {
        return sprintf('%s km', number_format($meters / 1000, 2));
    }

    private function gramsToKilos($grams)
    {
        return sprintf('%s kg', number_format($grams / 1000, 2));
    }

    private function createForOnDemandDelivery(): ProductVariantInterface
    {
        /** @var ProductInterface $product */
        $product = $this->productRepository->findOneBy(['code' => 'CPCCL-ODDLVR']);

        /** @var ProductVariantInterface $productVariant */
        $productVariant = $this->createForProduct($product);
        $productVariant->setCode('CPCCL-ODDLVR-'.Uuid::uuid4()->toString());

        $subjectToVat = $this->settingsManager->get('subject_to_vat');
        $taxCategory = $this->taxCategoryRepository->findOneBy([
            'code' => $subjectToVat ? 'SERVICE' : 'SERVICE_TAX_EXEMPT'
        ]);
        $productVariant->setTaxCategory($taxCategory);

        $productVariant->setPosition(1);

        return $productVariant;
    }
}
