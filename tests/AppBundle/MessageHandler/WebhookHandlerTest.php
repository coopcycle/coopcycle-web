<?php

namespace Tests\AppBundle\MessageHandler;

use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Entity\ApiApp;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Store;
use AppBundle\Entity\Webhook;
use AppBundle\Entity\WebhookExecution;
use AppBundle\Message\Webhook as WebhookMessage;
use AppBundle\MessageHandler\WebhookHandler;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Trikoder\Bundle\OAuth2Bundle\Model\Client as OAuth2Client;

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

        // https://docs.guzzlephp.org/en/stable/testing.html
        $this->mockHandler = new MockHandler();

        $handlerStack = HandlerStack::create($this->mockHandler);

        $this->httpContainer = [];
        $history = Middleware::history($this->httpContainer);

        $handlerStack->push($history);

        $this->client = new Client(['handler' => $handlerStack]);

        $this->handler = new WebhookHandler(
            $this->client,
            $this->iriConverter->reveal(),
            $this->entityManager->reveal()
        );
    }

    public function testSendsHttpRequest()
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

        $this->webhookRepository->findOneBy(['oauth2Client' => $oauth2Client->reveal(), 'event' => 'delivery.picked'])
            ->willReturn($webhook);

        $this->iriConverter->getItemFromIri('/api/deliveries/1')
            ->willReturn($delivery);

        $this->mockHandler->append(new Response(200));

        call_user_func_array($this->handler, [
            new WebhookMessage('/api/deliveries/1', 'delivery.picked')
        ]);

        $this->entityManager->persist(Argument::that(function (WebhookExecution $execution) use ($webhook) {
            return $webhook === $execution->getWebhook() && $execution->getStatusCode() === 200;
        }))->shouldHaveBeenCalled();

        $this->entityManager->flush()->shouldHaveBeenCalled();

        $this->assertCount(1, $this->httpContainer);

        // https://docs.guzzlephp.org/en/stable/testing.html#history-middleware
        foreach ($this->httpContainer as $transaction) {
            $this->assertEquals('POST', $transaction['request']->getMethod());
            $this->assertEquals('http://example.com/webhook', (string) $transaction['request']->getUri());
            $this->assertEquals('S9LMKpq4VBDEGm5umbQx/q4SQMnpX/Wz/719dBBZ3rI=',
                (string) $transaction['request']->getHeaderLine('x-coopcycle-signature'));

            $expectedPayload = [
                'data' => [
                    'object' => '/api/deliveries/1',
                    'event' => 'delivery.picked'
                ]
            ];

            $this->assertEquals($expectedPayload, json_decode($transaction['request']->getBody(), true));
        }
    }

    public function testSendsHttpRequestWithFailure()
    {
        $this->expectException(RequestException::class);

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

        $this->webhookRepository->findOneBy(['oauth2Client' => $oauth2Client->reveal(), 'event' => 'delivery.picked'])
            ->willReturn($webhook);

        $this->iriConverter->getItemFromIri('/api/deliveries/1')
            ->willReturn($delivery);

        $this->mockHandler->append(new Response(400));

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

        $this->assertCount(0, $this->httpContainer);
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

        $this->webhookRepository->findOneBy(['oauth2Client' => $oauth2Client->reveal(), 'event' => 'delivery.completed'])
            ->willReturn($webhook);

        $this->webhookRepository->findOneBy(['oauth2Client' => $oauth2Client->reveal(), 'event' => 'delivery.picked'])
            ->willReturn(null);

        $this->iriConverter->getItemFromIri('/api/deliveries/1')
            ->willReturn($delivery);

        call_user_func_array($this->handler, [
            new WebhookMessage('/api/deliveries/1', 'delivery.picked')
        ]);

        $this->assertCount(0, $this->httpContainer);
    }
}
