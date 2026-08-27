<?php

namespace Tests\AppBundle\Service;

use AppBundle\Entity\Shopify\ShopifyShop;
use AppBundle\Service\ShopifyClient;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Covers the GraphQL Admin API client. Public apps may no longer use the REST
 * Admin API, so these assert both that requests go to graphql.json and that the
 * translations REST used to do for us — slash-separated webhook topics, bare
 * numeric ids, lowercase delivery methods — still happen, on our side now.
 *
 * Failures reported inside a 200 response (`errors`, `userErrors`) get their own
 * tests: treating those as success is the classic way a GraphQL migration goes
 * quietly wrong.
 */
class ShopifyClientTest extends TestCase
{
    /** @var array<int,array{method:string,url:string,body:array}> */
    private array $requests = [];

    private function shop(): ShopifyShop
    {
        $shop = new ShopifyShop();
        $shop->setShopDomain('example.myshopify.com');
        $shop->setAccessToken('shpat_test');
        // No expiry recorded means "not expiring", so ensureFreshToken() lets
        // the call through without trying to refresh.
        $shop->setAccessTokenExpiresAt(null);

        return $shop;
    }

    /**
     * @param array<int,array> $responses payloads returned in order
     */
    private function client(array $responses): ShopifyClient
    {
        $this->requests = [];

        $factory = function (string $method, string $url, array $options) use (&$responses) {
            $this->requests[] = [
                'method' => $method,
                'url'    => $url,
                'body'   => json_decode($options['body'] ?? '{}', true) ?: [],
                'headers' => $options['headers'] ?? [],
            ];

            $payload = array_shift($responses) ?? ['data' => []];

            return new MockResponse(json_encode($payload), ['http_code' => $payload['__status'] ?? 200]);
        };

        return new ShopifyClient(
            new MockHttpClient($factory),
            $this->createMock(EntityManagerInterface::class),
            'api-key',
            'api-secret'
        );
    }

    private function lastRequest(): array
    {
        return $this->requests[count($this->requests) - 1];
    }

    public function testCallsTheGraphqlEndpointAndNeverRest()
    {
        $client = $this->client([
            ['data' => ['webhookSubscriptionCreate' => [
                'webhookSubscription' => ['id' => 'gid://shopify/WebhookSubscription/1'],
                'userErrors' => [],
            ]]],
        ]);

        $client->registerWebhook($this->shop(), 'orders/create', 'https://tenant.test/webhook');

        $request = $this->lastRequest();

        $this->assertSame('POST', $request['method']);
        $this->assertSame('https://example.myshopify.com/admin/api/2025-10/graphql.json', $request['url']);
        $this->assertStringNotContainsString('/admin/api/2025-10/webhooks.json', $request['url']);
        $this->assertArrayHasKey('query', $request['body']);
        $this->assertContains('X-Shopify-Access-Token: shpat_test', $request['headers']);
    }

    public function testRegisterWebhookConvertsTopicToTheGraphqlEnum()
    {
        $client = $this->client([
            ['data' => ['webhookSubscriptionCreate' => [
                'webhookSubscription' => ['id' => 'gid://shopify/WebhookSubscription/7'],
                'userErrors' => [],
            ]]],
        ]);

        $id = $client->registerWebhook($this->shop(), 'orders/cancelled', 'https://tenant.test/webhook');

        $this->assertSame('gid://shopify/WebhookSubscription/7', $id);

        $variables = $this->lastRequest()['body']['variables'];
        $this->assertSame('ORDERS_CANCELLED', $variables['topic']);
        $this->assertSame('https://tenant.test/webhook', $variables['subscription']['callbackUrl']);
        $this->assertSame('JSON', $variables['subscription']['format']);
    }

