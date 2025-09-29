<?php

namespace Tests\AppBundle\MessageHandler\Delivery;

use AppBundle\Entity\Address;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Store;
use AppBundle\Entity\Task;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Message\DeliveryCreated;
use AppBundle\Message\PushNotification;
use AppBundle\MessageHandler\DeliveryCreatedHandler;
use AppBundle\Service\EmailManager;
use AppBundle\Service\SettingsManager;
use Carbon\Carbon;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use AppBundle\Security\UserManager;
use NotFloran\MjmlBundle\Renderer\RendererInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment as TwigEnvironment;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

class DeliveryCreatedHandlerTest extends TestCase
{
    use ProphecyTrait;

    public function setUp(): void
    {
        $mockedToday = Carbon::create(2025, 1, 2, 0);
        Carbon::setTestNow($mockedToday);

        $this->entityManager = $this->prophesize(EntityManagerInterface::class);
        $this->userManager = $this->prophesize(UserManager::class);
        $this->emailManager = $this->prophesize(EmailManager::class);
        $this->mjml = $this->prophesize(RendererInterface::class);
        $this->messageBus = $this->prophesize(MessageBusInterface::class);
        $this->translator = $this->prophesize(TranslatorInterface::class);
        $this->twig = $this->prophesize(TwigEnvironment::class);
        $this->settingsManager = $this->prophesize(SettingsManager::class);
        $this->deliveryRepository = $this->prophesize(ObjectRepository::class);

        $this->entityManager
            ->getRepository(Delivery::class)
            ->willReturn($this->deliveryRepository->reveal());

        $this->userManager->findUsersByRoles(['ROLE_ADMIN', 'ROLE_DISPATCHER'])
            ->willReturn([]);

        $this->settingsManager->get('administrator_email')
            ->willReturn(null);

        $this->translator->trans('notifications.tap_to_open')
            ->willReturn('Tap to open');

        $this->handler = new DeliveryCreatedHandler(
            $this->entityManager->reveal(),
            $this->userManager->reveal(),
            $this->emailManager->reveal(),
            $this->mjml->reveal(),
            $this->messageBus->reveal(),
            $this->translator->reveal(),
            $this->twig->reveal(),
            $this->settingsManager->reveal(),
            'en'
        );
    }

    public function testSendDelivery__TYPE_SIMPLE__SamePickupAddr()
    {
        $pickup = new Task();
        $pickup->setType(Task::TYPE_PICKUP);
        $dropoff = new Task();
        $dropoff->setType(Task::TYPE_DROPOFF);
        $pickup->setNext($dropoff);
        $dropoff->setPrevious($pickup);

        $pickupAddress = new Address();
        $pickupAddress->setStreetAddress("111 Nice Pickup St, Somewhere, Argentina");
        $pickup->setAddress($pickupAddress);
        $dropoffAddress = new Address();
        $dropoffAddress->setStreetAddress("222 Nice Dropoff St, Someplace, Argentina");
        $dropoff->setAddress($dropoffAddress);

        $pickup->setAfter(new \DateTime('2025-01-02 01:02:03'));
        $pickup->setBefore(new \DateTime('2025-01-02 02:03:04'));
        $dropoff->setAfter(new \DateTime('2025-01-02 03:04:05'));
        $dropoff->setBefore(new \DateTime('2025-01-02 04:05:06'));

        $delivery = $this->prophesize(Delivery::class);
        $delivery->getId()->willReturn(1);
        $delivery->getTasks()->willReturn([$pickup, $dropoff]);
        $delivery->getPickup()->willReturn($pickup);
        $delivery->getDropoff()->willReturn($dropoff);

        $order = $this->prophesize(Order::class);
        $order->getId()->willReturn(11);
        $order->getNumber()->willReturn("G");
        $order->getDelivery()->willReturn($delivery);
        $delivery->getOrder()->willReturn($order);

        /////////////////////////
        // Test with a pickup address being the owner/store address
        /////////////////////////
        $this->genStoreOwner($delivery);

        $title = 'Test Store -> 222 Nice Dropoff St, Someplace, Argentina';
        $body = "PU: 01:02-02:03 | DO: 03:04-04:05";
        $data = [
            'event' => ['name' => 'delivery:created'],
            'delivery_id' => 1,
            'order_id' => 11,
            'order_number' => "G",
            'date_local' => 'Today at 1:02 AM',
            'date' => '2025-01-02',
            'time' => '01:02'
        ];

        $this->genPushNotificationAssertEqual($title, $body, $data);

        $message = $this->genDeliveryCreatedMessage($delivery);
        call_user_func_array($this->handler, [ $message ]);
    }

