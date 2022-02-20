<?php

namespace Tests\AppBundle\MessageHandler;

use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Entity\ApiApp;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Store;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Webhook;
use AppBundle\Entity\WebhookExecution;
use AppBundle\Message\Webhook as WebhookMessage;
use AppBundle\MessageHandler\WebhookHandler;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use League\Bundle\OAuth2ServerBundle\Model\Client as OAuth2Client;

class WebhookHandlerTest extends TestCase
{
    use ProphecyTrait;

    public function setUp(): void
    {
        $this->entityManager = $this->prophesize(EntityManagerInterface::class);
        $this->iriConverter = $this->prophesize(IriConverterInterface::class);

        $this->apiAppRepository = $this->prophesize(ObjectRepository::class);
        $this->webhookRepository = $this->prophesize(ObjectRepository::class);

        $this->entityManager
            ->getRepository(ApiApp::class)
            ->willReturn($this->apiAppRepository->reveal());

        $this->entityManager
            ->getRepository(Webhook::class)
            ->willReturn($this->webhookRepository->reveal());

        $this->client = new MockHttpClient();

        $this->handler = new WebhookHandler(
            $this->client,
            $this->iriConverter->reveal(),
            $this->entityManager->reveal()
        );
    }

    public function testSendsHttpRequestForDeliveryPicked()
    {
        $oauth2Client = $this->prophesize(OAuth2Client::class);

        $apiApp = new ApiApp();
        $apiApp->setOauth2Client($oauth2Client->reveal());

        $store = new Store();

        $delivery = new Delivery();
        $delivery->setStore($store);

        $webhook = new Webhook();
        $webhook->setEvent('delivery.picked');
        $webhook->setUrl('http://example.com/webhook');
        $webhook->setSecret('123456');

        $this->apiAppRepository->findBy(['store' => $store])
            ->willReturn([ $apiApp ]);

        $this->webhookRepository->findBy(['oauth2Client' => $oauth2Client->reveal(), 'event' => 'delivery.picked'])
            ->willReturn([ $webhook ]);

        $this->iriConverter->getItemFromIri('/api/deliveries/1')
            ->willReturn($delivery);

        $mockResponse = new MockResponse('', ['http_code' => 200]);

        $responses = [
            $mockResponse
        ];

        $this->client->setResponseFactory($responses);

        call_user_func_array($this->handler, [
            new WebhookMessage('/api/deliveries/1', 'delivery.picked')
        ]);

        $this->entityManager->persist(Argument::that(function (WebhookExecution $execution) use ($webhook) {
            return $webhook === $execution->getWebhook() && $execution->getStatusCode() === 200;
        }))->shouldHaveBeenCalled();

        $this->entityManager->flush()->shouldHaveBeenCalled();

        $this->assertSame('POST', $mockResponse->getRequestMethod());
        $this->assertSame('http://example.com/webhook', $mockResponse->getRequestUrl());
        $this->assertContains(
            'X-CoopCycle-Signature: S9LMKpq4VBDEGm5umbQx/q4SQMnpX/Wz/719dBBZ3rI=',
            $mockResponse->getRequestOptions()['headers']
        );

        $expectedPayload = [
            'data' => [
                'object' => '/api/deliveries/1',
                'event' => 'delivery.picked'
            ]
        ];
        $this->assertEquals($expectedPayload, json_decode($mockResponse->getRequestOptions()['body'], true));
    }

    public function testSendsHttpRequestForOrderCreated()
    {
        $oauth2Client = $this->prophesize(OAuth2Client::class);

        $apiApp = new ApiApp();
        $apiApp->setOauth2Client($oauth2Client->reveal());

        $shop = new LocalBusiness();

        $order = new Order();
        $order->setRestaurant($shop);

        $webhook = new Webhook();
        $webhook->setEvent('order.created');
        $webhook->setUrl('http://example.com/webhook');
        $webhook->setSecret('123456');

        $this->apiAppRepository->findBy(['shop' => $shop])
            ->willReturn([ $apiApp ]);

        $this->webhookRepository->findBy(['oauth2Client' => $oauth2Client->reveal(), 'event' => 'order.created'])
            ->willReturn([ $webhook ]);

        $this->iriConverter->getItemFromIri('/api/orders/1')
            ->willReturn($order);

        $mockResponse = new MockResponse('', ['http_code' => 200]);

        $responses = [
            $mockResponse
        ];

        $this->client->setResponseFactory($responses);

        call_user_func_array($this->handler, [
            new WebhookMessage('/api/orders/1', 'order.created')
        ]);

        $this->entityManager->persist(Argument::that(function (WebhookExecution $execution) use ($webhook) {
            return $webhook === $execution->getWebhook() && $execution->getStatusCode() === 200;
        }))->shouldHaveBeenCalled();

        $this->entityManager->flush()->shouldHaveBeenCalled();

        $this->assertSame('POST', $mockResponse->getRequestMethod());
        $this->assertSame('http://example.com/webhook', $mockResponse->getRequestUrl());
        $this->assertContains(
            'X-CoopCycle-Signature: 8vf8W/TwEtIxWAWNHz8VIFwSTBNSiuk8GhTXbMsGWnc=',
            $mockResponse->getRequestOptions()['headers']
        );

        $expectedPayload = [
            'data' => [
                'object' => '/api/orders/1',
                'event' => 'order.created'
            ]
        ];
        $this->assertEquals($expectedPayload, json_decode($mockResponse->getRequestOptions()['body'], true));
    }