    /**
     * The payload deliberately carries a subscription id *and* a userError: if
     * the client only checked whether the id came back, this would look like a
     * success. userErrors alone must decide it.
     */
    public function testRegisterWebhookReturnsNullOnUserErrors()
    {
        $client = $this->client([
            ['data' => ['webhookSubscriptionCreate' => [
                'webhookSubscription' => ['id' => 'gid://shopify/WebhookSubscription/1'],
                'userErrors' => [['field' => 'callbackUrl', 'message' => 'is invalid']],
            ]]],
        ]);

        $this->assertNull($client->registerWebhook($this->shop(), 'orders/create', 'not-a-url'));
    }

    public function testRegisterWebhookReturnsNullOnTopLevelErrors()
    {
        $client = $this->client([
            ['errors' => [['message' => 'Access denied for webhookSubscriptionCreate field.']]],
        ]);

        $this->assertNull($client->registerWebhook($this->shop(), 'orders/create', 'https://tenant.test/webhook'));
    }

    public function testGetWebhookIdsReturnsGids()
    {
        $client = $this->client([
            ['data' => ['webhookSubscriptions' => ['nodes' => [
                ['id' => 'gid://shopify/WebhookSubscription/1'],
                ['id' => 'gid://shopify/WebhookSubscription/2'],
            ]]]],
        ]);

        $ids = $client->getWebhookIds($this->shop(), 'orders/create');

        $this->assertSame([
            'gid://shopify/WebhookSubscription/1',
            'gid://shopify/WebhookSubscription/2',
        ], $ids);

        $this->assertSame(['ORDERS_CREATE'], $this->lastRequest()['body']['variables']['topics']);
    }

    public function testDeleteWebhookAcceptsBothGidAndNumericId()
    {
        foreach (['gid://shopify/WebhookSubscription/5', '5'] as $input) {
            $client = $this->client([
                ['data' => ['webhookSubscriptionDelete' => [
                    'deletedWebhookSubscriptionId' => 'gid://shopify/WebhookSubscription/5',
                    'userErrors' => [],
                ]]],
            ]);

            $this->assertTrue($client->deleteWebhook($this->shop(), $input));
            $this->assertSame(
                'gid://shopify/WebhookSubscription/5',
                $this->lastRequest()['body']['variables']['id'],
                sprintf('input "%s" should reach Shopify as a GID', $input)
            );
        }
    }

    public function testSyncTenantUrlUpsertsAnAppDataMetafield()
    {
        $client = $this->client([
            ['data' => ['currentAppInstallation' => ['id' => 'gid://shopify/AppInstallation/42']]],
            ['data' => ['metafieldsSet' => [
                'metafields' => [['id' => 'gid://shopify/Metafield/9']],
                'userErrors' => [],
            ]]],
        ]);

        $this->assertTrue($client->syncTenantUrl($this->shop(), 'https://demo.coopcycle.org'));

        $metafield = $this->lastRequest()['body']['variables']['metafields'][0];

        $this->assertSame('gid://shopify/AppInstallation/42', $metafield['ownerId']);
        $this->assertSame('coopcycle', $metafield['namespace']);
        $this->assertSame('tenant_url', $metafield['key']);
        $this->assertSame('https://demo.coopcycle.org', $metafield['value']);
        $this->assertSame('single_line_text_field', $metafield['type']);
    }

    /**
     * The owner must be the app's own installation, never the shop. A shop-owned
     * metafield needs a write scope that no longer exists — Shopify rejects an
     * app version requesting read_metafields/write_metafields — so this is the
     * only owner the app can actually write to.
     */
    public function testMetafieldOwnerIsTheAppInstallationNotTheShop()
    {
        $client = $this->client([
            ['data' => ['currentAppInstallation' => ['id' => 'gid://shopify/AppInstallation/42']]],
            ['data' => ['metafieldsSet' => ['metafields' => [['id' => 'a']], 'userErrors' => []]]],
        ]);

        $client->syncTenantUrl($this->shop(), 'https://demo.coopcycle.org');

        $ownerQuery = $this->requests[0]['body']['query'];

        $this->assertStringContainsString('currentAppInstallation', $ownerQuery);
        $this->assertStringNotContainsString('shop {', $ownerQuery);
        $this->assertStringStartsWith(
            'gid://shopify/AppInstallation/',
            $this->lastRequest()['body']['variables']['metafields'][0]['ownerId']
        );
    }

