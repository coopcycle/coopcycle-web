<?php

namespace AppBundle\Serializer\Csv;

use AppBundle\Entity\Delivery;
use AppBundle\Sylius\Order\AdjustmentInterface;
use ApiPlatform\Core\Serializer\ItemNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class DeliveryNormalizer implements NormalizerInterface
{
    private $normalizer;

    public function __construct(ObjectNormalizer $normalizer)
    {
        $this->normalizer = $normalizer;
    }

    public function normalize($object, $format = null, array $context = array())
    {
        $context = array_merge($context, ['groups' => ['delivery', 'address']]);
        $data = $this->normalizer->normalize($object, $format, $context);

        $owner = $object->getOwner();
        $order = $object->getOrder();
        $weight = $object->getWeight();

        $amount = null;
        if ($order) {
            if ($order->hasVendor()) {
                $amount = $order->getAdjustmentsTotal(AdjustmentInterface::DELIVERY_ADJUSTMENT);
            } else {
                $amount = $order->getTotal();
            }
        }

        $packages = [];
        if ($object->hasPackages()) {
            foreach ($object->getPackages() as $package) {
                $packages[] = sprintf('%d × %s',
                    $package->getQuantity(),
                    $package->getPackage()->getName());
            }
        }
        $packages = implode(', ', $packages);

        return [
            'organization' => $owner ? $owner->getName() : '',
            'pickup.address' => $data['pickup']['address']['streetAddress'],
            'pickup.after' => $data['pickup']['after'],
            'dropoff.address' => $data['dropoff']['address']['streetAddress'],
            'dropoff.before' => $data['dropoff']['before'],
            'weight' => $weight ?? '',
            'distance' => $object->getDistance(),
            'order.number' => $order ? $order->getNumber() : '',
            'order.total' => $amount ?? '',
            'packages' => $packages,
        ];
    }

    public function supportsNormalization($data, $format = null)
    {
        return 'csv' === $format && $data instanceof Delivery;
    }
}
