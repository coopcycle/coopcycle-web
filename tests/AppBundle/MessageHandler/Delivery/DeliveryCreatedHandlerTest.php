<?php

namespace Tests\AppBundle\MessageHandler\Delivery;

use AppBundle\Entity\Address;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Store;
use AppBundle\Entity\Task;
use AppBundle\Entity\User;
use AppBundle\Entity\Sylius\Customer;
use AppBundle\Message\DeliveryCreated;
use AppBundle\Message\PushNotification;
use AppBundle\MessageHandler\DeliveryCreatedHandler;
use AppBundle\Service\EmailManager;
use AppBundle\Service\SettingsManager;
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

    /**
     * @group only
     */
    public function testSendDeliveryTypeSimple()
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

        $pickupAfter = new \DateTime('2025-01-02 01:02:03');
        $pickupBefore = new \DateTime('2025-01-02 02:03:04');
        $pickup->setAfter($pickupAfter);
        $pickup->setBefore($pickupBefore);
        $dropoffAfter = new \DateTime('2025-01-02 03:04:05');
        $dropoffBefore = new \DateTime('2025-01-02 04:05:06');
        $dropoff->setAfter($dropoffAfter);
        $dropoff->setBefore($dropoffBefore);

        $delivery = $this->prophesize(Delivery::class);
        $delivery->getId()->willReturn(1);
        $delivery->getOrder()->willReturn(null);
        $delivery->getTasks()->willReturn([$pickup, $dropoff]);
        $delivery->getPickup()->willReturn($pickup);
        $delivery->getDropoff()->willReturn($dropoff);

        $owner = $this->genStoreOwner($delivery);
        //$user = $this->genCustomerUser($pickupAddress);

        $title = $owner->getName().' -> ' . $dropoffAddress->getStreetAddress();
        $body = "PU: 01:02-02:03 | DO: 03:04-04:05";
        //$users = $this->mockUsers([new User(), new User()]);
        $data = [
            'event' => 'delivery:created',
            'delivery_id' => 1,
            'order_id' => null,
            'date' => '2025-01-02 01:02',
            'date_local' => '01/02/2025'
        ];

        $this->genPushNotification($title, $body, $data);

        $message = $this->genDeliveryCreatedMessage($delivery);
        call_user_func_array($this->handler, [ $message ]);
    }

    // private function mockUsers($users): array
    // {
    //     $this->userManager->findUsersByRoles(['ROLE_ADMIN', 'ROLE_DISPATCHER'])
    //         ->willReturn($users);

    //     return $users;
    // }

    private function genPushNotification($title, $body, $data): PushNotification
    {
        $pushNotification = new PushNotification($title, $body, [], $data);

        $this->messageBus
            ->dispatch(Argument::that(function(PushNotification $pn) use ($pushNotification) {
                $this->assertEquals($pn, $pushNotification);
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

    // private function genCustomerUser($address): User
    // {
    //     $user = new User();
    //     $user->setCustomer(new Customer());
    //     $user->setUsername('bob');
    //     $user->addAddress($address);
    //     $this->userManager->findUserByUsername('bob')
    //         ->willReturn($user);
    //     return $user;
    // }
}
