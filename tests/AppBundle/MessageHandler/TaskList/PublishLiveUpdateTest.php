<?php

namespace Tests\AppBundle\MessageHandler\TaskList;

use AppBundle\Domain\TaskList\Event\TaskListUpdated;
use AppBundle\Domain\TaskList\Event\TaskListUpdatedv2;
use AppBundle\MessageHandler\TaskList\PublishLiveUpdate;
use AppBundle\Entity\TaskList;
use AppBundle\Service\LiveUpdates;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use AppBundle\Entity\User;

class PublishLiveUpdateTest extends TestCase
{
    use ProphecyTrait;

    private PublishLiveUpdate $publishLiveUpdate;
    private $liveUpdates;

    public function setUp(): void
    {
        $this->liveUpdates = $this->prophesize(LiveUpdates::class);
        $this->publishLiveUpdate = new PublishLiveUpdate(
            $this->liveUpdates->reveal()
        );
    }

    public function testTaskListUpdated()
    {
        $user = $this->prophesize(User::class);
        $event = $this->prophesize(TaskListUpdated::class);

        $event->getCourier()->willReturn($user->reveal());

        $this->liveUpdates->toUsers(
            [$user->reveal()], $event->reveal()
        )->shouldBeCalledOnce();

        ($this->publishLiveUpdate)($event->reveal());
    }

    public function testTaskListUpdatedv2()
    {
        $user = $this->prophesize(User::class);
        $event = $this->prophesize(TaskListUpdatedv2::class);
        $taskList = $this->prophesize(TaskList::class);


        $taskList->getCourier()->willReturn($user->reveal());
        $event->getTaskList()->willReturn($taskList->reveal());

        $this->liveUpdates->toUserAndRoles(
            $user->reveal(), ['ROLE_ADMIN', 'ROLE_DISPATCHER'], $event->reveal()
        )->shouldBeCalledOnce();

        ($this->publishLiveUpdate)($event->reveal());
    }
}
