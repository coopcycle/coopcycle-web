<?php

namespace AppBundle\Api\DataTransformer;

use ApiPlatform\Core\DataTransformer\DataTransformerInterface;
use ApiPlatform\Core\Serializer\AbstractItemNormalizer;
use AppBundle\Api\Dto\CartItemInput;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Service\RoutingInterface;
use AppBundle\Sylius\Product\LazyProductVariantResolverInterface;
use AppBundle\Utils\OptionsPayloadConverter;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Order\Modifier\OrderModifierInterface;
use Sylius\Component\Product\Repository\ProductRepositoryInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;

class CartItemInputDataTransformer implements DataTransformerInterface
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly OptionsPayloadConverter $optionsPayloadConverter,
        private readonly LazyProductVariantResolverInterface $variantResolver,
        private readonly FactoryInterface $orderItemFactory,
        private readonly OrderItemQuantityModifierInterface $orderItemQuantityModifier,
        private readonly OrderModifierInterface $orderModifier)
    {
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
                $optionValues = $this->optionsPayloadConverter->convert($product, $data->options);
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

        return Order::class === $to && CartItemInput::class === ($context['input']['class'] ?? null);
    }
}