    public function testSendDelivery__TYPE_SIMPLE__DifferentPickupAddr()
    {
        $pickup = new Task();
        $pickup->setType(Task::TYPE_PICKUP);
        $dropoff = new Task();
        $dropoff->setType(Task::TYPE_DROPOFF);
        $pickup->setNext($dropoff);
        $dropoff->setPrevious($pickup);

        $pickupAddress = new Address();
        $pickupAddress->setStreetAddress("111 Nice Pickup St, Somewhere, Argentina");
        $pickup->setAddress($pickupAddress);
        $dropoffAddress = new Address();
        $dropoffAddress->setStreetAddress("222 Nice Dropoff St, Someplace, Argentina");
        // Give a name for dropoff address
        $dropoffAddress->setName("Test Address Name");
        $dropoff->setAddress($dropoffAddress);

        $pickup->setAfter(new \DateTime('2025-01-02 01:02:03'));
        $pickup->setBefore(new \DateTime('2025-01-02 02:03:04'));
        $dropoff->setAfter(new \DateTime('2025-01-02 03:04:05'));
        $dropoff->setBefore(new \DateTime('2025-01-02 04:05:06'));

        $delivery = $this->prophesize(Delivery::class);
        $delivery->getId()->willReturn(1);
        $delivery->getOrder()->willReturn(null);
        $delivery->getTasks()->willReturn([$pickup, $dropoff]);
        $delivery->getPickup()->willReturn($pickup);
        $delivery->getDropoff()->willReturn($dropoff);

        /////////////////////////
        // Test with a different pickup address than the owner/store address
        /////////////////////////
        $notTheDeliveryPickupAddress = (new Address())
            ->setStreetAddress("123 Nice Pickup St, Somewhere, Argentina");
        $this->genStoreOwner($delivery, $notTheDeliveryPickupAddress);

        $title = 'Test Store -> Test Address Name';
        $body = "PU: 01:02-02:03 | DO: 03:04-04:05
PU: 111 Nice Pickup St, Somewhere, Argentina
DO: 222 Nice Dropoff St, Someplace, Argentina";
        $data = [
            'event' => ['name' => 'delivery:created'],
            'delivery_id' => 1,
            'order_id' => null,
            'order_number' => null,
            'date_local' => 'Today at 1:02 AM',
            'date' => '2025-01-02',
            'time' => '01:02'
        ];

        $this->genPushNotificationAssertEqual($title, $body, $data);

        $message = $this->genDeliveryCreatedMessage($delivery);
        call_user_func_array($this->handler, [ $message ]);
    }

