<?php

namespace Tests\AppBundle\Action\Urbantz;

use AppBundle\Action\Urbantz\ReceiveWebhook;
use AppBundle\Api\Resource\UrbantzWebhook;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\DeliveryRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

class ReceiveWebhookTest extends TestCase
{
    use ProphecyTrait;

    public function setUp(): void
    {
        $this->deliveryRepository = $this->prophesize(DeliveryRepository::class);

        $this->action = new ReceiveWebhook(
            $this->deliveryRepository->reveal()
        );
    }

    public function testEmptyTemplate()
    {
        $webhook = new UrbantzWebhook(UrbantzWebhook::TASK_CHANGED);
        $webhook->tasks = [
            [
                'extTrackId' => 'dlv_12345678'
            ]
        ];

        $delivery = new Delivery();

        $this->deliveryRepository
            ->findOneByHashId('dlv_12345678')
            ->willReturn($delivery);

        $response = call_user_func_array($this->action, [$webhook]);

        $this->assertSame($webhook, $response);
    }
}
