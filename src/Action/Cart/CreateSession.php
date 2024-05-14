<?php

namespace AppBundle\Action\Cart;

use ApiPlatform\Core\DataPersister\DataPersisterInterface;
use AppBundle\Security\OrderAccessTokenManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class CreateSession
{
    public function __construct(
        private DataPersisterInterface $dataPersister,
        private NormalizerInterface $itemNormalizer,
        private OrderAccessTokenManager $orderAccessTokenManager,
        private LoggerInterface $checkoutLogger
    )
    {
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

        return new JsonResponse([
            'token' => $this->orderAccessTokenManager->create($cart),
            'cart' => $this->itemNormalizer->normalize($data->cart, 'jsonld', [
                'groups' => ['cart']
            ]),
        ]);
    }
}