    public function testSendDelivery__TYPE_MULTI_PICKUP()
    {
        $pickup = new Task();
        $pickup->setType(Task::TYPE_PICKUP);
        $pickup2 = new Task();
        $pickup2->setType(Task::TYPE_PICKUP);
        $dropoff = new Task();
        $dropoff->setType(Task::TYPE_DROPOFF);
        $pickup->setNext($pickup2);
        $pickup2->setNext($dropoff);
        $dropoff->setPrevious($pickup2);

        $pickupAddress = new Address();
        $pickupAddress->setStreetAddress("111 Nice Pickup St, Somewhere, Argentina");
        $pickup->setAddress($pickupAddress);
        $pickup2Address = new Address();
        $pickup2Address->setStreetAddress("222 Nice Pickup St, Somewhere, Argentina");
        $pickup2->setAddress($pickup2Address);
        $dropoffAddress = new Address();
        $dropoffAddress->setStreetAddress("333 Nice Dropoff St, Someplace, Argentina");
        $dropoff->setAddress($dropoffAddress);

        $pickup->setAfter(new \DateTime('2025-01-02 01:02:03'));
        $pickup->setBefore(new \DateTime('2025-01-02 02:03:04'));
        $pickup2->setAfter(new \DateTime('2025-01-02 03:04:05'));
        $pickup2->setBefore(new \DateTime('2025-01-02 04:05:06'));
        $dropoff->setAfter(new \DateTime('2025-01-02 05:06:07'));
        $dropoff->setBefore(new \DateTime('2025-01-02 06:07:08'));

        $delivery = $this->prophesize(Delivery::class);
        $delivery->getId()->willReturn(2);
        $delivery->getTasks()->willReturn([$pickup, $pickup2, $dropoff]);
        $delivery->getPickup()->willReturn($pickup);
        $delivery->getDropoff()->willReturn($dropoff);

        $order = $this->prophesize(Order::class);
        $order->getId()->willReturn(11);
        $order->getNumber()->willReturn("G");
        $order->getDelivery()->willReturn($delivery);
        $delivery->getOrder()->willReturn($order);

        $this->genStoreOwner($delivery);

        $title = '2 pickups -> 333 Nice Dropoff St, Someplace, Argentina';
        $body = "PUs: 01:02-03:04 | DO: 05:06-06:07
PU 01:02-02:03: 111 Nice Pickup St, Somewhere, Argentina
PU 03:04-04:05: 222 Nice Pickup St, Somewhere, Argentina";
        $data = [
            'event' => ['name' => 'delivery:created'],
            'delivery_id' => 2,
            'order_id' => 11,
            'order_number' => "G",
            'date_local' => 'Today at 1:02 AM',
            'date' => '2025-01-02',
            'time' => '01:02'
        ];

        $this->genPushNotificationAssertEqual($title, $body, $data);

        $message = $this->genDeliveryCreatedMessage($delivery);
        call_user_func_array($this->handler, [ $message ]);
    }

    public function testSendDelivery__TYPE_MULTI_DROPOFF__SamePickupAddr()
    {
        $pickup = new Task();
        $pickup->setType(Task::TYPE_PICKUP);
        $dropoff = new Task();
        $dropoff->setType(Task::TYPE_DROPOFF);
        $dropoff2 = new Task();
        $dropoff2->setType(Task::TYPE_DROPOFF);
        $pickup->setNext($dropoff);
        $dropoff->setPrevious($pickup);
        $dropoff->setNext($dropoff2);
        $dropoff2->setPrevious($dropoff);

        $pickupAddress = new Address();
        $pickupAddress->setStreetAddress("111 Nice Pickup St, Somewhere, Argentina");
        $pickup->setAddress($pickupAddress);
        $dropoffAddress = new Address();
        $dropoffAddress->setStreetAddress("222 Nice Dropoff St, Someplace, Argentina");
        $dropoff->setAddress($dropoffAddress);
        $dropoff2Address = new Address();
        $dropoff2Address->setStreetAddress("333 Nice Dropoff St, Someplace, Argentina");
        $dropoff2->setAddress($dropoff2Address);

        $pickup->setAfter(new \DateTime('2025-01-02 01:02:03'));
        $pickup->setBefore(new \DateTime('2025-01-02 02:03:04'));
        $dropoff->setAfter(new \DateTime('2025-01-02 03:04:05'));
        $dropoff->setBefore(new \DateTime('2025-01-02 04:05:06'));
        $dropoff2->setAfter(new \DateTime('2025-01-02 05:06:07'));
        $dropoff2->setBefore(new \DateTime('2025-01-02 06:07:08'));

        $delivery = $this->prophesize(Delivery::class);
        $delivery->getId()->willReturn(3);
        $delivery->getOrder()->willReturn(null);
        $delivery->getTasks()->willReturn([$pickup, $dropoff, $dropoff2]);
        $delivery->getPickup()->willReturn($pickup);
        $delivery->getDropoff()->willReturn($dropoff2);

        $order = $this->prophesize(Order::class);
        $order->getId()->willReturn(11);
        $order->getNumber()->willReturn("G");
        $order->getDelivery()->willReturn($delivery);
        $delivery->getOrder()->willReturn($order);

        /////////////////////////
        // Test with a pickup address being the owner/store address
        /////////////////////////
        $this->genStoreOwner($delivery);

        $title = 'Test Store -> 2 dropoffs';
        $body = "PU: 01:02-02:03 | DOs: 03:04-05:06
DO 03:04-04:05: 222 Nice Dropoff St, Someplace, Argentina
DO 05:06-06:07: 333 Nice Dropoff St, Someplace, Argentina";
        $data = [
            'event' => ['name' => 'delivery:created'],
            'delivery_id' => 3,
            'order_id' => 11,
            'order_number' => "G",
            'date_local' => 'Today at 1:02 AM',
            'date' => '2025-01-02',
            'time' => '01:02'
        ];

        $this->genPushNotificationAssertEqual($title, $body, $data);

        $message = $this->genDeliveryCreatedMessage($delivery);
        call_user_func_array($this->handler, [ $message ]);
    }

