<?php

namespace AppBundle\Serializer\Json;

use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Sylius\Order\AdjustmentInterface;
use Sylius\Component\Order\Model\OrderItemInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class OrderItemNormalizer implements NormalizerInterface, DenormalizerInterface
{
    private $normalizer;
    private $iriConverter;

    public function __construct(
        NormalizerInterface $normalizer,
        IriConverterInterface $iriConverter)
    {
        $this->normalizer = $normalizer;
        $this->iriConverter = $iriConverter;
    }

    public function normalize($object, $format = null, array $context = array())
    {
        $data = $this->normalizer->normalize($object, $format, $context);

        // Change the shape of the "adjustments" prop

        $adjustments = array_values($data['adjustments']);

        $adjustmentsByType = [];

        $adjustmentTypes = [
            AdjustmentInterface::MENU_ITEM_MODIFIER_ADJUSTMENT,
            AdjustmentInterface::REUSABLE_PACKAGING_ADJUSTMENT,
            AdjustmentInterface::TAX_ADJUSTMENT,
        ];

        foreach ($adjustments as $adjustment) {
            if (in_array($adjustment['type'], $adjustmentTypes)) {
                $adjustmentsByType[$adjustment['type']][] = $adjustment;
            }
        }

        foreach ($adjustmentsByType as $adjustmentType => $adjustmentsArray) {
            $adjustmentsByType[$adjustmentType] = array_map(function ($adj) {

                return [
                    // FIXME
                    // Actually, we don't need the id to be serialized
                    'id' => $adj['@id'],
                    'label' => $adj['label'],
                    'amount' => $adj['amount'],
                ];
            }, $adjustmentsArray);
        }

        $data['adjustments'] = $adjustmentsByType;

        $data['name'] = $object->getVariant()->getProduct()->getName();

        $restaurant = $object->getVariant()->getProduct()->getRestaurant();

        if ($restaurant) {
            $data['vendor'] = [
                '@id' => $this->iriConverter->getIriFromItem($restaurant),
                'name' => $restaurant->getName(),
            ];
        } else {
            $data['vendor'] = null;
        }
        $data['player'] = $object->getCustomer();

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
