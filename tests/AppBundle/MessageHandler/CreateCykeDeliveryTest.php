<?php

namespace Tests\AppBundle\MessageHandler;

use AppBundle\Entity\Address;
use AppBundle\Entity\Cyke\Delivery as CykeDelivery;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Package;
use AppBundle\Entity\Store;
use AppBundle\Entity\Task;
use AppBundle\Message\DeliveryCreated;
use AppBundle\MessageHandler\CreateCykeDelivery;
use AppBundle\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use libphonenumber\PhoneNumberUtil;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class CreateCykeDeliveryTest extends TestCase
{
    use ProphecyTrait;

    private $entityManager;
    private $deliveryRepository;
    private $cykeDeliveryRepository;
    private $settingsManager;
    private $mockResponse;
    private $client;

    public function setUp(): void
    {
        $this->mockResponse = new MockResponse(json_encode(['id' => 'cyke-1']), ['http_code' => 200]);
        $this->client = new MockHttpClient($this->mockResponse, 'https://cyke.example.com/');

        $this->entityManager = $this->prophesize(EntityManagerInterface::class);
        $this->deliveryRepository = $this->prophesize(ObjectRepository::class);
        $this->cykeDeliveryRepository = $this->prophesize(ObjectRepository::class);

        $this->entityManager
            ->getRepository(Delivery::class)
            ->willReturn($this->deliveryRepository->reveal());
        $this->entityManager
            ->getRepository(CykeDelivery::class)
            ->willReturn($this->cykeDeliveryRepository->reveal());
        $this->entityManager->persist(Argument::any())->willReturn(null);
        $this->entityManager->flush()->willReturn(null);

        $this->settingsManager = $this->prophesize(SettingsManager::class);
        $this->settingsManager->get('phone_number')->willReturn(null);
    }

    private function createHandler(bool $cykeEnabled = true): CreateCykeDelivery
    {
        return new CreateCykeDelivery(
            $this->client,
            $this->entityManager->reveal(),
            PhoneNumberUtil::getInstance(),
            $this->settingsManager->reveal(),
            $cykeEnabled
        );
    }

    private function createStore(): Store
    {
        $store = new Store();
        $store->setCykeUserEmail('agency@example.com');
        $store->setCykeUserToken('secret-token');
        $store->setCykePackageTypeId('42');

        return $store;
    }

    private function createDelivery(Store $store, ?Task $dropoff = null): Delivery
    {
        $pickup = new Task();
        $pickup->setType(Task::TYPE_PICKUP);

        if (null === $dropoff) {
            $dropoff = new Task();
            $dropoff->setType(Task::TYPE_DROPOFF);
        }

        $address = new Address();
        $address->setStreetAddress('64 Rue Alexandre Dumas');
        $address->setPostalCode('75011');
        $address->setAddressLocality('Paris');
        $address->setName('John Doe');
        $address->setCompany('Zimp Company');

        $dropoff->setAddress($address);
        $dropoff->setAfter(new \DateTime('+1 day 08:00'));

        $delivery = new Delivery();
        $delivery->setTasks([$pickup, $dropoff]);
        $delivery->setStore($store);

        return $delivery;
    }

    public function testDoesNothingWhenCykeIsDisabled(): void
    {
        $handler = $this->createHandler(cykeEnabled: false);

        $delivery = $this->prophesize(Delivery::class);
        $delivery->getId()->willReturn(1);

        $handler->__invoke(new DeliveryCreated($delivery->reveal()));

        $this->assertEquals(0, $this->client->getRequestsCount());
    }

    public function testDoesNothingWhenDeliveryIsNotFound(): void
    {
        $handler = $this->createHandler();

        $delivery = $this->prophesize(Delivery::class);
        $delivery->getId()->willReturn(1);

        $this->deliveryRepository->find(1)->willReturn(null);

        $handler->__invoke(new DeliveryCreated($delivery->reveal()));

        $this->assertEquals(0, $this->client->getRequestsCount());
    }

    public function testDoesNothingWhenStoreIsNotCykeEnabled(): void
    {
        $handler = $this->createHandler();

        $store = new Store();
        // No cykeUserEmail/cykeUserToken/cykePackageTypeId set: isCykeEnabled() is false
        $delivery = $this->createDelivery($store);

        $deliveryMessage = $this->prophesize(Delivery::class);
        $deliveryMessage->getId()->willReturn(1);

        $this->deliveryRepository->find(1)->willReturn($delivery);

        $handler->__invoke(new DeliveryCreated($deliveryMessage->reveal()));

        $this->assertEquals(0, $this->client->getRequestsCount());
    }

    public function testDoesNothingWhenAlreadySentToCyke(): void
    {
        $handler = $this->createHandler();

        $store = $this->createStore();
        $delivery = $this->createDelivery($store);

        $deliveryMessage = $this->prophesize(Delivery::class);
        $deliveryMessage->getId()->willReturn(1);

        $this->deliveryRepository->find(1)->willReturn($delivery);
        $this->cykeDeliveryRepository
            ->findOneBy(['delivery' => $delivery])
            ->willReturn(new CykeDelivery());

        $handler->__invoke(new DeliveryCreated($deliveryMessage->reveal()));

        $this->assertEquals(0, $this->client->getRequestsCount());
    }

    public function testSendsDefaultQuantityOfOneWhenDropoffHasNoPackages(): void
    {
        $handler = $this->createHandler();

        $store = $this->createStore();
        $delivery = $this->createDelivery($store);

        $deliveryMessage = $this->prophesize(Delivery::class);
        $deliveryMessage->getId()->willReturn(1);

        $this->deliveryRepository->find(1)->willReturn($delivery);
        $this->cykeDeliveryRepository
            ->findOneBy(['delivery' => $delivery])
            ->willReturn(null);

        $handler->__invoke(new DeliveryCreated($deliveryMessage->reveal()));

        $this->assertEquals(1, $this->client->getRequestsCount());

        $body = json_decode($this->mockResponse->getRequestOptions()['body'], true);

        $this->assertEquals([
            [
                'package_type_id' => 42,
                'amount' => 1,
            ],
        ], $body['packages']);
    }

    public function testSendsActualPackageQuantityWhenDropoffHasPackages(): void
    {
        $handler = $this->createHandler();

        $store = $this->createStore();

        $dropoff = new Task();
        $dropoff->setType(Task::TYPE_DROPOFF);

        $box = new Package();
        $box->setName('CARTON');
        $box->setShortCode('CA');

        // Mirrors ImportFromPoint::addPackageToTask, which is what actually
        // populates dropoff packages for EDIFACT-imported deliveries.
        $dropoff->addPackageWithQuantity($box, 5);

        $delivery = $this->createDelivery($store, $dropoff);

        $deliveryMessage = $this->prophesize(Delivery::class);
        $deliveryMessage->getId()->willReturn(1);

        $this->deliveryRepository->find(1)->willReturn($delivery);
        $this->cykeDeliveryRepository
            ->findOneBy(['delivery' => $delivery])
            ->willReturn(null);

        $handler->__invoke(new DeliveryCreated($deliveryMessage->reveal()));

        $this->assertEquals(1, $this->client->getRequestsCount());

        $body = json_decode($this->mockResponse->getRequestOptions()['body'], true);

        $this->assertEquals([
            [
                'package_type_id' => 42,
                'amount' => 5,
            ],
        ], $body['packages']);
    }
}
