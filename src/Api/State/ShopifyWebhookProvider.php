<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use AppBundle\Api\Resource\ShopifyWebhook;
use AppBundle\Entity\Shopify\ShopifyShop;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ShopifyWebhookProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RequestStack $requestStack,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $request = $this->requestStack->getCurrentRequest();

        $shop = isset($uriVariables['id'])
            ? $this->entityManager->getRepository(ShopifyShop::class)->find((int) $uriVariables['id'])
            : $this->entityManager->getRepository(ShopifyShop::class)
                ->findOneBy(['shopDomain' => $request->headers->get('X-Shopify-Shop-Domain')]);

        if (!$shop) {
            throw new NotFoundHttpException('Shopify shop not found.');
        }

        $rawBody = $request->getContent();
        $shopifyHmac = $request->headers->get('X-Shopify-Hmac-SHA256');
        $topic = $request->headers->get('X-Shopify-Topic');

        if (!$shopifyHmac || !$this->verifyHmac($rawBody, $shopifyHmac, $shop->getWebhookSecret())) {
            throw new AccessDeniedHttpException('Invalid Shopify HMAC signature.');
        }

        $webhook = new ShopifyWebhook($shop->getId());
        $webhook->payload = json_decode($rawBody, true) ?? [];
        $webhook->topic = $topic;

        return $webhook;
    }

    private function verifyHmac(string $body, string $hmac, string $secret): bool
    {
        $computed = base64_encode(hash_hmac('sha256', $body, $secret, true));

        return hash_equals($computed, $hmac);
    }
}
