<?php

namespace Tests\AppBundle\Domain\Task\Reactor;

use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Domain\Task\Event;
use AppBundle\Domain\Task\Reactor\TriggerWebhook;
use AppBundle\Entity\Delivery;
// use AppBundle\Entity\Store;
use AppBundle\Entity\Task;
use AppBundle\Entity\User;
use AppBundle\Entity\Woopit\Delivery as WoopitDelivery;
use AppBundle\Message\Webhook;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class TriggerWebhookTest extends TestCase
{
    use ProphecyTrait;

    public function setUp(): void
    {
        $this->messageBus = $this->prophesize(MessageBusInterface::class);
        $this->messageBus
            ->dispatch(Argument::type('object'))
            ->will(function ($args) {
                return new Envelope($args[0]);
            });

        $this->iriConverter = $this->prophesize(IriConverterInterface::class);
        $this->iriConverter
            ->getIriFromItem(Argument::type(Delivery::class))
            ->willReturn('/api/deliveries/1');

        $this->woopitDeliveryRepository = $this->prophesize(ObjectRepository::class);

        $this->entityManager = $this->prophesize(EntityManagerInterface::class);
        $this->entityManager
            ->getRepository(WoopitDelivery::class)
            ->willReturn($this->woopitDeliveryRepository->reveal());

        $this->triggerWebhook = new TriggerWebhook(
            $this->messageBus->reveal(),
            $this->iriConverter->reveal(),
            $this->entityManager->reveal()
        );
    }

    public function triggersExpectedEventProvider()
    {
        $delivery = new Delivery();

        $task = new Task();
        $task->setDelivery($delivery);

        $user = new User();

        return [
            [
                $task,
                Task::TYPE_DROPOFF,
                new Event\TaskAssigned($task, $user),
                'delivery.assigned'
            ],
            [
                $task,
                Task::TYPE_PICKUP,
                new Event\TaskStarted($task),
                'delivery.started'
            ],
            [
                $task,
                Task::TYPE_PICKUP,
                new Event\TaskDone($task),
                'delivery.picked'
            ],
            [
                $task,
                Task::TYPE_DROPOFF,
                new Event\TaskDone($task),
                'delivery.completed'
            ],
            [
                $task,
                Task::TYPE_PICKUP,
                new Event\TaskFailed($task),
                'delivery.failed'
            ],
            [
                $task,
                Task::TYPE_DROPOFF,
                new Event\TaskFailed($task),
                'delivery.failed'
            ],
        ];
    }

    /**
     * @dataProvider triggersExpectedEventProvider
     */
    public function testTriggersExpectedEvent(Task $task, $taskType, Event $event, $expectedEventName)
    {
        $task->setType($taskType);

        call_user_func_array($this->triggerWebhook, [ $event ]);

        $this
            ->messageBus
            ->dispatch(new Webhook('/api/deliveries/1', $expectedEventName))
            ->shouldHaveBeenCalled();
    }
}
