<?php

namespace Tests\AppBundle\MessageHandler;

use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Entity\Delivery;
use AppBundle\Message\WoopitWebhook;
use AppBundle\MessageHandler\WoopitWebhookHandler;
use BenjaminFavre\OAuthHttpClient\GrantType\ClientCredentialsGrantType;
use BenjaminFavre\OAuthHttpClient\GrantType\Tokens;
use BenjaminFavre\OAuthHttpClient\OAuthHttpClient;
use Doctrine\ORM\EntityManagerInterface;
use Hashids\Hashids;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class WoopitWebhookHandlerTest extends TestCase
{
    use ProphecyTrait;

    public function setUp() :void
    {
        $this->entityManager = $this->prophesize(EntityManagerInterface::class);
        $this->iriConverter = $this->prophesize(IriConverterInterface::class);

        $this->mockClient = new MockHttpClient(null, 'http://example.com/mocks/woop/');

        $this->clientCredentialsHandler = $this->prophesize(ClientCredentialsGrantType::class);
        $this->clientCredentialsHandler->getTokens()->willReturn(
            new Tokens('1234567890', null)
        );

        $this->client = new OAuthHttpClient($this->mockClient, $this->clientCredentialsHandler->reveal());

        $this->secret = '123456';

        $this->hashids = new Hashids($this->secret, 8);

        $this->handler = new WoopitWebhookHandler(
            $this->client,
            $this->iriConverter->reveal(),
            $this->entityManager->reveal(),
            $this->hashids
        );
    }

    public function testSendsHttpRequestForDeliveryStarted()
    {
        $delivery = $this->prophesize(Delivery::class);
        $delivery->getId()->willReturn(1);

        $this->iriConverter->getItemFromIri('/api/deliveries/1')
            ->willReturn($delivery->reveal());

        $deliveryId = $this->hashids->encode(1);

        $mockResponse = new MockResponse('', []);

        $responses = [
            $mockResponse
        ];

        $this->mockClient->setResponseFactory($responses);

        call_user_func_array($this->handler, [
            new WoopitWebhook('/api/deliveries/1', 'delivery.started')
        ]);


        $this->assertSame('PUT', $mockResponse->getRequestMethod());
        $this->assertSame(
            sprintf('http://example.com/mocks/woop/deliveries/%s/status', $deliveryId),
            $mockResponse->getRequestUrl()
        );

        $this->assertEquals(
            'DELIVERY_PICK_UP_STARTED',
            json_decode($mockResponse->getRequestOptions()['body'], true)['status']
        );
    }

    public function testSendsHttpRequestForDeliveryCompleted()
    {
        $delivery = $this->prophesize(Delivery::class);
        $delivery->getId()->willReturn(1);

        $this->iriConverter->getItemFromIri('/api/deliveries/1')
            ->willReturn($delivery->reveal());

        $deliveryId = $this->hashids->encode(1);

        $mockResponse = new MockResponse('', []);

        $responses = [
            $mockResponse
        ];

        $this->mockClient->setResponseFactory($responses);

        call_user_func_array($this->handler, [
            new WoopitWebhook('/api/deliveries/1', 'delivery.completed')
        ]);


        $this->assertSame('PUT', $mockResponse->getRequestMethod());
        $this->assertSame(
            sprintf('http://example.com/mocks/woop/deliveries/%s/status', $deliveryId),
            $mockResponse->getRequestUrl()
        );

        $this->assertEquals(
            'DELIVERY_OK',
            json_decode($mockResponse->getRequestOptions()['body'], true)['status']
        );
    }

    public function testSendsHttpRequestForDeliveryFailed()
    {
        $delivery = $this->prophesize(Delivery::class);
        $delivery->getId()->willReturn(1);

        $this->iriConverter->getItemFromIri('/api/deliveries/1')
            ->willReturn($delivery->reveal());

        $deliveryId = $this->hashids->encode(1);

        $mockResponse = new MockResponse('', []);

        $responses = [
            $mockResponse
        ];

        $this->mockClient->setResponseFactory($responses);

        call_user_func_array($this->handler, [
            new WoopitWebhook('/api/deliveries/1', 'delivery.failed')
        ]);


        $this->assertSame('PUT', $mockResponse->getRequestMethod());
        $this->assertSame(
            sprintf('http://example.com/mocks/woop/deliveries/%s/status', $deliveryId),
            $mockResponse->getRequestUrl()
        );

        $this->assertEquals(
            'DELIVERY_KO',
            json_decode($mockResponse->getRequestOptions()['body'], true)['status']
        );
    }
}
