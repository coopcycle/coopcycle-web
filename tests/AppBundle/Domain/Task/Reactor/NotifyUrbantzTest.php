<?php

namespace Tests\AppBundle\Domain\Task\Reactor;

use AppBundle\Domain\Task\Event as TaskEvent;
use AppBundle\Domain\Task\Event\TaskAssigned;
use AppBundle\Domain\Task\Event\TaskDone;
use AppBundle\Domain\Task\Event\TaskFailed;
use AppBundle\Domain\Task\Reactor\NotifyUrbantz;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Task;
use AppBundle\Entity\Urbantz\Delivery as UrbantzDelivery;
use AppBundle\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class NotifyUrbantzTest extends TestCase
{
    use ProphecyTrait;

    private $notifyUrbantz;
    private $urbantzBaseUri = 'https://api.urbantz.com/v2/';

    public function setUp(): void
    {
        $this->mockResponse = new MockResponse('', ['http_code' => 200]);
        $this->client = new MockHttpClient($this->mockResponse, $this->urbantzBaseUri);

        $this->entityManager = $this->prophesize(EntityManagerInterface::class);

        $this->urbantzDeliveryRepository = $this->prophesize(ObjectRepository::class);

        $this->entityManager
            ->getRepository(UrbantzDelivery::class)
            ->willReturn($this->urbantzDeliveryRepository->reveal());

        $this->notifyUrbantz = new NotifyUrbantz(
            $this->client,
            $this->entityManager->reveal(),
            '1234567890'
        );
    }

    public function testDoesNothingWithStandaloneTask()
    {
        $task = new Task();
        $user = new User();

        call_user_func_array($this->notifyUrbantz, [ new TaskAssigned($task, $user) ]);

        $this->assertEquals(0, $this->client->getRequestsCount());
    }

    public function sendRequestToUrbantzProvider()
    {
        $dropoff = new Task();
        $dropoff->setType(Task::TYPE_DROPOFF);

        $pickup = new Task();
        $pickup->setType(Task::TYPE_PICKUP);

        $user = new User();

        $delivery = $this->prophesize(Delivery::class);
        $delivery->getId()->willReturn(1);

        $dropoff->setDelivery($delivery->reveal());
        $pickup->setDelivery($delivery->reveal());

        return [
            [
                $delivery->reveal(),
                $event = new TaskAssigned($dropoff, $user),
                1,
                'carrier/external/task/dlv_g01RzjwPbJ4qDpLKPYVWN3dvoQO5rM8K/assign'
            ],
            [
                $delivery->reveal(),
                $event = new TaskDone($dropoff),
                1,
                'carrier/external/task/dlv_g01RzjwPbJ4qDpLKPYVWN3dvoQO5rM8K/complete'
            ],
            [
                $delivery->reveal(),
                $event = new TaskAssigned($pickup, $user),
                0,
            ],
            [
                $delivery->reveal(),
                $event = new TaskDone($pickup),
                1,
                'carrier/external/task/dlv_g01RzjwPbJ4qDpLKPYVWN3dvoQO5rM8K/start'
            ],
        ];
    }

    /**
     * @dataProvider sendRequestToUrbantzProvider
     */
    public function testSendsRequestToUrbantz(Delivery $delivery, TaskEvent $event,
        int $expectedRequestsCount,
        string $expectedPath = '')
    {
        $urbantzDelivery = new UrbantzDelivery();

        $this->urbantzDeliveryRepository
            ->findOneBy(['delivery' => $delivery])
            ->willReturn($urbantzDelivery);

        call_user_func_array($this->notifyUrbantz, [ $event ]);

        $this->assertEquals($expectedRequestsCount, $this->client->getRequestsCount());

        if ($expectedRequestsCount > 0) {

            $this->assertEquals(
                $this->urbantzBaseUri . $expectedPath,
                $this->mockResponse->getRequestUrl()
            );

            $body = $this->mockResponse->getRequestOptions()['body'];
        }
    }
}