    public function testSendsHttpRequestWithFailure()
    {
        $this->expectException(HttpExceptionInterface::class);

        $oauth2Client = $this->prophesize(OAuth2Client::class);

        $apiApp = new ApiApp();
        $apiApp->setOauth2Client($oauth2Client->reveal());

        $store = new Store();

        $delivery = new Delivery();
        $delivery->setStore($store);

        $webhook = new Webhook();
        $webhook->setEvent('delivery.picked');
        $webhook->setUrl('http://example.com/webhook');
        $webhook->setSecret('123456');

        $this->apiAppRepository->findBy(['store' => $store])
            ->willReturn([ $apiApp ]);

        $this->webhookRepository->findBy(['oauth2Client' => $oauth2Client->reveal(), 'event' => 'delivery.picked'])
            ->willReturn([ $webhook ]);

        $this->iriConverter->getItemFromIri('/api/deliveries/1')
            ->willReturn($delivery);

        $responses = [
            new MockResponse('', ['http_code' => 400]),
        ];
        $this->client->setResponseFactory($responses);

        $this->entityManager->persist(Argument::that(function (WebhookExecution $execution) use ($webhook) {
            return $webhook === $execution->getWebhook() && $execution->getStatusCode() === 400;
        }))->shouldBeCalled();

        $this->entityManager->flush()->shouldBeCalled();

        call_user_func_array($this->handler, [
            new WebhookMessage('/api/deliveries/1', 'delivery.picked')
        ]);
    }

    public function testDoesNotSendHttpRequestWhenNotSameStore()
    {
        $oauth2Client = $this->prophesize(OAuth2Client::class);

        $apiApp = new ApiApp();
        $apiApp->setOauth2Client($oauth2Client->reveal());

        $store = new Store();

        $delivery = new Delivery();
        $delivery->setStore($store);

        $webhook = new Webhook();
        $webhook->setEvent('delivery.completed');
        $webhook->setUrl('http://example.com/webhook');
        $webhook->setSecret('123456');

        $this->apiAppRepository->findBy(['store' => $store])
            ->willReturn([]);

        call_user_func_array($this->handler, [
            new WebhookMessage('/api/deliveries/1', 'delivery.picked')
        ]);

        $this->entityManager->flush()->shouldNotHaveBeenCalled();
    }

    public function testDoesNotSendHttpRequestWhenNotSubscribedToEvent()
    {
        $oauth2Client = $this->prophesize(OAuth2Client::class);

        $apiApp = new ApiApp();
        $apiApp->setOauth2Client($oauth2Client->reveal());

        $store = new Store();

        $delivery = new Delivery();
        $delivery->setStore($store);

        $webhook = new Webhook();
        $webhook->setEvent('delivery.completed');
        $webhook->setUrl('http://example.com/webhook');
        $webhook->setSecret('123456');

        $this->apiAppRepository->findBy(['store' => $store])
            ->willReturn([ $apiApp ]);

        $this->webhookRepository->findBy(['oauth2Client' => $oauth2Client->reveal(), 'event' => 'delivery.completed'])
            ->willReturn([ $webhook ]);

        $this->webhookRepository->findBy(['oauth2Client' => $oauth2Client->reveal(), 'event' => 'delivery.picked'])
            ->willReturn([]);

        $this->iriConverter->getItemFromIri('/api/deliveries/1')
            ->willReturn($delivery);

        call_user_func_array($this->handler, [
            new WebhookMessage('/api/deliveries/1', 'delivery.picked')
        ]);

        $this->entityManager->flush()->shouldNotHaveBeenCalled();
    }
}
