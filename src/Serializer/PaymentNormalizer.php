<?php

namespace AppBundle\Serializer;

use ApiPlatform\JsonLd\Serializer\ItemNormalizer;
use AppBundle\DataType\NumRange;
use AppBundle\Payment\GatewayResolver;
use Sylius\Component\Payment\Model\Payment;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class PaymentNormalizer implements NormalizerInterface
{
    public function __construct(
        private ItemNormalizer $normalizer,
        private GatewayResolver $gatewayResolver)
    {}

    public function normalize($object, $format = null, array $context = array())
    {
        $data = $this->normalizer->normalize($object, $format, $context);

        $data['supportsPartialRefunds'] = $this->supportsPartialRefunds($object);

        return $data;
    }

    public function supportsNormalization($data, $format = null)
    {
        return $this->normalizer->supportsNormalization($data, $format) && $data instanceof Payment;
    }

    public function supportsDenormalization($data, $type, $format = null)
    {
        return false;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            Payment::class => true, // supports*() call result is cached
        ];
    }

    private function supportsPartialRefunds(Payment $payment): bool
    {
        if ('EDENRED' === $payment->getMethod()?->getCode()) {
            return false;
        }

        $gateway = $this->gatewayResolver->resolveForPayment($payment);

        if ('paygreen' === $gateway) {
            return false;
        }

        return true;
    }
}
