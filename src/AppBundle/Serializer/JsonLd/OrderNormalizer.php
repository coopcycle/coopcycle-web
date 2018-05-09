<?php

namespace AppBundle\Serializer\JsonLd;

use ApiPlatform\Core\Api\IriConverterInterface;
use ApiPlatform\Core\JsonLd\Serializer\ItemNormalizer;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Sylius\Order\AdjustmentInterface;
use AppBundle\Sylius\Order\OrderFactory;
use AppBundle\Entity\Sylius\ProductVariantRepository;
use Sylius\Component\Order\Factory\AdjustmentFactoryInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Order\Modifier\OrderModifierInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class OrderNormalizer implements NormalizerInterface, DenormalizerInterface
{
    private $normalizer;
    private $iriConverter;
    private $orderFactory;
    private $productVariantRepository;
    private $orderItemFactory;
    private $adjustmentFactory;
    private $orderItemQuantityModifier;
    private $orderModifier;

    public function __construct(
        ItemNormalizer $normalizer,
        IriConverterInterface $iriConverter,
        OrderFactory $orderFactory,
        ProductVariantRepository $productVariantRepository,
        FactoryInterface $orderItemFactory,
        AdjustmentFactoryInterface $adjustmentFactory,
        OrderItemQuantityModifierInterface $orderItemQuantityModifier,
        OrderModifierInterface $orderModifier)
    {
        $this->normalizer = $normalizer;
        $this->iriConverter = $iriConverter;
        $this->orderFactory = $orderFactory;
        $this->productVariantRepository = $productVariantRepository;
        $this->orderItemFactory = $orderItemFactory;
        $this->adjustmentFactory = $adjustmentFactory;
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

    public function denormalize($data, $class, $format = null, array $context = array())
    {
        $order = $this->normalizer->denormalize($data, $class, $format, $context);

        $orderItems = array_map(function ($item) {

            $menuItem = $this->iriConverter->getItemFromIri($item['menuItem']);
            $productVariant = $this->productVariantRepository->findOneByMenuItem($menuItem);

            $orderItem = $this->orderItemFactory->createNew();
            $orderItem->setVariant($productVariant);
            $orderItem->setUnitPrice($productVariant->getPrice());

            $this->orderItemQuantityModifier->modify($orderItem, $item['quantity']);

            return $orderItem;

        }, $data['items']);

        $adjustment = $this->adjustmentFactory->createWithData(
            AdjustmentInterface::DELIVERY_ADJUSTMENT,
            'Livraison',
            $order->getRestaurant()->getFlatDeliveryPrice(),
            $neutral = false
        );
        $order->addAdjustment($adjustment);

        $order->clearItems();
        foreach ($orderItems as $orderItem) {
            $this->orderModifier->addToOrder($order, $orderItem);
        }

        return $order;
    }

    public function supportsDenormalization($data, $type, $format = null)
    {
        return $this->normalizer->supportsDenormalization($data, $type, $format) && $type === Order::class;
    }
}