    public function testSyncSlotsSpecSendsJsonTypedMetafield()
    {
        $client = $this->client([
            ['data' => ['currentAppInstallation' => ['id' => 'gid://shopify/AppInstallation/42']]],
            ['data' => ['metafieldsSet' => [
                'metafields' => [['id' => 'gid://shopify/Metafield/10']],
                'userErrors' => [],
            ]]],
        ]);

        $spec = [['dayOfWeek' => ['Mo'], 'opens' => '09:00', 'closes' => '18:00']];

        $this->assertTrue($client->syncSlotsSpec($this->shop(), $spec));

        $metafield = $this->lastRequest()['body']['variables']['metafields'][0];

        $this->assertSame('slots_spec', $metafield['key']);
        $this->assertSame('json', $metafield['type']);
        $this->assertSame($spec, json_decode($metafield['value'], true));
    }

    /**
     * An install writes tenant_url and slots_spec back to back; the owner lookup
     * should not be repeated for the second one.
     */
    public function testAppInstallationGidIsResolvedOnceAndReused()
    {
        $client = $this->client([
            ['data' => ['currentAppInstallation' => ['id' => 'gid://shopify/AppInstallation/42']]],
            ['data' => ['metafieldsSet' => ['metafields' => [['id' => 'a']], 'userErrors' => []]]],
            ['data' => ['metafieldsSet' => ['metafields' => [['id' => 'b']], 'userErrors' => []]]],
        ]);

        $shop = $this->shop();
        $client->syncTenantUrl($shop, 'https://demo.coopcycle.org');
        $client->syncSlotsSpec($shop, []);

        $this->assertCount(3, $this->requests, 'expected one owner lookup plus two metafield writes');
    }

    public function testMetafieldWriteFailsWhenAppInstallationGidCannotBeResolved()
    {
        $client = $this->client([
            ['errors' => [['message' => 'Access denied']]],
        ]);

        $this->assertFalse($client->syncTenantUrl($this->shop(), 'https://demo.coopcycle.org'));
        $this->assertCount(1, $this->requests, 'must not attempt the write without an owner');
    }

    /** userErrors decide the outcome even when a metafield is echoed back. */
    public function testMetafieldWriteFailsOnUserErrors()
    {
        $client = $this->client([
            ['data' => ['currentAppInstallation' => ['id' => 'gid://shopify/AppInstallation/42']]],
            ['data' => ['metafieldsSet' => [
                'metafields' => [['id' => 'gid://shopify/Metafield/9']],
                'userErrors' => [['field' => 'type', 'message' => 'is invalid']],
            ]]],
        ]);

        $this->assertFalse($client->syncTenantUrl($this->shop(), 'https://demo.coopcycle.org'));
    }

    /** A write that reports no metafields is a failure too, errors or not. */
    public function testMetafieldWriteFailsWhenNothingWasWritten()
    {
        $client = $this->client([
            ['data' => ['currentAppInstallation' => ['id' => 'gid://shopify/AppInstallation/42']]],
            ['data' => ['metafieldsSet' => ['metafields' => [], 'userErrors' => []]]],
        ]);

        $this->assertFalse($client->syncTenantUrl($this->shop(), 'https://demo.coopcycle.org'));
    }

    public function testDeleteWebhookFailsOnUserErrors()
    {
        $client = $this->client([
            ['data' => ['webhookSubscriptionDelete' => [
                'deletedWebhookSubscriptionId' => 'gid://shopify/WebhookSubscription/5',
                'userErrors' => [['field' => 'id', 'message' => 'does not exist']],
            ]]],
        ]);

        $this->assertFalse($client->deleteWebhook($this->shop(), '5'));
    }

