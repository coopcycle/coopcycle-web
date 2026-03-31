<?php

namespace Tests\AppBundle\MessageHandler\Order;

use AppBundle\Domain\Order\Event;
use AppBundle\Entity\Task;
use AppBundle\Entity\User;
use AppBundle\Message\TopBarNotification;
use AppBundle\MessageHandler\Order\PublishLiveUpdate;
use AppBundle\Security\UserManager;
use AppBundle\Service\LiveUpdates;
use AppBundle\Service\NotificationPreferences;
use AppBundle\Sylius\Customer\CustomerInterface;
use AppBundle\Sylius\Order\OrderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use phpcent\Client as CentrifugoClient;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\NullLogger;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Serializer;
use Symfony\Contracts\Translation\TranslatorInterface;

class PublishLiveUpdateTest extends TestCase
{
    use ProphecyTrait;

    private $security;
    private $entityManager;
    private $taskRepository;

    private PublishLiveUpdate $handler;

    public function setUp(): void
    {
        $this->security = $this->prophesize(Security::class);
        $this->userManager = $this->prophesize(UserManager::class);
        $this->serializer = $this->prophesize(Serializer::class);
        $this->translator = $this->prophesize(TranslatorInterface::class);
        $this->centrifugoClient = $this->prophesize(CentrifugoClient::class);
        $this->messageBus = $this->prophesize(MessageBusInterface::class);
        $this->notificationPreferences = $this->prophesize(NotificationPreferences::class);

        $liveUpdates = new LiveUpdates(
            $this->security->reveal(),
            $this->userManager->reveal(),
            $this->serializer->reveal(),
            $this->translator->reveal(),
            $this->centrifugoClient->reveal(),
            $this->messageBus->reveal(),
            $this->notificationPreferences->reveal(),
            new NullLogger(),
            'foo'
        );

        $this->handler = new PublishLiveUpdate(
            $liveUpdates
        );
    }

    public function eventsProvider()
    {
        return [
            [ Event\EmailSent::class, false ],
            [ Event\OrderAccepted::class, true ],
            [ Event\OrderFulfilled::class, true ],
        ];
    }

    /**
     * @dataProvider eventsProvider
     */
    public function testEmailSent(string $eventClass, bool $shouldAddTopBarNotification): void
    {
        $customer = $this->prophesize(CustomerInterface::class);
        $user = $this->prophesize(UserInterface::class);

        $user->getUserIdentifier()->willReturn('bob');

        $customer->hasUser()->willReturn(true);
        $customer->getUser()->willReturn($user->reveal());

        $order = $this->prophesize(OrderInterface::class);
        $order->getCustomer()->willReturn($customer->reveal());
        $order->hasVendor()->willReturn(true);
        $order->isMultiVendor()->willReturn(false);
        $order->getNotificationRecipients()->willReturn([]);
        $order->getId()->willReturn(1);

        $this->notificationPreferences->isEventEnabled(Argument::type('string'))->willReturn(true);

        $admin = $this->prophesize(UserInterface::class);
        $admin->getUserIdentifier()->willReturn('admin');

        $this->userManager->findUsersByRoles(['ROLE_ADMIN'])->willReturn([$admin]);

        $this->translator->trans(Argument::type('string'), Argument::type('array'))->willReturn('Lorem ipsum');

        if ($shouldAddTopBarNotification) {

            $this->messageBus->dispatch(
                new TopBarNotification(['admin', 'bob'], 'Lorem ipsum')
            )->will(function ($args) {
                return new Envelope($args[0]);
            })->shouldBeCalled();

        } else {

            $this->messageBus->dispatch(
                new TopBarNotification(['admin'], 'Lorem ipsum')
            )->will(function ($args) {
                return new Envelope($args[0]);
            })->shouldBeCalled();
        }

        ($this->handler)(new $eventClass($order->reveal(), 'foo@coopcycle.local'));
    }

}
