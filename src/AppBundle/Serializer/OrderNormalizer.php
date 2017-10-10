<?php

namespace AppBundle\Serializer;

use ApiPlatform\Core\JsonLd\Serializer\ItemNormalizer;
use AppBundle\Entity\Order;
use Symfony\Component\Routing\Router;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class OrderNormalizer implements NormalizerInterface, DenormalizerInterface
{
    private $normalizer;

    public function __construct(ItemNormalizer $normalizer, Router $router)
    {
        $this->normalizer = $normalizer;
        $this->router = $router;
    }

    public function normalize($object, $format = null, array $context = array())
    {
        $data =  $this->normalizer->normalize($object, $format, $context);

        $data['total'] = $object->getTotal();
        $data['publicUrl'] = $this->router->generate('order_public', ['uuid' => $object->getUuid()], true);

        return $data;
    }

    public function supportsNormalization($data, $format = null)
    {
        return $this->normalizer->supportsNormalization($data, $format) && $data instanceof Order;
    }

    public function denormalize($data, $class, $format = null, array $context = array())
    {
        return $this->normalizer->denormalize($data, $class, $format, $context);
    }

    public function supportsDenormalization($data, $type, $format = null)
    {
        return $this->normalizer->supportsDenormalization($data, $type, $format) && $type instanceof Order;
    }
}
