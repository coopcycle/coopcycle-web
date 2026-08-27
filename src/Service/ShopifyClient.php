<?php

namespace AppBundle\Service;

use AppBundle\Entity\Shopify\ShopifyShop;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ShopifyClient
{
    /**
     * Keep in step with `[webhooks] api_version` in shopify-app/shopify.app.toml,
     * so the payloads Shopify sends match the shape this client reads back.
     */
    private const API_VERSION = '2025-10';

    /** @var array<string,string> shop domain => AppInstallation GID, see appInstallationGid() */
    private array $appInstallationGids = [];

    public function __construct(
        private HttpClientInterface $httpClient,
        private EntityManagerInterface $entityManager,
        private string $shopifyApiKey,
        private string $shopifyApiSecret,
        private LoggerInterface $logger = new NullLogger(),
    ) {}

    /**
     * Offline access tokens expire after an hour, so every call has to be
     * prepared to renew one. This runs server-side with no merchant present,
     * which is the whole point — webhooks and cron commands must keep working.
     *
     * Returns false when the token is expired and cannot be renewed; callers
     * then fail as they would for any other API error.
     */
    private function ensureFreshToken(ShopifyShop $shop): bool
    {
        if (!$shop->isAccessTokenExpired()) {
            return true;
        }

        $refreshToken = $shop->getRefreshToken();

        if (!$refreshToken) {
            $this->logger->error(sprintf(
                'Shopify access token for %s has expired and there is no refresh token. '
                . 'The shop must reinstall the app.',
                $shop->getShopDomain()
            ));

            return false;
        }

        try {
            $response = $this->httpClient->request(
                'POST',
                sprintf('https://%s/admin/oauth/access_token', $shop->getShopDomain()),
                [
                    'body' => [
                        'client_id'     => $this->shopifyApiKey,
                        'client_secret' => $this->shopifyApiSecret,
                        'grant_type'    => 'refresh_token',
                        'refresh_token' => $refreshToken,
                    ],
                ]
            );

            if ($response->getStatusCode() !== 200) {
                $this->logger->error(sprintf(
                    'Refreshing the Shopify access token for %s returned HTTP %d: %s',
                    $shop->getShopDomain(),
                    $response->getStatusCode(),
                    $response->getContent(false)
                ));

                return false;
            }

            $data = $response->toArray(false);
        } catch (HttpExceptionInterface | TransportExceptionInterface $e) {
            $this->logger->error(sprintf(
                'Refreshing the Shopify access token for %s failed: %s',
                $shop->getShopDomain(),
                $e->getMessage()
            ));

            return false;
        }

        if (empty($data['access_token'])) {
            $this->logger->error(sprintf(
                'Refresh response for %s contained no access token.',
                $shop->getShopDomain()
            ));

            return false;
        }

        $this->applyTokenResponse($shop, $data);
        $this->entityManager->flush();

        $this->logger->info(sprintf('Refreshed the Shopify access token for %s.', $shop->getShopDomain()));

        return true;
    }

    /**
     * Copies an /admin/oauth/access_token response onto the shop. Shared by the
     * refresh above and by the install flow, so both record expiry the same way.
     * A response without `expires_in` is a non-expiring token, which the Admin
     * API no longer accepts — recorded as such rather than silently trusted.
     */
    public function applyTokenResponse(ShopifyShop $shop, array $data): void
    {
        $shop->setAccessToken($data['access_token']);

        if (isset($data['refresh_token'])) {
            $shop->setRefreshToken($data['refresh_token']);
        }

        $shop->setAccessTokenExpiresAt(
            isset($data['expires_in'])
                ? new \DateTime(sprintf('@%d', time() + (int) $data['expires_in']))
                : null
        );
    }


    /**
     * Shopify's REST Admin API is a legacy API, and public apps must be built
     * exclusively on GraphQL, so every Admin call below goes through
     * {@see graphql()}. The two OAuth token calls above are not Admin API
     * endpoints and stay form-encoded POSTs.
     */
    public function registerWebhook(ShopifyShop $shop, string $topic, string $callbackUrl): ?string
    {
        $data = $this->graphql($shop, <<<'GRAPHQL'
            mutation RegisterWebhook($topic: WebhookSubscriptionTopic!, $subscription: WebhookSubscriptionInput!) {
              webhookSubscriptionCreate(topic: $topic, webhookSubscription: $subscription) {
                webhookSubscription { id }
                userErrors { field message }
              }
            }
            GRAPHQL, [
            'topic'        => $this->webhookTopic($topic),
            'subscription' => ['callbackUrl' => $callbackUrl, 'format' => 'JSON'],
        ]);

        if (null === $data || $this->hasUserErrors($data, 'webhookSubscriptionCreate')) {
            return null;
        }

        return $data['webhookSubscriptionCreate']['webhookSubscription']['id'] ?? null;
    }

    /**
     * @return string[] existing webhook subscription IDs for the given topic,
     *                  as GIDs — which is what deleteWebhook() expects back
     */
    public function getWebhookIds(ShopifyShop $shop, string $topic): array
    {
        $data = $this->graphql($shop, <<<'GRAPHQL'
            query WebhookIds($topics: [WebhookSubscriptionTopic!]) {
              webhookSubscriptions(first: 100, topics: $topics) {
                nodes { id }
              }
            }
            GRAPHQL, ['topics' => [$this->webhookTopic($topic)]]);

        if (null === $data) {
            return [];
        }

        return array_map(
            fn ($node) => (string) $node['id'],
            $data['webhookSubscriptions']['nodes'] ?? []
        );
    }

    public function deleteWebhook(ShopifyShop $shop, string $id): bool
    {
        $data = $this->graphql($shop, <<<'GRAPHQL'
            mutation DeleteWebhook($id: ID!) {
              webhookSubscriptionDelete(id: $id) {
                deletedWebhookSubscriptionId
                userErrors { field message }
              }
            }
            GRAPHQL, ['id' => $this->gid('WebhookSubscription', $id)]);

        if (null === $data || $this->hasUserErrors($data, 'webhookSubscriptionDelete')) {
            return false;
        }

        return null !== ($data['webhookSubscriptionDelete']['deletedWebhookSubscriptionId'] ?? null);
    }

    public function setAppMetafield(ShopifyShop $shop, string $namespace, string $key, string $value): bool
    {
        return $this->setMetafield($shop, $namespace, $key, $value, 'single_line_text_field');
    }

    public function syncTenantUrl(ShopifyShop $shop, string $tenantUrl): bool
    {
        return $this->setAppMetafield($shop, 'coopcycle', 'tenant_url', $tenantUrl);
    }

    /**
     * NOTE: nothing reads this metafield. The cart picker fetches slots live
     * from /api/shopify/slots instead, and the checkout extension that once
     * consumed slots_spec is no longer part of the app. Kept working rather than
     * deleted, since its callers (ShopifySyncSlotsCommand and
     * ShopifySlotsSyncSubscriber) are outside the scope of this migration.
     */
    public function syncSlotsSpec(ShopifyShop $shop, array $openingHoursSpec): bool
    {
        return $this->setMetafield($shop, 'coopcycle', 'slots_spec', json_encode($openingHoursSpec), 'json');
    }

    /**
     * Writes an app-data metafield: one owned by this app's own AppInstallation
     * rather than by the shop.
     *
     * That ownership is the whole point. A shop-owned metafield would need write
     * access to the shop, and there is no scope that grants it — read_metafields
     * and write_metafields are not valid scopes any more, and Shopify rejects an
     * app version that asks for them. An app-data metafield needs no scope at
     * all, is invisible to the merchant in the admin, and is still readable from
     * the theme app extension through Liquid's `app` object.
     *
     * metafieldsSet is an upsert keyed on owner + namespace + key, which
     * replaces the read-then-create-or-update dance the REST version needed.
     */
    private function setMetafield(ShopifyShop $shop, string $namespace, string $key, string $value, string $type): bool
    {
        $ownerId = $this->appInstallationGid($shop);

        if (null === $ownerId) {
            return false;
        }

        $data = $this->graphql($shop, <<<'GRAPHQL'
            mutation SetMetafield($metafields: [MetafieldsSetInput!]!) {
              metafieldsSet(metafields: $metafields) {
                metafields { id }
                userErrors { field message }
              }
            }
            GRAPHQL, [
            'metafields' => [[
                'ownerId'   => $ownerId,
                'namespace' => $namespace,
                'key'       => $key,
                'value'     => $value,
                'type'      => $type,
            ]],
        ]);

        if (null === $data || $this->hasUserErrors($data, 'metafieldsSet')) {
            return false;
        }

        return !empty($data['metafieldsSet']['metafields']);
    }

    /**
     * NOTE: $status is accepted but unused, exactly as it was under REST —
     * creating a fulfillment is a single act, whereas in_transit/success/failure
     * are fulfillment *events* and would need fulfillmentEventCreate. Left
     * as-is so this migration changes protocol and nothing else. This whole path
     * is currently unreachable anyway: it is only called with
     * ShopifyShop::getFulfillmentServiceId(), and nothing ever sets that field.
     */
    public function updateFulfillment(ShopifyShop $shop, string $fulfillmentOrderId, string $status, ?string $trackingUrl = null): bool
    {
        $fulfillment = [
            'lineItemsByFulfillmentOrder' => [
                ['fulfillmentOrderId' => $this->gid('FulfillmentOrder', $fulfillmentOrderId)],
            ],
        ];

        if ($trackingUrl) {
            $fulfillment['trackingInfo'] = ['url' => $trackingUrl];
        }

        $data = $this->graphql($shop, <<<'GRAPHQL'
            mutation CreateFulfillment($fulfillment: FulfillmentInput!) {
              fulfillmentCreate(fulfillment: $fulfillment) {
                fulfillment { id }
                userErrors { field message }
              }
            }
            GRAPHQL, ['fulfillment' => $fulfillment]);

        if (null === $data || $this->hasUserErrors($data, 'fulfillmentCreate')) {
            return false;
        }

        return null !== ($data['fulfillmentCreate']['fulfillment']['id'] ?? null);
    }

    /**
     * Returns the delivery method types of an order's fulfillment orders,
     * e.g. ["local"] for local delivery, ["shipping"] for regular shipping.
     *
     * The orders/create webhook payload carries no delivery method, so this
     * has to be looked up. Returns null when the lookup fails, which callers
     * must distinguish from an empty list (order has no fulfillment orders).
     *
     * GraphQL returns the method as an enum (LOCAL), where REST returned a
     * lowercase string ("local"); lowercasing keeps callers comparing against
     * the same values as before.
     */
    public function getDeliveryMethodTypes(ShopifyShop $shop, string $orderId): ?array
    {
        $data = $this->graphql($shop, <<<'GRAPHQL'
            query DeliveryMethodTypes($id: ID!) {
              order(id: $id) {
                fulfillmentOrders(first: 50) {
                  nodes { deliveryMethod { methodType } }
                }
              }
            }
            GRAPHQL, ['id' => $this->gid('Order', $orderId)]);

        if (null === $data || null === ($data['order'] ?? null)) {
            return null;
        }

        $types = [];
        foreach ($data['order']['fulfillmentOrders']['nodes'] ?? [] as $fulfillmentOrder) {
            $type = $fulfillmentOrder['deliveryMethod']['methodType'] ?? null;
            if ($type) {
                $types[] = strtolower($type);
            }
        }

        return array_values(array_unique($types));
    }

    /**
     * The GID of this app's own installation on the shop, which owns the
     * `coopcycle` metafields.
     *
     * Cached per instance: an install writes two metafields back to back, and
     * the id cannot change under us.
     */
    private function appInstallationGid(ShopifyShop $shop): ?string
    {
        $domain = $shop->getShopDomain();

        if (isset($this->appInstallationGids[$domain])) {
            return $this->appInstallationGids[$domain];
        }

        $data = $this->graphql($shop, '{ currentAppInstallation { id } }');

        $id = $data['currentAppInstallation']['id'] ?? null;

        if (null === $id) {
            $this->logger->error(sprintf('Could not resolve the app installation GID for %s.', $domain));

            return null;
        }

        return $this->appInstallationGids[$domain] = (string) $id;
    }

    /**
     * REST used slash-separated lowercase topics ("orders/create"); GraphQL uses
     * a screaming-snake enum. Callers still pass the REST spelling, which is
     * also what Shopify puts in the X-Shopify-Topic header.
     */
    private function webhookTopic(string $topic): string
    {
        return strtoupper(str_replace('/', '_', $topic));
    }

    /**
     * Numeric ids reach us from webhook payloads, which are still REST-shaped.
     * A value that is already a GID passes through untouched.
     */
    private function gid(string $type, string $id): string
    {
        return str_starts_with($id, 'gid://')
            ? $id
            : sprintf('gid://shopify/%s/%s', $type, $id);
    }

    /**
     * A GraphQL mutation reports business-rule failures in `userErrors` while
     * returning HTTP 200, so these have to be checked explicitly or every
     * failure looks like a success.
     */
    private function hasUserErrors(array $data, string $mutation): bool
    {
        $errors = $data[$mutation]['userErrors'] ?? [];

        if (empty($errors)) {
            return false;
        }

        $this->logger->error(sprintf(
            'Shopify %s returned user errors: %s',
            $mutation,
            json_encode($errors)
        ));

        return true;
    }

    /**
     * Single entry point for the GraphQL Admin API.
     *
     * @return array|null the `data` payload, or null when the call failed for
     *                    any reason — transport, HTTP status, or a top-level
     *                    GraphQL error. Mutation `userErrors` are the caller's
     *                    business, via hasUserErrors().
     */
    private function graphql(ShopifyShop $shop, string $query, array $variables = []): ?array
    {
        if (!$this->ensureFreshToken($shop)) {
            return null;
        }

        $url = sprintf('https://%s/admin/api/%s/graphql.json', $shop->getShopDomain(), self::API_VERSION);

        $body = ['query' => $query];

        if (!empty($variables)) {
            $body['variables'] = $variables;
        }

        try {
            $response = $this->httpClient->request('POST', $url, [
                'headers' => [
                    'X-Shopify-Access-Token' => $shop->getAccessToken(),
                    'Content-Type'           => 'application/json',
                ],
                'json' => $body,
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode < 200 || $statusCode >= 300) {
                $this->logger->error(sprintf(
                    'Shopify GraphQL request returned HTTP %d: %s',
                    $statusCode,
                    $response->getContent(false)
                ));

                return null;
            }

            $payload = $response->toArray(false);
        } catch (HttpExceptionInterface | TransportExceptionInterface $e) {
            $this->logger->error(sprintf('Shopify GraphQL error: %s', $e->getMessage()));

            return null;
        }

        // Query-level failures — a bad field, a missing scope, a throttle — come
        // back as HTTP 200 with an `errors` array and no usable data.
        if (!empty($payload['errors'])) {
            $this->logger->error(sprintf(
                'Shopify GraphQL returned errors: %s',
                json_encode($payload['errors'])
            ));

            return null;
        }

        return $payload['data'] ?? null;
    }
}
