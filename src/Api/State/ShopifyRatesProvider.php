<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use AppBundle\Api\Resource\ShopifyRates;
use AppBundle\Entity\Shopify\ShopifyShop;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ShopifyRatesProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RequestStack $requestStack,
        private string $shopifyApiSecret,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $shopId = (int) $uriVariables['id'];

        $shop = $this->entityManager->getRepository(ShopifyShop::class)->find($shopId);

        if (!$shop) {
            throw new NotFoundHttpException(sprintf('Shopify shop with id %d not found.', $shopId));
        }

        $request = $this->requestStack->getCurrentRequest();
        $rawBody = $request->getContent();
        $shopifyHmac = $request->headers->get('X-Shopify-Hmac-SHA256');

        if (!$shopifyHmac || !$this->verifyHmac($rawBody, $shopifyHmac, $shop->getWebhookSecret())) {
            throw new AccessDeniedHttpException('Invalid Shopify HMAC signature.');
        }

        $payload = json_decode($rawBody, true) ?? [];

        $rates = new ShopifyRates($shopId);
        $rates->rate = $payload['rate'] ?? [];

        return $rates;
    }

    private function verifyHmac(string $body, string $hmac, string $secret): bool
    {
        $computed = base64_encode(hash_hmac('sha256', $body, $secret, true));

        return hash_equals($computed, $hmac);
    }
}
