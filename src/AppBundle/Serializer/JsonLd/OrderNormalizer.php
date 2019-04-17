<?php

namespace AppBundle\Serializer\JsonLd;

use ApiPlatform\Core\Api\IriConverterInterface;
use ApiPlatform\Core\JsonLd\Serializer\ItemNormalizer;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Sylius\Product\LazyProductVariantResolverInterface;
use AppBundle\Utils\ShippingDateFilter;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Order\Modifier\OrderModifierInterface;
use Sylius\Component\Product\Repository\ProductRepositoryInterface;
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
    private $shippingDateFilter;

    public function __construct(
        ItemNormalizer $normalizer,
        ChannelContextInterface $channelContext,
        ProductRepositoryInterface $productRepository,
        RepositoryInterface $productOptionValueRepository,
        LazyProductVariantResolverInterface $variantResolver,
        FactoryInterface $orderItemFactory,
        OrderItemQuantityModifierInterface $orderItemQuantityModifier,
        OrderModifierInterface $orderModifier,
        ShippingDateFilter $shippingDateFilter)
    {
        $this->normalizer = $normalizer;
        $this->channelContext = $channelContext;
        $this->productRepository = $productRepository;
        $this->productOptionValueRepository = $productOptionValueRepository;
        $this->variantResolver = $variantResolver;
        $this->orderItemFactory = $orderItemFactory;
        $this->orderItemQuantityModifier = $orderItemQuantityModifier;
        $this->orderModifier = $orderModifier;
        $this->shippingDateFilter = $shippingDateFilter;
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

    public function denormalize($data, $class, $format = null, array $context = array())
    {
        $order = $this->normalizer->denormalize($data, $class, $format, $context);

        // When no shipping date is provided, use ASAP
        if ($order->isFoodtech() && null === $order->getShippedAt()) {

            $availabilities = $order->getRestaurant()->getAvailabilities();
            $availabilities = array_filter($availabilities, function ($date) use ($order) {
                $shippingDate = new \DateTime($date);

                return $this->shippingDateFilter->accept($order, $shippingDate);
            });

            $asap = current(array_values($availabilities));

            $order->setShippedAt(new \DateTime($asap));
        }

        $order->setChannel($this->channelContext->getChannel());

        if (isset($data['items'])) {
            $orderItems = array_map(function ($item) {

                $product = $this->productRepository->findOneByCode($item['product']);

                if ($product->hasOptions()) {
                    if (isset($item['options']) && is_array($item['options'])) {
                        $optionValues = [];
                        foreach ($item['options'] as $optionValueCode) {
                            $optionValue = $this->productOptionValueRepository->findOneByCode($optionValueCode);
                            $optionValues[] = $optionValue;
                        }
                        $productVariant = $this->variantResolver->getVariantForOptionValues($product, $optionValues);
                    }
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