    public function testUpdateFulfillmentFailsOnUserErrors()
    {
        $client = $this->client([
            ['data' => ['fulfillmentCreate' => [
                'fulfillment' => ['id' => 'gid://shopify/Fulfillment/3'],
                'userErrors' => [['field' => 'lineItemsByFulfillmentOrder', 'message' => 'is invalid']],
            ]]],
        ]);

        $this->assertFalse($client->updateFulfillment($this->shop(), '99', 'success'));
    }

    public function testGetDeliveryMethodTypesLowercasesTheEnum()
    {
        $client = $this->client([
            ['data' => ['order' => ['fulfillmentOrders' => ['nodes' => [
                ['deliveryMethod' => ['methodType' => 'LOCAL']],
            ]]]]],
        ]);

        $types = $client->getDeliveryMethodTypes($this->shop(), '1234567890');

        // ShopifyWebhookProcessor compares against the literal 'local'.
        $this->assertSame(['local'], $types);
        $this->assertSame('gid://shopify/Order/1234567890', $this->lastRequest()['body']['variables']['id']);
    }

    public function testGetDeliveryMethodTypesDeduplicates()
    {
        $client = $this->client([
            ['data' => ['order' => ['fulfillmentOrders' => ['nodes' => [
                ['deliveryMethod' => ['methodType' => 'SHIPPING']],
                ['deliveryMethod' => ['methodType' => 'SHIPPING']],
                ['deliveryMethod' => ['methodType' => 'LOCAL']],
            ]]]]],
        ]);

        $this->assertSame(['shipping', 'local'], $client->getDeliveryMethodTypes($this->shop(), '1'));
    }

    /**
     * Callers must be able to tell "lookup failed" from "order has no
     * fulfillment orders" — the processor decides whether to skip an order on
     * exactly that difference.
     */
    public function testGetDeliveryMethodTypesReturnsNullWhenTheOrderIsMissing()
    {
        $client = $this->client([['data' => ['order' => null]]]);

        $this->assertNull($client->getDeliveryMethodTypes($this->shop(), '1'));
    }

    public function testGetDeliveryMethodTypesReturnsEmptyArrayWhenThereAreNoFulfillmentOrders()
    {
        $client = $this->client([
            ['data' => ['order' => ['fulfillmentOrders' => ['nodes' => []]]]],
        ]);

        $this->assertSame([], $client->getDeliveryMethodTypes($this->shop(), '1'));
    }

    public function testUpdateFulfillmentSendsAFulfillmentOrderGid()
    {
        $client = $this->client([
            ['data' => ['fulfillmentCreate' => [
                'fulfillment' => ['id' => 'gid://shopify/Fulfillment/3'],
                'userErrors' => [],
            ]]],
        ]);

        $this->assertTrue($client->updateFulfillment($this->shop(), '99', 'success', 'https://track.test/1'));

        $fulfillment = $this->lastRequest()['body']['variables']['fulfillment'];

        $this->assertSame(
            'gid://shopify/FulfillmentOrder/99',
            $fulfillment['lineItemsByFulfillmentOrder'][0]['fulfillmentOrderId']
        );
        $this->assertSame(['url' => 'https://track.test/1'], $fulfillment['trackingInfo']);
    }

    public function testUpdateFulfillmentOmitsTrackingInfoWhenNoUrlIsGiven()
    {
        $client = $this->client([
            ['data' => ['fulfillmentCreate' => [
                'fulfillment' => ['id' => 'gid://shopify/Fulfillment/3'],
                'userErrors' => [],
            ]]],
        ]);

        $client->updateFulfillment($this->shop(), '99', 'success');

        $this->assertArrayNotHasKey('trackingInfo', $this->lastRequest()['body']['variables']['fulfillment']);
    }

    public function testHttpErrorIsTreatedAsFailure()
    {
        $client = $this->client([
            ['__status' => 401, 'errors' => 'Unauthorized'],
        ]);

        $this->assertNull($client->registerWebhook($this->shop(), 'orders/create', 'https://tenant.test/webhook'));
    }
}
