<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Shopify\ShopifyShop;
use AppBundle\Service\ShopifyClient;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ShopifyController extends AbstractController
{
    public function __construct(
        private string $shopifyApiKey,
        private string $shopifyApiSecret,
        private EntityManagerInterface $entityManager,
        private ShopifyClient $shopifyClient,
        private LoggerInterface $logger,
    ) {}

    #[Route(path: '/connect/shopify', name: 'shopify_install')]
    public function install(Request $request): Response
    {
        $shop = $request->query->get('shop');

        if (!$shop || !$this->isValidShopDomain($shop)) {
            throw new BadRequestHttpException('Invalid shop domain.');
        }

        $state = bin2hex(random_bytes(16));
        $request->getSession()->set('shopify_oauth_state', $state);

        $redirectUri = $this->generateUrl('shopify_callback', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $scopes = 'read_orders,write_fulfillments,read_fulfillments,write_shipping';

        $authUrl = sprintf(
            'https://%s/admin/oauth/authorize?client_id=%s&scope=%s&redirect_uri=%s&state=%s',
            $shop,
            $this->shopifyApiKey,
            $scopes,
            urlencode($redirectUri),
            $state
        );

        return new RedirectResponse($authUrl);
    }

    #[Route(path: '/connect/shopify/callback', name: 'shopify_callback')]
    public function callback(Request $request): Response
    {
        $shop  = $request->query->get('shop');
        $code  = $request->query->get('code');
        $state = $request->query->get('state');
        $hmac  = $request->query->get('hmac');

        if (!$shop || !$code || !$state || !$hmac) {
            throw new BadRequestHttpException('Missing required parameters.');
        }

        $sessionState = $request->getSession()->get('shopify_oauth_state');
        if (!$sessionState || !hash_equals($sessionState, $state)) {
            throw new BadRequestHttpException('Invalid state parameter.');
        }
        $request->getSession()->remove('shopify_oauth_state');

        if (!$this->verifyHmac($request->query->all(), $hmac)) {
            throw new BadRequestHttpException('HMAC verification failed.');
        }

        $accessToken = $this->exchangeCodeForToken($shop, $code);
        if (!$accessToken) {
            throw new BadRequestHttpException('Failed to obtain access token.');
        }

        $shopEntity = $this->entityManager->getRepository(ShopifyShop::class)
            ->findOneBy(['shopDomain' => $shop]);

        if (!$shopEntity) {
            $shopEntity = new ShopifyShop();
            $shopEntity->setShopDomain($shop);
        }

        $shopEntity->setAccessToken($accessToken);
        $shopEntity->setWebhookSecret($this->shopifyApiSecret);

        $this->entityManager->persist($shopEntity);
        $this->entityManager->flush();

        $this->registerWebhooksAndFulfillmentService($shopEntity);

        $this->logger->info(sprintf('Shopify shop "%s" successfully installed.', $shop));

        return $this->render('shopify/installed.html.twig', [
            'shop' => $shop,
        ]);
    }

    private function registerWebhooksAndFulfillmentService(ShopifyShop $shopEntity): void
    {
        $webhookUrl = $this->generateUrl(
            'api_shopify_webhooks_post_collection',
            ['id' => $shopEntity->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        // Register orders/create webhook to receive new orders
        $this->shopifyClient->registerWebhook($shopEntity, 'orders/create', $webhookUrl);
        // Register orders/cancelled webhook to cancel deliveries
        $this->shopifyClient->registerWebhook($shopEntity, 'orders/cancelled', $webhookUrl);

        // Register as a fulfillment service so Shopify notifies us of fulfillment requests
        $fulfillmentCallbackUrl = $this->generateUrl(
            'api_shopify_webhooks_post_collection',
            ['id' => $shopEntity->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $fulfillmentServiceId = $this->shopifyClient->registerFulfillmentService($shopEntity, $fulfillmentCallbackUrl);

        if ($fulfillmentServiceId) {
            $shopEntity->setFulfillmentServiceId($fulfillmentServiceId);
            $this->entityManager->flush();
        }
    }

    private function exchangeCodeForToken(string $shop, string $code): ?string
    {
        $url = sprintf('https://%s/admin/oauth/access_token', $shop);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'client_id'     => $this->shopifyApiKey,
            'client_secret' => $this->shopifyApiSecret,
            'code'          => $code,
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$body) {
            $this->logger->error(sprintf('Shopify token exchange failed with HTTP %d', $httpCode));
            return null;
        }

        $data = json_decode($body, true);

        return $data['access_token'] ?? null;
    }

    private function verifyHmac(array $queryParams, string $hmac): bool
    {
        unset($queryParams['hmac'], $queryParams['signature']);

        ksort($queryParams);

        $message = http_build_query($queryParams);

        $computed = hash_hmac('sha256', $message, $this->shopifyApiSecret);

        return hash_equals($computed, $hmac);
    }

    private function isValidShopDomain(string $shop): bool
    {
        return (bool) preg_match('/^[a-zA-Z0-9\-]+\.myshopify\.com$/', $shop);
    }
}
