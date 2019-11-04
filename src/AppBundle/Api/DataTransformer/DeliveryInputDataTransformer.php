<?php

namespace AppBundle\Api\DataTransformer;

use ApiPlatform\Core\DataTransformer\DataTransformerInterface;
use AppBundle\Entity\Delivery;
use AppBundle\Api\Resource\Pricing;
use AppBundle\Serializer\DeliveryNormalizer;
use AppBundle\Service\RoutingInterface;
use ApiPlatform\Core\Api\IriConverterInterface;

class DeliveryInputDataTransformer implements DataTransformerInterface
{
    public function __construct(
        DeliveryNormalizer $deliveryNormalizer,
        RoutingInterface $routing,
        IriConverterInterface $iriConverter)
    {
        $this->deliveryNormalizer = $deliveryNormalizer;
        $this->routing = $routing;
        $this->iriConverter = $iriConverter;
    }

    /**
     * {@inheritdoc}
     */
    public function transform($data, string $to, array $context = [])
    {
        $deliveryData = [
            'pickup' => $data->pickup,
            'dropoff' => $data->dropoff,
            'weight' => $data->weight,
            'packages' => $data->packages,
        ];
        $delivery = $this->deliveryNormalizer->denormalize($deliveryData, Delivery::class);

        if ($data->store) {
            $store = $this->iriConverter->getItemFromIri($data->store);
            $delivery->setStore($store);
        }

        $osrmData = $this->routing->getRawResponse(
            $delivery->getPickup()->getAddress()->getGeo(),
            $delivery->getDropoff()->getAddress()->getGeo()
        );

        $distance = $osrmData['routes'][0]['distance'];

        $delivery->setDistance(ceil($distance));
        $delivery->setWeight($data->weight ?? null);

        return $delivery;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        if ($data instanceof Pricing) {
          return false;
        }

        return Pricing::class === $to && null !== ($context['input']['class'] ?? null);
    }
}
