<?php

namespace Tests\AppBundle\MessageHandler\Delivery;

use AppBundle\Entity\Address;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Store;
use AppBundle\Entity\Task;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Message\DeliveriesCreated;
use AppBundle\Message\PushNotification;
use AppBundle\MessageHandler\DeliveriesCreatedHandler;
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

class DeliveriesCreatedHandlerTest extends TestCase
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
            ->willReturn([]);

        $this->settingsManager->get('administrator_email')
            ->willReturn('admin@demo.coopcycle.org');

        $this->translator->trans('notifications.tap_to_open')
            ->willReturn('Tap to open');

        $this->messageBus->dispatch(Argument::any())
            ->will(fn ($args) => new Envelope($args[0]));

        $this->mjml->render(Argument::any())->willReturn('<html></html>');
        $this->twig->render(Argument::cetera())->willReturn('<mjml></mjml>');
        $this->emailManager->createHtmlMessage(Argument::cetera())->willReturn(new Email());
        $this->emailManager->sendTo(Argument::cetera())->willReturn(null);

        $this->handler = new DeliveriesCreatedHandler(
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

    private function createDelivery(int $id, string $pickupAfter, array $taskIds): Delivery
    {
        [$pickupId, $dropoffId] = $taskIds;

        $address = new Address();
        $address->setStreetAddress('222 Nice Dropoff St, Someplace, Argentina');

        $pickup = $this->prophesize(Task::class);
        $pickup->getId()->willReturn($pickupId);
        $pickup->getAfter()->willReturn(new \DateTime($pickupAfter));

        $dropoff = $this->prophesize(Task::class);
        $dropoff->getId()->willReturn($dropoffId);
        $dropoff->getAddress()->willReturn($address);

        $store = new Store();
        $store->setName('Test Store');

        $order = $this->prophesize(Order::class);
        $order->getNumber()->willReturn('ABC');

        $delivery = $this->prophesize(Delivery::class);
        $delivery->getId()->willReturn($id);
        $delivery->getPickup()->willReturn($pickup->reveal());
        $delivery->getDropoff()->willReturn($dropoff->reveal());
        $delivery->getTasks()->willReturn([$pickup->reveal(), $dropoff->reveal()]);
        $delivery->getOwner()->willReturn($store);
        $delivery->getOrder()->willReturn($order->reveal());

        return $delivery->reveal();
    }

    public function testSendsOnePushNotificationAndOneEmailForTheWholeBatch()
    {
        $deliveries = [
            $this->createDelivery(1, '2025-01-03 10:00:00', [1, 2]),
            $this->createDelivery(2, '2025-01-03 11:00:00', [3, 4]),
        ];

        $this->deliveryRepository->findBy(['id' => [1, 2]])->willReturn($deliveries);

        $this->translator
            ->trans('notifications.deliveries_created', [
                '%count%' => 2,
                '%date%'  => 'tomorrow at 10:00 am',
            ])
            ->willReturn('2 deliveries to be picked up tomorrow at 10:00 AM have been created');

        $message = $this->prophesize(DeliveriesCreated::class);
        $message->getDeliveryIds()->willReturn([1, 2]);

        $this->handler->__invoke($message->reveal());

        $this->messageBus
            ->dispatch(Argument::that(function ($notification) {
                if (!$notification instanceof PushNotification) {
                    return false;
                }

                $data = $notification->getData();

                return '2 deliveries to be picked up tomorrow at 10:00 AM have been created' === $notification->getTitle()
                    && 'deliveries:created' === $data['event']['name']
                    && [1, 2, 3, 4] === $data['task_ids']
                    && [1, 2] === $data['delivery_ids']
                    && '2025-01-03' === $data['date'];
            }))
            ->shouldHaveBeenCalledTimes(1);

        $this->emailManager
            ->sendTo(Argument::type(Email::class), 'admin@demo.coopcycle.org')
            ->shouldHaveBeenCalledTimes(1);
    }

    public function testUsesAGenericRecapWhenDeliveriesAreOnDifferentDays()
    {
        $deliveries = [
            $this->createDelivery(1, '2025-01-03 10:00:00', [1, 2]),
            $this->createDelivery(2, '2025-01-04 11:00:00', [3, 4]),
        ];

        $this->deliveryRepository->findBy(['id' => [1, 2]])->willReturn($deliveries);

        $this->translator
            ->trans('notifications.deliveries_created.no_date', ['%count%' => 2])
            ->willReturn('2 deliveries have been created');

        $message = $this->prophesize(DeliveriesCreated::class);
        $message->getDeliveryIds()->willReturn([1, 2]);

        $this->handler->__invoke($message->reveal());

        $this->messageBus
            ->dispatch(Argument::that(function ($notification) {
                return $notification instanceof PushNotification
                    && '2 deliveries have been created' === $notification->getTitle()
                    && !isset($notification->getData()['date']);
            }))
            ->shouldHaveBeenCalledTimes(1);
    }
}
