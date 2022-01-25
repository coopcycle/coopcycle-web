<?php

namespace Tests\AppBundle\Api\EventSubscriber;

use AppBundle\Api\EventSubscriber\UrbantzSubscriber;
use AppBundle\Api\Resource\UrbantzWebhook;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Store;
use AppBundle\Entity\Urbantz\Hub as UrbantzHub;
use AppBundle\Security\TokenStoreExtractor;
use AppBundle\Service\DeliveryManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Event\ViewEvent;

class UrbantzSubscriberTest extends TestCase
{
    use ProphecyTrait;

    private $urbantzBaseUri = 'https://api.urbantz.com/v2/';

    public function setUp(): void
    {
        $this->mockResponse = new MockResponse('', ['http_code' => 200]);
        $this->httpClient = new MockHttpClient($this->mockResponse, $this->urbantzBaseUri);

        $this->entityManager = $this->prophesize(EntityManagerInterface::class);
        $this->storeExtractor = $this->prophesize(TokenStoreExtractor::class);

        $this->urbantzHubRepository = $this->prophesize(ObjectRepository::class);

        $this->entityManager
            ->getRepository(UrbantzHub::class)
            ->willReturn($this->urbantzHubRepository->reveal());

        $this->deliveryManager = $this->prophesize(DeliveryManager::class);

        $this->subscriber = new UrbantzSubscriber(
            $this->httpClient,
            $this->entityManager->reveal(),
            $this->storeExtractor->reveal(),
            $this->deliveryManager->reveal(),
            new NullLogger(),
            'secret'
        );
    }

    public function testAddToStoreWithNoHub()
    {
        $httpKernel = $this->prophesize(HttpKernelInterface::class);

        $request = Request::create('/api/urbantz/webhook/tasks_announced', 'POST', [], [], [], [], '');
        $request->attributes->set('_route', 'api_urbantz_webhooks_receive_webhook_item');

        $delivery = $this->prophesize(Delivery::class);

        $controllerResult = new UrbantzWebhook(UrbantzWebhook::TASKS_ANNOUNCED);
        $controllerResult->deliveries[] = $delivery->reveal();

        $event = new ViewEvent(
            $httpKernel->reveal(),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $controllerResult
        );

        $store = $this->prophesize(Store::class);

        $this->storeExtractor
            ->extractStore()
            ->willReturn($store->reveal())
            ;

        $store
            ->addDelivery($delivery->reveal())
            ->shouldBeCalled();

        $this->subscriber->addToStore($event);
    }

    public function testAddToStoreWithNoMatchingHub()
    {
        $httpKernel = $this->prophesize(HttpKernelInterface::class);

        $request = Request::create('/api/urbantz/webhook/tasks_announced', 'POST', [], [], [], [], '');
        $request->attributes->set('_route', 'api_urbantz_webhooks_receive_webhook_item');

        $delivery = $this->prophesize(Delivery::class);

        $controllerResult = new UrbantzWebhook(UrbantzWebhook::TASKS_ANNOUNCED);
        $controllerResult->deliveries[] = $delivery->reveal();
        $controllerResult->hub = '618a4fce108a386e4699725f';

        $event = new ViewEvent(
            $httpKernel->reveal(),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $controllerResult
        );

        $store = $this->prophesize(Store::class);

        $this->storeExtractor
            ->extractStore()
            ->willReturn($store->reveal())
            ;

        $this->urbantzHubRepository
            ->findOneBy(['hub' => '618a4fce108a386e4699725f'])
            ->willReturn(null);

        $store
            ->addDelivery($delivery->reveal())
            ->shouldBeCalled();

        $this->subscriber->addToStore($event);
    }

    public function testAddToStoreWithHub()
    {
        $httpKernel = $this->prophesize(HttpKernelInterface::class);

        $request = Request::create('/api/urbantz/webhook/tasks_announced', 'POST', [], [], [], [], '');
        $request->attributes->set('_route', 'api_urbantz_webhooks_receive_webhook_item');

        $delivery = $this->prophesize(Delivery::class);

        $controllerResult = new UrbantzWebhook(UrbantzWebhook::TASKS_ANNOUNCED);
        $controllerResult->deliveries[] = $delivery->reveal();
        $controllerResult->hub = '618a4fce108a386e4699725f';

        $event = new ViewEvent(
            $httpKernel->reveal(),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $controllerResult
        );

        $storeFromToken = $this->prophesize(Store::class);
        $storeForHub = $this->prophesize(Store::class);

        $storeFromToken->getName()->willReturn('From token');
        $storeForHub->getName()->willReturn('For hub');

        $this->storeExtractor
            ->extractStore()
            ->willReturn($storeFromToken->reveal())
            ;

        $urbantzHub = new UrbantzHub();
        $urbantzHub->setStore($storeForHub->reveal());

        $this->urbantzHubRepository
            ->findOneBy(['hub' => '618a4fce108a386e4699725f'])
            ->willReturn($urbantzHub);

        $storeFromToken
            ->addDelivery($delivery->reveal())
            ->shouldNotBeCalled();

        $storeForHub
            ->addDelivery($delivery->reveal())
            ->shouldBeCalled();

        $this->subscriber->addToStore($event);
    }
}
