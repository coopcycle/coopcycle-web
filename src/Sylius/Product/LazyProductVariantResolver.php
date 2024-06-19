<?php

namespace AppBundle\Sylius\Product;

use AppBundle\Business\Context as BusinessContext;
use AppBundle\Entity\BusinessRestaurantGroup;
use AppBundle\Entity\Sylius\ProductVariantOptionValue;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use Ramsey\Uuid\Uuid;
use Sylius\Component\Product\Factory\ProductVariantFactoryInterface;
use Sylius\Component\Product\Model\ProductInterface;
use Sylius\Component\Product\Model\ProductOptionInterface;
use Sylius\Component\Product\Model\ProductOptionValueInterface;
use Sylius\Component\Product\Model\ProductVariantInterface;
use Sylius\Component\Product\Resolver\ProductVariantResolverInterface;

class LazyProductVariantResolver implements LazyProductVariantResolverInterface
{
    private $variantResolver;
    private $variantFactory;
    private $businessContext;
    private $entityManager;

    public function __construct(
        ProductVariantResolverInterface $variantResolver,
        ProductVariantFactoryInterface $variantFactory,
        BusinessContext $businessContext,
        EntityManagerInterface $entityManager)
    {
        $this->variantResolver = $variantResolver;
        $this->variantFactory = $variantFactory;
        $this->businessContext = $businessContext;
        $this->entityManager = $entityManager;
    }

    /**
     * {@inheritdoc}
     */
    public function getVariant(ProductInterface $product): ?ProductVariantInterface
    {
        if ($this->businessContext->isActive()) {
            $businessAccount = $this->businessContext->getBusinessAccount();
            if ($businessAccount) {
                $restaurantGroup = $businessAccount->getBusinessRestaurantGroup();

                $variant = $this->getVariantForBusinessRestaurantGroup($product, $restaurantGroup);
                if ($variant) {
                    return $variant;
                }
            }
        }

        return $this->variantResolver->getVariant($product);
    }

    /**
     * {@inheritdoc}
     */
    public function getVariantForOptionValues(ProductInterface $product, \Traversable $optionValues): ?ProductVariantInterface
    {
        // We do *NOT* use the Product::getVariants() method,
        // because when there is a big number of variants, it becomes very slow.
        // See https://github.com/coopcycle/coopcycle-web/issues/4090
        $variantsAsArray = $this->getVariantsAsArray($product);

        foreach ($variantsAsArray as $variant) {

            if (count($variant['option_values']) !== count($optionValues)) {
                continue;
            }

            $isBusiness = !empty($variant['business_restaurant_group_id']);
            if ($isBusiness !== $this->businessContext->isActive()) {
                continue;
            }

            if ($this->matchOptions($variant, $optionValues)) {
                return $this->entityManager->getRepository(ProductVariantInterface::class)->find($variant['id']);
            }
        }

        // No variant found
        $variant = $this->variantFactory->createForProduct($product);
        $values = [];
        foreach ($optionValues as $optionValue) {

            $quantity = null;
            if ($optionValues instanceof \SplObjectStorage) {
                $quantity = $optionValues->getInfo();
            }

            if (null !== $quantity) {
                $variant->addOptionValueWithQuantity($optionValue, (int) $quantity);
            } else {
                $variant->addOptionValue($optionValue);
            }
        }

        $variant->setName($product->getName());
        $variant->setCode(Uuid::uuid4()->toString());

        $defaultVariant = $this->getVariant($product);

        // Copy price & tax category from default variant
        $variant->setPrice($defaultVariant->getPrice());
        $variant->setTaxCategory($defaultVariant->getTaxCategory());

        return $variant;
    }

    private function getVariantsAsArray(ProductInterface $product)
    {
        $qb = $this->entityManager->getRepository(ProductVariantInterface::class)->createQueryBuilder('variant');
        $qb->leftJoin(ProductVariantOptionValue::class, 'variant_option_value', Join::WITH, 'variant_option_value.variant = variant.id');
        $qb->leftJoin(ProductOptionValueInterface::class, 'option_value', Join::WITH, 'variant_option_value.optionValue = option_value.id');

        $qb->select('variant.id');
        $qb->addSelect('IDENTITY(variant.businessRestaurantGroup) AS business_restaurant_group_id');
        // This will return the option values & their quantity as JSON
        // [{"id" : 1, "quantity" : 1}, {"id" : 2, "quantity" : 2}]
        $qb->addSelect('JSON_AGG(JSON_BUILD_OBJECT(\'id\', option_value.id, \'quantity\', variant_option_value.quantity)) AS option_values');

        $qb->andWhere('variant.product = :product');
        $qb->setParameter('product', $product);

        $qb->groupBy('variant.id');

        return array_map(function ($variant) {
            $variant['option_values'] = json_decode($variant['option_values'], true);

            // This happens when a variant has no options
            if (count($variant['option_values']) === 1 && $variant['option_values'][0]['id'] === null) {
                $variant['option_values'] = [];
            }

            return $variant;
        }, $qb->getQuery()->getArrayResult());
    }

    private function matchOptions(array $variant, \Traversable $optionValues)
    {
        foreach ($optionValues as $optionValue) {

            $quantity = null;
            if ($optionValues instanceof \SplObjectStorage) {
                $quantity = $optionValues->getInfo();
            }

            if (null !== $quantity) {
                if (!$this->hasOptionValueWithQuantity($variant, $optionValue, (int) $quantity)) {
                    return false;
                }
            } else {
                if (!$this->hasOptionValue($variant, $optionValue)) {
                    return false;
                }
            }
        }

        return true;
    }

    private function hasOptionValue(array $variant, ProductOptionValueInterface $optionValue): bool
    {
        foreach ($variant['option_values'] as $optVal) {
            if ($optVal['id'] === $optionValue->getId()) {

                return true;
            }
        }

        return false;
    }

    private function hasOptionValueWithQuantity(array $variant, ProductOptionValueInterface $optionValue, int $quantity): bool
    {
        foreach ($variant['option_values'] as $optVal) {
            if ($optVal['id'] === $optionValue->getId()) {

                return $optVal['quantity'] === $quantity;
            }
        }

        return false;
    }

    public function getVariantForBusinessRestaurantGroup(ProductInterface $product, BusinessRestaurantGroup $businessRestaurantGroup): ?ProductVariantInterface
    {
        $qb = $this->entityManager->getRepository(ProductVariantInterface::class)->createQueryBuilder('v');
        $qb->andWhere('v.product = :product');
        $qb->andWhere('v.businessRestaurantGroup = :business_restaurant_group');
        $qb->setParameter('product', $product);
        $qb->setParameter('business_restaurant_group', $businessRestaurantGroup);

        return $qb->getQuery()->getOneOrNullResult();
    }
}
