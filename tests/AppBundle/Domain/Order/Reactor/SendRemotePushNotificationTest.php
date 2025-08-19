<?php

namespace Tests\AppBundle\MessageHandler\Order;

use ApiPlatform\Api\IriConverterInterface;
use AppBundle\Domain\Order\Event;
use AppBundle\MessageHandler\Order\SendRemotePushNotification;
use AppBundle\Entity\User;
use AppBundle\Entity\Restaurant;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Message\PushNotification;
use AppBundle\Security\UserManager;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class SendRemotePushNotificationTest extends KernelTestCase
{
    use ProphecyTrait;

    private $eventBus;
    private $reactor;

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        // @see https://symfony.com/blog/new-in-symfony-4-1-simpler-service-testing
        $iriConverter = self::getContainer()->get(IriConverterInterface::class);

        $this->messageBus = $this->prophesize(MessageBusInterface::class);

        $this->messageBus
            ->dispatch(Argument::type(PushNotification::class))
            ->will(function ($args) {
                return new Envelope($args[0]);
            });

        $this->admin = new User();
        $this->admin->setUsername('admin');

        $this->userManager = $this->prophesize(UserManager::class);
        $this->userManager->findUsersByRoles(['ROLE_ADMIN', 'ROLE_DISPATCHER'])
            ->willReturn([ $this->admin ]);

        $this->translator = $this->prophesize(TranslatorInterface::class);

        $this->reactor = new SendRemotePushNotification(
            $this->userManager->reveal(),
            $this->messageBus->reveal(),
            $iriConverter,
            $this->translator->reveal(),
            new NullLogger()
        );
    }

    private function setId($object, $id)
    {
        $property = new \ReflectionProperty($object, 'id');
        $property->setAccessible(true);
        $property->setValue($object, 1);
    }

    public function testSendsNotification()
    {
        $owner = new User();
        $owner->setUsername('bob');
        $this->userManager->findUserByUsername('bob')
            ->willReturn($owner);

        $order = new Order();

        $restaurant = new Restaurant();
        $restaurant->setName('Foo');
        $restaurant->addOwner($owner);

        $order->setRestaurant($restaurant);
        $order->addRestaurant($restaurant, 1000, 0);

        $this->setId($restaurant, 1);
        $this->setId($order, 1);

        $this->translator->trans('notifications.restaurant.new_order')->willReturn('New order!');

        call_user_func_array($this->reactor, [ new Event\OrderCreated($order) ]);

        $this
            ->messageBus
            ->dispatch(new PushNotification(
                'New order!',
                '',
                [ $this->admin ],
            ))
            ->shouldHaveBeenCalledTimes(1);

        $this
            ->messageBus
            ->dispatch(new PushNotification(
                'New order!',
                '',
                [ $owner ],
                [
                    'event' => [
                        'name' => 'order:created',
                        'data' => [
                            'order' => '/api/orders/1',
                        ]
                    ]
                ]
            ))
            ->shouldHaveBeenCalledTimes(1);
    }
}
