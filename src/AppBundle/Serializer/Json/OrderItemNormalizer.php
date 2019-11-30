<?php

namespace AppBundle\Serializer\Json;

use AppBundle\Sylius\Order\AdjustmentInterface;
use ApiPlatform\Core\JsonLd\Serializer\ItemNormalizer;
use Sylius\Component\Order\Model\AdjustmentInterface as BaseAdjustmentInterface;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Order\Model\OrderItemInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class OrderItemNormalizer implements NormalizerInterface, DenormalizerInterface
{
    private $normalizer;

    public function __construct(ObjectNormalizer $normalizer)
    {
        $this->normalizer = $normalizer;
    }

    private function normalizeAdjustments(array $adjustments)
    {
        $data = array_map(function (BaseAdjustmentInterface $adjustment) {

            return [
                'id' => $adjustment->getId(),
                'label' => $adjustment->getLabel(),
                'amount' => $adjustment->getAmount(),
            ];
        }, $adjustments);

        return array_values($data);
    }

    public function normalize($object, $format = null, array $context = array())
    {
        $data = $this->normalizer->normalize($object, $format, $context);

        $optionsAdjustments = $object->getAdjustments(AdjustmentInterface::MENU_ITEM_MODIFIER_ADJUSTMENT)->toArray();
        $packagingAdjustments = $object->getAdjustments(AdjustmentInterface::REUSABLE_PACKAGING_ADJUSTMENT)->toArray();

        $data['name'] = $object->getVariant()->getProduct()->getName();
        $data['adjustments'] = [
            AdjustmentInterface::MENU_ITEM_MODIFIER_ADJUSTMENT => $this->normalizeAdjustments($optionsAdjustments)
        ];

        if (count($packagingAdjustments) > 0) {
            $data['adjustments'][AdjustmentInterface::REUSABLE_PACKAGING_ADJUSTMENT]
                = $this->normalizeAdjustments($packagingAdjustments);
        }

        return $data;
    }

    public function supportsNormalization($data, $format = null)
    {
        return $this->normalizer->supportsNormalization($data, $format) && $data instanceof OrderItemInterface;
    }

    public function denormalize($data, $class, $format = null, array $context = array())
    {
        return [];
    }

    public function supportsDenormalization($data, $type, $format = null)
    {
        return false;
    }
}