    public function testSendDelivery__TYPE_MULTI_DROPOFF__DifferentPickupAddr()
    {
        $pickup = new Task();
        $pickup->setType(Task::TYPE_PICKUP);
        $dropoff = new Task();
        $dropoff->setType(Task::TYPE_DROPOFF);
        $dropoff2 = new Task();
        $dropoff2->setType(Task::TYPE_DROPOFF);
        $pickup->setNext($dropoff);
        $dropoff->setPrevious($pickup);
        $dropoff->setNext($dropoff2);
        $dropoff2->setPrevious($dropoff);

        $pickupAddress = new Address();
        $pickupAddress->setStreetAddress("111 Nice Pickup St, Somewhere, Argentina");
        $pickup->setAddress($pickupAddress);
        $dropoffAddress = new Address();
        $dropoffAddress->setStreetAddress("222 Nice Dropoff St, Someplace, Argentina");
        $dropoff->setAddress($dropoffAddress);
        $dropoff2Address = new Address();
        $dropoff2Address->setStreetAddress("333 Nice Dropoff St, Someplace, Argentina");
        $dropoff2->setAddress($dropoff2Address);

        $pickup->setAfter(new \DateTime('2025-01-02 01:02:03'));
        $pickup->setBefore(new \DateTime('2025-01-02 02:03:04'));
        $dropoff->setAfter(new \DateTime('2025-01-02 03:04:05'));
        $dropoff->setBefore(new \DateTime('2025-01-02 04:05:06'));
        $dropoff2->setAfter(new \DateTime('2025-01-02 05:06:07'));
        $dropoff2->setBefore(new \DateTime('2025-01-02 06:07:08'));

        $delivery = $this->prophesize(Delivery::class);
        $delivery->getId()->willReturn(3);
        $delivery->getOrder()->willReturn(null);
        $delivery->getTasks()->willReturn([$pickup, $dropoff, $dropoff2]);
        $delivery->getPickup()->willReturn($pickup);
        $delivery->getDropoff()->willReturn($dropoff2);

        /////////////////////////
        // Test with a different pickup address than the owner/store address
        /////////////////////////
        $notTheDeliveryPickupAddress = (new Address())
            ->setStreetAddress("123 Nice Pickup St, Somewhere, Argentina");
        $this->genStoreOwner($delivery, $notTheDeliveryPickupAddress);

        $title = 'Test Store -> 2 dropoffs';
        $body = "PU: 01:02-02:03 | DOs: 03:04-05:06
PU: 111 Nice Pickup St, Somewhere, Argentina
DO 03:04-04:05: 222 Nice Dropoff St, Someplace, Argentina
DO 05:06-06:07: 333 Nice Dropoff St, Someplace, Argentina";
        $data = [
            'event' => ['name' => 'delivery:created'],
            'delivery_id' => 3,
            'order_id' => null,
            'order_number' => null,
            'date_local' => 'Today at 1:02 AM',
            'date' => '2025-01-02',
            'time' => '01:02'
        ];

        $this->genPushNotificationAssertEqual($title, $body, $data);

        $message = $this->genDeliveryCreatedMessage($delivery);
        call_user_func_array($this->handler, [ $message ]);
    }

