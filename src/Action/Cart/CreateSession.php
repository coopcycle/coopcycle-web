<?php

namespace AppBundle\Action\Cart;

use ApiPlatform\Core\Api\IriConverterInterface;
use ApiPlatform\Core\Api\UrlGeneratorInterface;
use ApiPlatform\Core\DataPersister\DataPersisterInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class CreateSession
{
    public function __construct(
        DataPersisterInterface $dataPersister,
        JWTEncoderInterface $jwtEncoder,
        IriConverterInterface $iriConverter,
        NormalizerInterface $itemNormalizer,
        private LoggerInterface $checkoutLogger
    )
    {
        $this->dataPersister = $dataPersister;
        $this->jwtEncoder = $jwtEncoder;
        $this->iriConverter = $iriConverter;
        $this->itemNormalizer = $itemNormalizer;
    }

    public function __invoke($data)
    {
        $cart = $data->cart;
        $isExisting = $cart->getId() === null;

        $this->dataPersister->persist($cart);

        if ($isExisting) {
            $this->checkoutLogger->info(sprintf('Order #%d updated in the database | CreateSession',
                $cart->getId()));
        } else {
            $this->checkoutLogger->info(sprintf('Order #%d (created_at = %s) created in the database (id = %d) | CreateSession',
                $cart->getId(), $cart->getCreatedAt()->format(\DateTime::ATOM), $cart->getId()));
        }

        $payload = [
            'sub' => $this->iriConverter->getIriFromItem($data->cart, UrlGeneratorInterface::ABS_URL),
            'exp' => time() + (60 * 60 * 24),
        ];

        return new JsonResponse([
            'token' => $this->jwtEncoder->encode($payload),
            'cart' => $this->itemNormalizer->normalize($data->cart, 'jsonld', [
                'groups' => ['cart']
            ]),
        ]);
    }
}
