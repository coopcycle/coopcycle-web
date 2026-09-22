<?php

namespace Tests\AppBundle\MessageHandler\Delivery;

use AppBundle\Entity\Address;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Store;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Task;
use AppBundle\Message\DeliveryCancelled;
use AppBundle\Message\DeliveryUpdated;
use AppBundle\Message\PushNotification;
use AppBundle\MessageHandler\DeliveryChangedHandler;
use AppBundle\Security\UserManager;
use AppBundle\Service\EmailManager;
use AppBundle\Service\SettingsManager;
use Carbon\Carbon;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use NotFloran\MjmlBundle\Renderer\RendererInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment as TwigEnvironment;

class DeliveryChangedHandlerTest extends TestCase
{
    use ProphecyTrait;

    public function setUp(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 1, 2, 0));

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
            ->willReturn(['admin', 'dispatcher']);

        $this->translator->trans(Argument::type('string'), Argument::any())
            ->will(fn ($args) => $args[0]);
        $this->translator->trans('notifications.tap_to_open')
            ->willReturn('Tap to open');

        $this->handler = new DeliveryChangedHandler(
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

    public function tearDown(): void
    {
        Carbon::setTestNow();
    }

    private function createDelivery(bool $withOrder = true)
    {
        $pickup = new Task();
        $pickup->setType(Task::TYPE_PICKUP);
        $pickup->setAddress((new Address())->setStreetAddress('111 Pickup St'));
        $pickup->setAfter(new \DateTime('2025-01-02 10:00:00'));
        $pickup->setBefore(new \DateTime('2025-01-02 11:00:00'));

        $dropoff = new Task();
        $dropoff->setType(Task::TYPE_DROPOFF);
        $dropoff->setAddress((new Address())->setStreetAddress('222 Dropoff St'));
        $dropoff->setAfter(new \DateTime('2025-01-02 12:00:00'));
        $dropoff->setBefore(new \DateTime('2025-01-02 13:00:00'));

        $store = new Store();
        $store->setName('Acme');

        $delivery = $this->prophesize(Delivery::class);
        $delivery->getId()->willReturn(7);
        $delivery->getTasks()->willReturn([$pickup, $dropoff]);
        $delivery->getPickup()->willReturn($pickup);
        $delivery->getStore()->willReturn($store);

        if ($withOrder) {
            $order = $this->prophesize(Order::class);
            $order->getId()->willReturn(11);
            $order->getNumber()->willReturn('ABC');
            $delivery->getOrder()->willReturn($order->reveal());
        } else {
            $delivery->getOrder()->willReturn(null);
        }

        $this->deliveryRepository->find(7)->willReturn($delivery->reveal());

        return $delivery;
    }

    private function expectPushNotification(string $title, string $body, string $eventName): void
    {
        $this->messageBus
            ->dispatch(Argument::that(function (PushNotification $pn) use ($title, $body, $eventName) {
                $this->assertEquals($title, $pn->getTitle());
                $this->assertEquals($body, $pn->getBody());
                $this->assertEquals(['admin', 'dispatcher'], $pn->getUsers());
                $this->assertEquals(['name' => $eventName], $pn->getData()['event']);
                $this->assertEquals(7, $pn->getData()['delivery_id']);
                $this->assertEquals('2025-01-02', $pn->getData()['date']);
                $this->assertEquals('10:00', $pn->getData()['time']);
                return true;
            }))
            ->will(fn ($args) => new Envelope($args[0]))
            ->shouldBeCalledOnce();
    }

    public function testDeliveryUpdatedSendsPushAndEmail()
    {
        $delivery = $this->createDelivery();

        $this->settingsManager->get('administrator_email')->willReturn('admin@example.com');

        $this->translator->trans('notifications.delivery_updated', [
            '%store%' => 'Acme',
            '%date%' => 'today at 10:00 am',
        ])->willReturn('Delivery modified by Acme');
        $this->translator->trans('notifications.delivery_changed.order', ['%number%' => 'ABC'])
            ->willReturn('Order ABC');

        $this->expectPushNotification('Delivery modified by Acme', 'Order ABC', 'delivery:updated');

        $this->twig->render('emails/delivery/changed.mjml.twig', Argument::that(function ($params) use ($delivery) {
            $this->assertEquals('Delivery modified by Acme', $params['body']);
            $this->assertEquals('updated', $params['change']);
            $this->assertSame($delivery->reveal(), $params['delivery']);
            return true;
        }))->willReturn('<mjml/>')->shouldBeCalledOnce();
        $this->mjml->render('<mjml/>')->willReturn('<html/>');

        $email = new Email();
        $this->emailManager->createHtmlMessage('Delivery modified by Acme', '<html/>')
            ->willReturn($email)
            ->shouldBeCalledOnce();
        $this->emailManager->sendTo($email, 'admin@example.com')->shouldBeCalledOnce();

        $this->handler->onDeliveryUpdated(new DeliveryUpdated($delivery->reveal()));
    }

    public function testDeliveryCancelledWithoutAdminEmailOnlySendsPush()
    {
        $delivery = $this->createDelivery(withOrder: false);

        $this->settingsManager->get('administrator_email')->willReturn(null);

        $this->translator->trans('notifications.delivery_cancelled', [
            '%store%' => 'Acme',
            '%date%' => 'today at 10:00 am',
        ])->willReturn('Delivery cancelled by Acme');

        $this->expectPushNotification('Delivery cancelled by Acme', 'Tap to open', 'delivery:cancelled');

        $this->emailManager->sendTo(Argument::cetera())->shouldNotBeCalled();

        $this->handler->onDeliveryCancelled(new DeliveryCancelled($delivery->reveal()));
    }

    public function testUnknownDeliveryIsIgnored()
    {
        $this->deliveryRepository->find(42)->willReturn(null);

        $delivery = $this->prophesize(Delivery::class);
        $delivery->getId()->willReturn(42);

        $this->messageBus->dispatch(Argument::cetera())->shouldNotBeCalled();

        $this->handler->onDeliveryUpdated(new DeliveryUpdated($delivery->reveal()));
    }
}
