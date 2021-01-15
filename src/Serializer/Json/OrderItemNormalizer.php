<?php

namespace AppBundle\Serializer\Json;

use AppBundle\Entity\LocalBusinessRepository;
use AppBundle\Sylius\Order\AdjustmentInterface;
use ApiPlatform\Core\Api\IriConverterInterface;
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
    private $localBusinessRepository;
    private $iriConverter;

    public function __construct(
        ObjectNormalizer $normalizer,
        LocalBusinessRepository $localBusinessRepository,
        IriConverterInterface $iriConverter)
    {
        $this->normalizer = $normalizer;
        $this->localBusinessRepository = $localBusinessRepository;
        $this->iriConverter = $iriConverter;
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
        $taxAdjustments = $object->getAdjustments(AdjustmentInterface::TAX_ADJUSTMENT)->toArray();

        $data['name'] = $object->getVariant()->getProduct()->getName();
        $data['adjustments'] = [
            AdjustmentInterface::MENU_ITEM_MODIFIER_ADJUSTMENT => $this->normalizeAdjustments($optionsAdjustments),
            AdjustmentInterface::TAX_ADJUSTMENT => $this->normalizeAdjustments($taxAdjustments),
        ];

        if (count($packagingAdjustments) > 0) {
            $data['adjustments'][AdjustmentInterface::REUSABLE_PACKAGING_ADJUSTMENT]
                = $this->normalizeAdjustments($packagingAdjustments);
        }

        $restaurant = $this->localBusinessRepository->findOneByProduct(
            $object->getVariant()->getProduct()
        );

        if ($restaurant) {
            $data['vendor'] = [
                '@id' => $this->iriConverter->getIriFromItem($restaurant),
                'name' => $restaurant->getName(),
            ];
        } else {
            $data['vendor'] = null;
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