    public function testSendDelivery__TYPE_MULTI_MULTI()
    {
        $pickup = new Task();
        $pickup->setType(Task::TYPE_PICKUP);
        $pickup2 = new Task();
        $pickup2->setType(Task::TYPE_PICKUP);
        $dropoff = new Task();
        $dropoff->setType(Task::TYPE_DROPOFF);
        $dropoff2 = new Task();
        $dropoff2->setType(Task::TYPE_DROPOFF);
        $pickup->setNext($pickup2);
        $pickup2->setPrevious($pickup);
        $pickup2->setNext($dropoff);
        $dropoff->setPrevious($pickup2);
        $dropoff->setNext($dropoff2);
        $dropoff2->setPrevious($dropoff);

        $pickupAddress = new Address();
        $pickupAddress->setStreetAddress("111 Nice Pickup St, Somewhere, Argentina");
        $pickup->setAddress($pickupAddress);
        $pickup2Address = new Address();
        $pickup2Address->setStreetAddress("222 Nice Pickup St, Somewhere, Argentina");
        $pickup2->setAddress($pickup2Address);
        $dropoffAddress = new Address();
        $dropoffAddress->setStreetAddress("333 Nice Dropoff St, Someplace, Argentina");
        $dropoff->setAddress($dropoffAddress);
        $dropoff2Address = new Address();
        $dropoff2Address->setStreetAddress("444 Nice Dropoff St, Someplace, Argentina");
        $dropoff2->setAddress($dropoff2Address);

        $pickup->setAfter(new \DateTime('2025-01-02 01:02:03'));
        $pickup->setBefore(new \DateTime('2025-01-02 02:03:04'));
        $pickup2->setAfter(new \DateTime('2025-01-02 03:04:05'));
        $pickup2->setBefore(new \DateTime('2025-01-02 04:05:06'));
        $dropoff->setAfter(new \DateTime('2025-01-02 05:06:07'));
        $dropoff->setBefore(new \DateTime('2025-01-02 06:07:08'));
        $dropoff2->setAfter(new \DateTime('2025-01-02 07:08:09'));
        $dropoff2->setBefore(new \DateTime('2025-01-02 08:09:10'));

        $delivery = $this->prophesize(Delivery::class);
        $delivery->getId()->willReturn(4);
        $delivery->getTasks()->willReturn([$pickup, $pickup2, $dropoff, $dropoff2]);
        $delivery->getPickup()->willReturn($pickup);
        $delivery->getDropoff()->willReturn($dropoff2);

        $order = $this->prophesize(Order::class);
        $order->getId()->willReturn(11);
        $order->getNumber()->willReturn("G");
        $order->getDelivery()->willReturn($delivery);
        $delivery->getOrder()->willReturn($order);

        $this->genStoreOwner($delivery);

        $title = '2 pickups -> 2 dropoffs';
        $body = "PUs: 01:02-03:04 | DOs: 05:06-07:08
PU 01:02-02:03: 111 Nice Pickup St, Somewhere, Argentina
PU 03:04-04:05: 222 Nice Pickup St, Somewhere, Argentina
DO 05:06-06:07: 333 Nice Dropoff St, Someplace, Argentina
DO 07:08-08:09: 444 Nice Dropoff St, Someplace, Argentina";
        $data = [
            'event' => ['name' => 'delivery:created'],
            'delivery_id' => 4,
            'order_id' => 11,
            'order_number' => "G",
            'date_local' => 'Today at 1:02 AM',
            'date' => '2025-01-02',
            'time' => '01:02'
        ];

        $this->genPushNotificationAssertEqual($title, $body, $data);

        $message = $this->genDeliveryCreatedMessage($delivery);
        call_user_func_array($this->handler, [ $message ]);
    }

