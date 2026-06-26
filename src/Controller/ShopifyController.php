<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Shopify\ShopifyShop;
use AppBundle\Entity\Store;
use AppBundle\Form\Type\TimeSlotChoiceLoader;
use AppBundle\Service\ShopifyClient;
use Carbon\Carbon;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
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
        private string $shopifyGatewaySecret,
        private string $country,
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

        $scopes = 'read_orders,write_fulfillments,read_fulfillments';

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

        $this->setupShop($shop, $accessToken, null, $request->getSchemeAndHttpHost());

        return $this->render('shopify/installed.html.twig', [
            'shop' => $shop,
        ]);
    }

    /**
     * Step 2 of the install flow: the merchant arrives from the gateway, must be
     * authenticated, and picks which CoopCycle Store to link to their Shopify shop.
     *
     * GET  — shows the store picker (requires login; Symfony redirects to /login if not).
     * POST — validates the selection, signs the response, redirects back to the gateway.
     */
    #[Route(path: '/connect/shopify/choose-store', name: 'shopify_choose_store', methods: ['GET', 'POST'])]
    public function chooseStore(Request $request): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');

        $state = $request->get('state', '');
        $sig   = $request->get('sig', '');

        // Verify the gateway's HMAC so we know this request originated from our gateway.
        $expectedSig = hash_hmac('sha256', $state, $this->shopifyGatewaySecret);
        if (!$state || !$sig || !hash_equals($expectedSig, $sig)) {
            throw new BadRequestHttpException('Invalid or missing gateway signature.');
        }

        $stateData = json_decode(base64_decode($state), true);
        $shop      = $stateData['shop']      ?? null;
        $returnTo  = $stateData['return_to'] ?? null;

        if (!$shop || !$returnTo || !filter_var($returnTo, FILTER_VALIDATE_URL)) {
            throw new BadRequestHttpException('Malformed state token.');
        }

        if ($request->isMethod('POST')) {
            $storeId = (int) $request->request->get('store_id', 0);

            if (!$storeId) {
                return $this->render('shopify/choose_store.html.twig', [
                    'shop'   => $shop,
                    'state'  => $state,
                    'sig'    => $sig,
                    'stores' => $this->getAuthorizedStores(),
                    'error'  => 'Please select a store.',
                ]);
            }

            // Verify this user is actually allowed to manage the chosen store.
            $store = $this->entityManager->getRepository(Store::class)->find($storeId);
            if (!$store) {
                throw $this->createNotFoundException('Store not found.');
            }

            if (!$this->isGranted('ROLE_ADMIN') && !$this->getUser()->getStores()->contains($store)) {
                throw $this->createAccessDeniedException('You do not manage this store.');
            }

            // Sign the response so the gateway can verify CoopCycle authorised this store_id.
            $returnSig   = hash_hmac('sha256', $state . ':' . $storeId, $this->shopifyGatewaySecret);
            $redirectUrl = $returnTo . '?' . http_build_query([
                'state'      => $state,
                'store_id'   => $storeId,
                'return_sig' => $returnSig,
            ]);

            return new RedirectResponse($redirectUrl);
        }

        return $this->render('shopify/choose_store.html.twig', [
            'shop'   => $shop,
            'state'  => $state,
            'sig'    => $sig,
            'stores' => $this->getAuthorizedStores(),
            'error'  => null,
        ]);
    }

    /**
     * Called by the Shopify gateway after a successful OAuth flow.
     * Expects JSON body: {"shop_domain": "...", "access_token": "...", "store_id": 42}.
     * Authenticated via Authorization: Bearer {SHOPIFY_GATEWAY_SECRET}.
     */
    #[Route(path: '/connect/shopify/provision', name: 'shopify_provision', methods: ['POST'])]
    public function provision(Request $request): JsonResponse
    {
        $token = $request->headers->get('Authorization', '');
        $token = str_starts_with($token, 'Bearer ') ? substr($token, 7) : '';

        if (!$this->shopifyGatewaySecret || !hash_equals($this->shopifyGatewaySecret, $token)) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        $data        = json_decode($request->getContent(), true) ?? [];
        $shopDomain  = $data['shop_domain'] ?? null;
        $accessToken = $data['access_token'] ?? null;

        if (!$shopDomain || !$accessToken) {
            return new JsonResponse(['error' => 'Missing shop_domain or access_token'], 400);
        }

        $storeId = isset($data['store_id']) ? (int) $data['store_id'] : null;

        $this->setupShop($shopDomain, $accessToken, $storeId, $request->getSchemeAndHttpHost());

        return new JsonResponse(['success' => true]);
    }

    /** @return Store[] */
    private function getAuthorizedStores(): array
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->entityManager->getRepository(Store::class)->findAll();
        }

        return $this->getUser()->getStores()->toArray();
    }

    #[Route('/api/shopify/slots', name: 'shopify_slots', methods: ['GET', 'OPTIONS'])]
    public function slots(Request $request): JsonResponse
    {
        $corsHeaders = [
            'Access-Control-Allow-Origin'  => '*',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type',
        ];

        if ($request->isMethod('OPTIONS')) {
            return new JsonResponse(null, 204, $corsHeaders);
        }

        $domain = $request->query->get('domain', '');

        $shop = $domain
            ? $this->entityManager->getRepository(ShopifyShop::class)->findOneBy(['shopDomain' => $domain])
            : null;

        if (!$shop || !$shop->getStore() || !$shop->getStore()->getTimeSlot()) {
            return new JsonResponse(['slots' => []], 200, $corsHeaders);
        }

        $loader  = new TimeSlotChoiceLoader($shop->getStore()->getTimeSlot(), $this->country);
        $byDate  = [];

        foreach ($loader->loadChoiceList()->getChoices() as $choice) {
            [$start, $end] = $choice->getTimeRange();
            $date  = Carbon::instance($choice->getDate())->format('Y-m-d');
            $label = "{$start} - {$end}";
            $byDate[$date][] = ['value' => $label, 'label' => $label];
        }

        $slots = array_map(
            fn($date, $times) => ['date' => $date, 'times' => $times],
            array_keys($byDate),
            array_values($byDate)
        );

        return new JsonResponse(['slots' => $slots], 200, $corsHeaders);
    }

    private function setupShop(string $shopDomain, string $accessToken, ?int $storeId = null, ?string $tenantUrl = null): void
    {
        $shopEntity = $this->entityManager->getRepository(ShopifyShop::class)
            ->findOneBy(['shopDomain' => $shopDomain]);

        if (!$shopEntity) {
            $shopEntity = new ShopifyShop();
            $shopEntity->setShopDomain($shopDomain);
        }

        $shopEntity->setAccessToken($accessToken);
        // Shopify signs all webhooks with the app's API secret.
        $shopEntity->setWebhookSecret($this->shopifyApiSecret);

        if ($storeId !== null) {
            $store = $this->entityManager->getRepository(Store::class)->find($storeId);
            if ($store) {
                $shopEntity->setStore($store);
            }
        }

        $this->entityManager->persist($shopEntity);
        $this->entityManager->flush();

        $this->registerWebhooks($shopEntity);

        if ($tenantUrl) {
            $this->shopifyClient->setShopMetafield($shopEntity, 'coopcycle', 'tenant_url', $tenantUrl);
        }

        $this->logger->info(sprintf('Shopify shop "%s" installed/updated.', $shopDomain));
    }

    private function registerWebhooks(ShopifyShop $shopEntity): void
    {
        $webhookUrl = $this->generateUrl(
            '_api_/shopify/webhook/{id}_post',
            ['id' => $shopEntity->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $this->shopifyClient->registerWebhook($shopEntity, 'orders/create', $webhookUrl);
        $this->shopifyClient->registerWebhook($shopEntity, 'orders/cancelled', $webhookUrl);
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
