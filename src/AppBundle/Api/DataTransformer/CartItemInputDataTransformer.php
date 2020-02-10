<?php

namespace AppBundle\Api\DataTransformer;

use ApiPlatform\Core\Api\IriConverterInterface;
use ApiPlatform\Core\DataTransformer\DataTransformerInterface;
use ApiPlatform\Core\Serializer\AbstractItemNormalizer;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Api\Resource\Pricing;
use AppBundle\Serializer\DeliveryNormalizer;
use AppBundle\Service\RoutingInterface;
use AppBundle\Sylius\Product\LazyProductVariantResolverInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Order\Modifier\OrderModifierInterface;
use Sylius\Component\Product\Repository\ProductRepositoryInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

class CartItemInputDataTransformer implements DataTransformerInterface
{
    public function __construct(
        ProductRepositoryInterface $productRepository,
        RepositoryInterface $productOptionValueRepository,
        LazyProductVariantResolverInterface $variantResolver,
        FactoryInterface $orderItemFactory,
        OrderItemQuantityModifierInterface $orderItemQuantityModifier,
        OrderModifierInterface $orderModifier)
    {
        $this->productRepository = $productRepository;
        $this->productOptionValueRepository = $productOptionValueRepository;
        $this->variantResolver = $variantResolver;
        $this->orderItemFactory = $orderItemFactory;
        $this->orderItemQuantityModifier = $orderItemQuantityModifier;
        $this->orderModifier = $orderModifier;
    }

    /**
     * {@inheritdoc}
     */
    public function transform($data, string $to, array $context = [])
    {
        $cart = $context[AbstractItemNormalizer::OBJECT_TO_POPULATE];

        $product = $this->productRepository->findOneByCode($data->product);

        if (!$product->hasOptions()) {
            $productVariant = $this->variantResolver->getVariant($product);
        } else {
            if (!$product->hasNonAdditionalOptions() && empty($data->options)) {
                $productVariant = $this->variantResolver->getVariant($product);
            } else {
                $optionValues = new \SplObjectStorage();
                foreach ($data->options as $option) {
                    // Legacy
                    if (is_string($option)) {
                        $optionValue = $this->productOptionValueRepository->findOneByCode($option);
                        $optionValues->attach($optionValue);
                    } else {
                        $optionValue = $this->productOptionValueRepository->findOneByCode($option['code']);
                        if ($optionValue && $product->hasOptionValue($optionValue)) {
                            $quantity = isset($option['quantity']) ? (int) $option['quantity'] : 0;
                            if (!$optionValue->getOption()->isAdditional() || null === $optionValue->getOption()->getValuesRange()) {
                                $quantity = 1;
                            }
                            if ($quantity > 0) {
                                $optionValues->attach($optionValue, $quantity);
                            }
                        }
                    }
                }
                $productVariant = $this->variantResolver->getVariantForOptionValues($product, $optionValues);
            }
        }

        $orderItem = $this->orderItemFactory->createNew();
        $orderItem->setVariant($productVariant);
        $orderItem->setUnitPrice($productVariant->getPrice());

        $this->orderItemQuantityModifier->modify($orderItem, $data->quantity);

        $this->orderModifier->addToOrder($cart, $orderItem);

        return $cart;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        if ($data instanceof Order) {
          return false;
        }

        return Order::class === $to && null !== ($context['input']['class'] ?? null);
    }
}