    public function testSendDelivery__DifferentDateThanToday()
    {
        $pickup = new Task();
        $pickup->setType(Task::TYPE_PICKUP);
        $dropoff = new Task();
        $dropoff->setType(Task::TYPE_DROPOFF);
        $pickup->setNext($dropoff);
        $dropoff->setPrevious($pickup);

        $pickupAddress = new Address();
        $pickupAddress->setStreetAddress("111 Nice Pickup St, Somewhere, Argentina");
        $pickup->setAddress($pickupAddress);
        $dropoffAddress = new Address();
        $dropoffAddress->setStreetAddress("222 Nice Dropoff St, Someplace, Argentina");
        $dropoff->setAddress($dropoffAddress);

        $pickup->setAfter(new \DateTime('2025-01-03 01:02:03'));
        $pickup->setBefore(new \DateTime('2025-01-03 02:03:04'));
        $dropoff->setAfter(new \DateTime('2025-01-03 03:04:05'));
        $dropoff->setBefore(new \DateTime('2025-01-03 04:05:06'));

        $delivery = $this->prophesize(Delivery::class);
        $delivery->getId()->willReturn(1);
        $delivery->getTasks()->willReturn([$pickup, $dropoff]);
        $delivery->getPickup()->willReturn($pickup);
        $delivery->getDropoff()->willReturn($dropoff);

        $order = $this->prophesize(Order::class);
        $order->getId()->willReturn(11);
        $order->getNumber()->willReturn("G");
        $order->getDelivery()->willReturn($delivery);
        $delivery->getOrder()->willReturn($order);

        $this->genStoreOwner($delivery);

        $title = '(03 Jan) Test Store -> 222 Nice Dropoff St, Someplace, Argentina';
        $body = "PU: 01:02-02:03 | DO: 03:04-04:05";
        $data = [
            'event' => ['name' => 'delivery:created'],
            'delivery_id' => 1,
            'order_id' => 11,
            'order_number' => "G",
            'date_local' => 'Tomorrow at 1:02 AM',
            'date' => '2025-01-03',
            'time' => '01:02'
        ];

        $this->genPushNotificationAssertEqual($title, $body, $data);

        $message = $this->genDeliveryCreatedMessage($delivery);
        call_user_func_array($this->handler, [ $message ]);
    }

    private function genPushNotificationAssertEqual($title, $body, $data): PushNotification
    {
        $pushNotification = new PushNotification($title, $body, [], $data);

        $this->messageBus
            ->dispatch(Argument::that(function(PushNotification $pn) use ($pushNotification) {
                $this->assertEquals($pushNotification, $pn);
                return true;
            }))
            ->willReturn(new Envelope($pushNotification))
            ->shouldBeCalledOnce();

        return $pushNotification;
    }

    private function genDeliveryCreatedMessage($delivery): DeliveryCreated
    {
        $message = new DeliveryCreated($delivery->reveal());

        $this->deliveryRepository->find($message->getDeliveryId())
            ->willReturn($delivery);

        return $message;
    }

    private function genStoreOwner($delivery, $address = null): Store
    {
        $owner = new Store();
        $owner->setName("Test Store");
        $owner->setAddress($address ?: $delivery->reveal()->getPickup()->getAddress());
        $delivery->getOwner()->willReturn($owner);
        return $owner;
    }
}
