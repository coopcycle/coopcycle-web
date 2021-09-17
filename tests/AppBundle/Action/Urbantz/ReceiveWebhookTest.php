<?php

namespace Tests\AppBundle\Action\Urbantz;

use AppBundle\Action\Urbantz\ReceiveWebhook;
use AppBundle\Api\Resource\UrbantzWebhook;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\DeliveryRepository;
use AppBundle\Service\TaskManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

class ReceiveWebhookTest extends TestCase
{
    use ProphecyTrait;

    public function setUp(): void
    {
        $this->deliveryRepository = $this->prophesize(DeliveryRepository::class);
        $this->taskManager = $this->prophesize(TaskManager::class);
        $this->entityManager = $this->prophesize(EntityManagerInterface::class);

        $this->action = new ReceiveWebhook(
            $this->deliveryRepository->reveal(),
            $this->taskManager->reveal(),
            $this->entityManager->reveal()
        );
    }

    public function testDiscardedTask()
    {
        $webhook = new UrbantzWebhook(UrbantzWebhook::TASK_CHANGED);
        $webhook->tasks = [
            [
                'extTrackId' => 'dlv_12345678',
                'progress' => 'DISCARDED',
            ]
        ];

        $delivery = new Delivery();

        $this->deliveryRepository
            ->findOneByHashId('dlv_12345678')
            ->willReturn($delivery);

        $response = call_user_func_array($this->action, [$webhook]);

        $this->assertSame($webhook, $response);

        $this->taskManager->cancel($delivery->getPickup())
            ->shouldHaveBeenCalled();

        $this->taskManager->cancel($delivery->getDropoff())
            ->shouldHaveBeenCalled();

        $this->entityManager->flush()
            ->shouldHaveBeenCalled();
    }
}
