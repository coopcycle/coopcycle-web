<?php

namespace Tests\AppBundle\MessageHandler;

use AppBundle\Entity\ApiUser;
use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Task;
use AppBundle\Entity\TaskList;
use AppBundle\Entity\TaskListRepository;
use AppBundle\Message\Location;
use AppBundle\MessageHandler\CalculateEtaHandler;
use AppBundle\Security\UserManager;
use AppBundle\Service\RoutingInterface;
use Carbon\Carbon;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Predis\Client as Redis;

class CalculateEtaHandlerTest extends TestCase
{
    public function setUp(): void
    {
        $this->userManager = $this->prophesize(UserManager::class);
        $this->taskListRepository = $this->prophesize(TaskListRepository::class);
        $this->entityManager = $this->prophesize(EntityManagerInterface::class);
        $this->routing = $this->prophesize(RoutingInterface::class);
        $this->redis = $this->prophesize(Redis::class);

        $this->entityManager
            ->getRepository(TaskList::class)
            ->willReturn($this->taskListRepository->reveal());

        $this->handler = new CalculateEtaHandler(
            $this->entityManager->reveal(),
            $this->userManager->reveal(),
            $this->routing->reveal(),
            $this->redis->reveal()
        );
    }

    public function tearDown(): void
    {
        Carbon::setTestNow();
    }

    public function testInvoke()
    {
        Carbon::setTestNow(Carbon::parse('2020-02-20 13:00:00'));

        $admin = new ApiUser();
        $admin->setUsername('admin');

        $this->userManager->findUsersByRole('ROLE_ADMIN')->willReturn([ $admin ]);

        $user = new ApiUser();

        $taskList = $this->prophesize(TaskList::class);

        $currentLoc = new GeoCoordinates();

        $coords1 = new GeoCoordinates();
        $coords2 = new GeoCoordinates();

        $address1 = new Address();
        $address2 = new Address();

        $address1->setGeo($coords1);
        $address2->setGeo($coords2);

        $task1 = $this->prophesize(Task::class);
        $task1->getId()->willReturn(1);
        $task1->getAddress()->willReturn($address1);

        $task2 = $this->prophesize(Task::class);
        $task2->getId()->willReturn(2);
        $task2->getAddress()->willReturn($address2);

        $taskList->getTasks()->willReturn([
            $task1, $task2
        ]);

        $this->taskListRepository
            ->findOneByUserAndDate($user, Argument::type(\DateTime::class))
            ->willReturn($taskList->reveal());

        $this->routing
            ->getEtas(
                Argument::type(GeoCoordinates::class),
                $address1->getGeo(),
                $address2->getGeo()
            )
            ->willReturn([ 300, 600 ]);

        $this->redis
            ->set('task:1:eta', Carbon::now()->addSeconds(300)->format(\DateTime::ATOM))
            ->shouldBeCalled();
        $this->redis
            ->set('task:2:eta', Carbon::now()->addSeconds(300 + 600)->format(\DateTime::ATOM))
            ->shouldBeCalled();
        $this->redis
            ->publish('users:admin', json_encode([
                'name' => 'etas',
                'data' => [
                    '/api/tasks/1' => Carbon::now()->addSeconds(300)->format(\DateTime::ATOM),
                    '/api/tasks/2' => Carbon::now()->addSeconds(300 + 600)->format(\DateTime::ATOM),
                ]
            ]))
            ->shouldBeCalled();

        $this->userManager->findUserByUsername('bob')->willReturn($user);

        call_user_func_array($this->handler, [ new Location('bob', [48.8678, 2.3677283]) ]);
    }
}
