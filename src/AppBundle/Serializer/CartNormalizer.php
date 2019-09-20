<?php

namespace AppBundle\Serializer;

use AppBundle\Sylius\Order\AdjustmentInterface;
use ApiPlatform\Core\JsonLd\Serializer\ItemNormalizer;
use Sylius\Component\Order\Model\AdjustmentInterface as BaseAdjustmentInterface;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Order\Model\OrderItemInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class CartNormalizer implements NormalizerInterface, DenormalizerInterface
{
    private $normalizer;

    public function __construct(ObjectNormalizer $normalizer)
    {
        $this->normalizer = $normalizer;
    }

    public function normalize($object, $format = null, array $context = array())
    {
        $data = $this->normalizer->normalize($object, $format, $context);

        // Make sure the array is zero-indexed
        $data['items'] = array_values($data['items']);

        $restaurant = $object->getRestaurant();
        if (null === $restaurant) {
            $data['restaurant'] = null;
        } else {
            $data['restaurant'] = [
                'id' => $restaurant->getId(),
                'address' => [
                    'latlng' => [
                        $restaurant->getAddress()->getGeo()->getLatitude(),
                        $restaurant->getAddress()->getGeo()->getLongitude(),
                    ]
                ]
            ];
        }

        $shippingAddress = $object->getShippingAddress();

        if (null !== $shippingAddress && null !== $shippingAddress->getGeo()) {
            $data['shippingAddress'] = [
                'latlng' => [
                    $shippingAddress->getGeo()->getLatitude(),
                    $shippingAddress->getGeo()->getLongitude(),
                ],
                'streetAddress' => $shippingAddress->getStreetAddress()
            ];
        } else {
            $data['shippingAddress'] = null;
        }

        $serializeAdjustments = function (BaseAdjustmentInterface $adjustment) {

            return [
                'id' => $adjustment->getId(),
                'label' => $adjustment->getLabel(),
                'amount' => $adjustment->getAmount(),
            ];
        };

        $deliveryAdjustments =
            array_map($serializeAdjustments, $object->getAdjustments(AdjustmentInterface::DELIVERY_ADJUSTMENT)->toArray());
        $deliveryPromotionAdjustments =
            array_map($serializeAdjustments, $object->getAdjustments(AdjustmentInterface::DELIVERY_PROMOTION_ADJUSTMENT)->toArray());
        $reusablePackagingAdjustments =
            array_map($serializeAdjustments, $object->getAdjustments(AdjustmentInterface::REUSABLE_PACKAGING_ADJUSTMENT)->toArray());

        $data['adjustments'] = [
            AdjustmentInterface::DELIVERY_ADJUSTMENT => array_values($deliveryAdjustments),
            AdjustmentInterface::DELIVERY_PROMOTION_ADJUSTMENT => array_values($deliveryPromotionAdjustments),
            AdjustmentInterface::REUSABLE_PACKAGING_ADJUSTMENT => array_values($reusablePackagingAdjustments)
        ];

        $shippedAt = $object->getShippedAt();
        if (null === $shippedAt) {
            $data['shippedAt'] = $data['date'] = null;
        } else {
            $data['shippedAt'] = $data['date'] = $shippedAt->format(\DateTime::ATOM);
        }

        $customer = $object->getCustomer();
        if (null === $customer) {
            $data['customer'] = null;
        } else {
            $data['customer'] = [
                'username' => $customer->getUsername()
            ];
        }

        return $data;
    }

    public function supportsNormalization($data, $format = null)
    {
        return $format === 'json'
            && $this->normalizer->supportsNormalization($data, $format)
            && $data instanceof OrderInterface
            ;
    }

    public function denormalize($data, $class, $format = null, array $context = array())
    {
        return null;
    }

    public function supportsDenormalization($data, $type, $format = null)
    {
        return false;
    }
}
