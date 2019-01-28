<?php

namespace AppBundle\Serializer\JsonLd;

use ApiPlatform\Core\Api\IriConverterInterface;
use ApiPlatform\Core\JsonLd\Serializer\ItemNormalizer;
use AppBundle\Entity\Sylius\Order;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Order\Modifier\OrderModifierInterface;
use Sylius\Component\Product\Repository\ProductRepositoryInterface;
use Sylius\Component\Product\Resolver\ProductVariantResolverInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class OrderNormalizer implements NormalizerInterface, DenormalizerInterface
{
    private $normalizer;
    private $channelContext;
    private $productRepository;
    private $productOptionValueRepository;
    private $variantResolver;
    private $orderItemFactory;
    private $orderItemQuantityModifier;
    private $orderModifier;

    public function __construct(
        ItemNormalizer $normalizer,
        ChannelContextInterface $channelContext,
        ProductRepositoryInterface $productRepository,
        RepositoryInterface $productOptionValueRepository,
        ProductVariantResolverInterface $variantResolver,
        FactoryInterface $orderItemFactory,
        OrderItemQuantityModifierInterface $orderItemQuantityModifier,
        OrderModifierInterface $orderModifier)
    {
        $this->normalizer = $normalizer;
        $this->channelContext = $channelContext;
        $this->productRepository = $productRepository;
        $this->productOptionValueRepository = $productOptionValueRepository;
        $this->variantResolver = $variantResolver;
        $this->orderItemFactory = $orderItemFactory;
        $this->orderItemQuantityModifier = $orderItemQuantityModifier;
        $this->orderModifier = $orderModifier;
    }

    public function normalize($object, $format = null, array $context = array())
    {
        $data = $this->normalizer->normalize($object, $format, $context);

        if (isset($data['restaurant']) && is_array($data['restaurant'])) {
            unset($data['restaurant']['availabilities']);
            unset($data['restaurant']['minimumCartAmount']);
            unset($data['restaurant']['flatDeliveryPrice']);
        }

        return $data;
    }

    public function supportsNormalization($data, $format = null)
    {
        return $this->normalizer->supportsNormalization($data, $format) && $data instanceof Order;
    }

    private function matchOptions($variant, array $optionValues)
    {
        foreach ($optionValues as $optionValue) {
            if (!$variant->hasOptionValue($optionValue)) {
                return false;
            }
        }

        return true;
    }

    private function resolveProductVariant($product, array $optionValues)
    {
        foreach ($product->getVariants() as $variant) {
            if (count($variant->getOptionValues()) !== count($optionValues)) {
                continue;
            }

            if ($this->matchOptions($variant, $optionValues)) {
                return $variant;
            }
        }
    }

    public function denormalize($data, $class, $format = null, array $context = array())
    {
        $order = $this->normalizer->denormalize($data, $class, $format, $context);

        $order->setChannel($this->channelContext->getChannel());

        if (isset($data['items'])) {
            $orderItems = array_map(function ($item) {

                $product = $this->productRepository->findOneByCode($item['product']);

                if (isset($item['options'])) {
                    $optionValues = [];
                    foreach ($item['options'] as $optionValueCode) {
                        $optionValue = $this->productOptionValueRepository->findOneByCode($optionValueCode);
                        $optionValues[] = $optionValue;
                    }
                    $productVariant = $this->resolveProductVariant($product, $optionValues);
                } else {
                    $productVariant = $this->variantResolver->getVariant($product);
                }

                $orderItem = $this->orderItemFactory->createNew();
                $orderItem->setVariant($productVariant);
                $orderItem->setUnitPrice($productVariant->getPrice());

                $this->orderItemQuantityModifier->modify($orderItem, $item['quantity']);

                return $orderItem;

            }, $data['items']);

            $order->clearItems();
            foreach ($orderItems as $orderItem) {
                $this->orderModifier->addToOrder($order, $orderItem);
            }
        }

        return $order;
    }

    public function supportsDenormalization($data, $type, $format = null)
    {
        return $this->normalizer->supportsDenormalization($data, $type, $format) && $type === Order::class;
    }
}
