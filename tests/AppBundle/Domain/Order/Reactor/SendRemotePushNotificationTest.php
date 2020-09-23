<?php

namespace Tests\AppBundle\Domain\Order\Reactor;

use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Domain\Order\Event;
use AppBundle\Domain\Order\Reactor\SendRemotePushNotification;
use AppBundle\Entity\User;
use AppBundle\Entity\Restaurant;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Message\PushNotification;
use AppBundle\Security\UserManager;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Translation\TranslatorInterface;

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
        $serializer = self::$container->get(SerializerInterface::class);
        $iriConverter = self::$container->get(IriConverterInterface::class);

        $this->messageBus = $this->prophesize(MessageBusInterface::class);

        $this->messageBus
            ->dispatch(Argument::type(PushNotification::class))
            ->will(function ($args) {
                return new Envelope($args[0]);
            });

        $admin = new User();
        $admin->setUsername('admin');

        $this->userManager = $this->prophesize(UserManager::class);
        $this->userManager->findUsersByRole('ROLE_ADMIN')
            ->willReturn([ $admin ]);

        $this->translator = $this->prophesize(TranslatorInterface::class);

        $this->reactor = new SendRemotePushNotification(
            $this->userManager->reveal(),
            $this->messageBus->reveal(),
            $iriConverter,
            $serializer,
            $this->translator->reveal()
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

        $order = new Order();

        $restaurant = new Restaurant();
        $restaurant->setName('Foo');
        $restaurant->addOwner($owner);

        $order->setRestaurant($restaurant);
        $order->setShippedAt(new \DateTime('2020-05-10 12:30:00'));

        $this->setId($restaurant, 1);
        $this->setId($order, 1);

        $this->translator->trans('notifications.restaurant.new_order')->willReturn('New order!');

        call_user_func_array($this->reactor, [ new Event\OrderCreated($order) ]);

        $this
            ->messageBus
            ->dispatch(new PushNotification(
                'New order!',
                [ 'admin' ],
            ))
            ->shouldHaveBeenCalledTimes(1);

        $this
            ->messageBus
            ->dispatch(new PushNotification(
                'New order!',
                [ 'bob' ],
                [
                    'event' => [
                        'name' => 'order:created',
                        'data' => [
                            'restaurant' => [
                                '@id' => '/api/restaurants/1',
                                'name' => 'Foo'
                            ],
                            'date' => '2020-05-10',
                            'order' => '/api/orders/1',
                        ]
                    ]
                ]
            ))
            ->shouldHaveBeenCalledTimes(1);
    }
}
