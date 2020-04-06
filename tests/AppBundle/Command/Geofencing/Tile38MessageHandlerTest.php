<?php

namespace Tests\AppBundle\Command\Geofencing;

use AppBundle\Command\Geofencing\Tile38MessageHandler;
use AppBundle\Entity\ApiUser;
use AppBundle\Entity\Task;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\OrderRepository;
use AppBundle\Message\PushNotification;
use Doctrine\Persistence\ObjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class Tile38MessageHandlerTest extends TestCase
{
    public function setUp(): void
    {
        $this->taskRepository = $this->prophesize(ObjectRepository::class);
        $this->orderRepository = $this->prophesize(OrderRepository::class);

        $this->entityManager = $this->prophesize(EntityManagerInterface::class);

        $this->messageBus = $this->prophesize(MessageBusInterface::class);
        $this->messageBus
            ->dispatch(Argument::type(PushNotification::class))
            ->will(function ($args) {
                return new Envelope($args[0]);
            });

        $this->translator = $this->prophesize(TranslatorInterface::class);

        $this->entityManager
            ->getRepository(Task::class)
            ->willReturn($this->taskRepository->reveal());
        $this->entityManager
            ->getRepository(Order::class)
            ->willReturn($this->orderRepository->reveal());

        $this->handler = new Tile38MessageHandler(
            'coopcycle',
            $this->entityManager->reveal(),
            $this->messageBus->reveal(),
            $this->translator->reveal()
        );
    }

    private static function createMessage(string $messenger, int $taskId)
    {
        $pmessage = new \stdClass();

        $pmessage->channel = sprintf('coopcycle:dropoff:%d', $taskId);
        $pmessage->payload = json_encode([
            'id' => $messenger,
            'hook' => sprintf('coopcycle:dropoff:%d', $taskId),
        ]);

        return $pmessage;

        // (
        //     [kind] => pmessage
        //     [channel] => coopcycle:dropoff:7395
        //     [payload] => {
        //         "command":"set",
        //         "group":"5e78da00fdee2e0001356871",
        //         "detect":"enter",
        //         "hook":"coopcycle:dropoff:7395",
        //         "key":"coopcycle:fleet",
        //         "time":"2020-03-23T15:47:12.1482893Z",
        //         "id":"bot_2",
        //         "object":{"type":"Point","coordinates":[2.3184081,48.8554067]}
        //     }
        // )
    }

    public function testMessageWithAnotherMessenger()
    {
        $sarah = new ApiUser();
        $sarah->setUsername('sarah');

        $taskId = 1;

        $task = $this->prophesize(Task::class);
        $task->getAssignedCourier()->willReturn($sarah);

        $this->taskRepository
            ->find($taskId)
            ->willReturn($task->reveal());

        $message = self::createMessage('bob', $taskId);

        $this->messageBus->dispatch(Argument::any())->shouldNotBeCalled();

        call_user_func_array($this->handler, [ $message ]);
    }

    public function testMessageWithNoAssociatedOrder()
    {
        $sarah = new ApiUser();
        $sarah->setUsername('sarah');

        $taskId = 1;

        $task = $this->prophesize(Task::class);
        $task->getAssignedCourier()->willReturn($sarah);

        $this->taskRepository
            ->find($taskId)
            ->willReturn($task->reveal());

        $this->orderRepository
            ->findOneByTask($task->reveal())
            ->willReturn(null);

        $message = self::createMessage('sarah', $taskId);

        $this->messageBus->dispatch(Argument::any())->shouldNotBeCalled();

        call_user_func_array($this->handler, [ $message ]);
    }

    public function testMessageSendsNotification()
    {
        $sarah = new ApiUser();
        $sarah->setUsername('sarah');

        $fred = new ApiUser();
        $fred->setUsername('fred');

        $taskId = 1;

        $task = $this->prophesize(Task::class);
        $task->getAssignedCourier()->willReturn($sarah);

        $this->taskRepository
            ->find($taskId)
            ->willReturn($task->reveal());

        $order = $this->prophesize(Order::class);
        $order->getCustomer()->willReturn($fred);

        $this->orderRepository
            ->findOneByTask($task->reveal())
            ->willReturn($order->reveal());

        $message = self::createMessage('sarah', $taskId);

        $this->translator
            ->trans(Argument::type('string'), Argument::type('array'))
            ->willReturn('Hello');

        call_user_func_array($this->handler, [ $message ]);

        $this->messageBus
            ->dispatch(new PushNotification('Hello', [ 'fred' ]))
            ->shouldHaveBeenCalled();
    }
}
