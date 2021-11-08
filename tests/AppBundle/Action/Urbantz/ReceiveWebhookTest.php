<?php

namespace Tests\AppBundle\Action\Urbantz;

use AppBundle\Action\Urbantz\ReceiveWebhook;
use AppBundle\Api\Resource\UrbantzWebhook;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\DeliveryRepository;
use AppBundle\Service\DeliveryManager;
use AppBundle\Service\TaskManager;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use libphonenumber\PhoneNumberUtil;

class ReceiveWebhookTest extends TestCase
{
    use ProphecyTrait;

    public function setUp(): void
    {
        $this->deliveryRepository = $this->prophesize(DeliveryRepository::class);
        $this->deliveryManager = $this->prophesize(DeliveryManager::class);
        $this->taskManager = $this->prophesize(TaskManager::class);

        $this->action = new ReceiveWebhook(
            $this->deliveryRepository->reveal(),
            $this->deliveryManager->reveal(),
            $this->taskManager->reveal(),
            PhoneNumberUtil::getInstance(),
            'fr'
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
    }

    public function testTasksAnnounced()
    {
        $webhook = new UrbantzWebhook(UrbantzWebhook::TASKS_ANNOUNCED);
        $webhook->tasks = [
            [
                'id' => 'abcdefgh123456',
                'taskId' => '1269-00099999991',
                'source' => [
                    'number' => '4',
                    'street' => 'Rue Perrault',
                    'city' => 'Nantes',
                    'zip' => '44000',
                    'country' => 'FR',
                    'address' => 'Rue Perrault 4 44000 Nantes FR'
                ],
                'location' => [
                    'location' => [
                        'geometry' => [
                            -1.5506787323970848,
                            47.21125182318541
                        ]
                    ]
                ],
                'contact' => [
                    'name' => null,
                    'person' => 'Test Nantais',
                    'phone' => '06XXXXXXX'
                ],
                'timeWindow' => [
                    'start' => '2021-09-23T08:25:00.000Z',
                    'stop' => '2021-09-23T09:00:00.000Z'
                ],
                'dimensions' => [
                    'weight' => 25.592,
                    'bac' => 1,
                    'volume' => 49.452571
                ],
            ]
        ];

        $response = call_user_func_array($this->action, [$webhook]);

        $this->assertSame($webhook, $response);

        $this->assertCount(1, $webhook->deliveries);

        $delivery = $webhook->deliveries[0];

        $dropoffAddress = $delivery->getDropoff()->getAddress();

        $this->assertEquals('4 Rue Perrault, 44000 Nantes', $dropoffAddress->getStreetAddress());
        $this->assertEquals(47.21125182318541, $dropoffAddress->getGeo()->getLatitude());
        $this->assertEquals(-1.5506787323970848, $dropoffAddress->getGeo()->getLongitude());
        $this->assertEquals('abcdefgh123456', $delivery->getDropoff()->getRef());
        $this->assertEquals("1 × bac(s)\n25.592 kg\n", $delivery->getPickup()->getComments());
    }
}
