<?php

namespace Tests\AppBundle\Action\Urbantz;

use AppBundle\Action\Urbantz\ReceiveWebhook;
use AppBundle\Api\Resource\UrbantzWebhook;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\DeliveryRepository;
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
        $this->taskManager = $this->prophesize(TaskManager::class);

        $this->action = new ReceiveWebhook(
            $this->deliveryRepository->reveal(),
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
                    'phone' => '06XXXXXXX',
                    'buildingInfo' => [
                        'digicode1' => null
                    ]
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
                'hub' => '618a4fce108a386e4699725f',
            ]
        ];

        $response = call_user_func_array($this->action, [$webhook]);

        $this->assertSame($webhook, $response);

        $this->assertCount(1, $webhook->deliveries);

        $this->assertEquals('618a4fce108a386e4699725f', $webhook->hub);

        $delivery = $webhook->deliveries[0];

        $dropoffAddress = $delivery->getDropoff()->getAddress();

        $this->assertEquals('4 Rue Perrault, 44000 Nantes', $dropoffAddress->getStreetAddress());
        $this->assertEquals(47.21125182318541, $dropoffAddress->getGeo()->getLatitude());
        $this->assertEquals(-1.5506787323970848, $dropoffAddress->getGeo()->getLongitude());
        $this->assertEquals('abcdefgh123456', $delivery->getDropoff()->getRef());
        $this->assertEquals("Commande n° 1269-00099999991\n1 × bac(s)\n25.592 kg\n", $delivery->getPickup()->getComments());
        $this->assertEmpty($delivery->getDropoff()->getComments());
        $this->assertEquals(25592, $delivery->getWeight());
    }

    public function testTasksAnnouncedWithDigicodeAndFloor()
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
                    'phone' => '06XXXXXXX',
                    'buildingInfo' => [
                        'floor' => 13,
                        'digicode1' => '123456'
                    ]
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
                'hub' => '618a4fce108a386e4699725f',
            ]
        ];

        $response = call_user_func_array($this->action, [$webhook]);

        $this->assertSame($webhook, $response);

        $this->assertCount(1, $webhook->deliveries);

        $this->assertEquals('618a4fce108a386e4699725f', $webhook->hub);

        $delivery = $webhook->deliveries[0];

        $dropoffAddress = $delivery->getDropoff()->getAddress();

        $this->assertEquals("Commande n° 1269-00099999991\n1 × bac(s)\n25.592 kg\n", $delivery->getPickup()->getComments());
        $this->assertEquals("Digicode : 123456\nÉtage : 13\n", $delivery->getDropoff()->getComments());
    }

    public function testTasksAnnouncedWithInterphoneAndEmptyFloor()
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
                    'phone' => '06XXXXXXX',
                    'buildingInfo' => [
                        'floor' => 0,
                        'digicode1' => null,
                        'hasInterphone' => true,
                        'interphoneCode' => '3466'
                    ]
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
                'hub' => '618a4fce108a386e4699725f',
            ]
        ];

        $response = call_user_func_array($this->action, [$webhook]);

        $this->assertSame($webhook, $response);

        $this->assertCount(1, $webhook->deliveries);

        $this->assertEquals('618a4fce108a386e4699725f', $webhook->hub);

        $delivery = $webhook->deliveries[0];

        $dropoffAddress = $delivery->getDropoff()->getAddress();

        $this->assertEquals("Commande n° 1269-00099999991\n1 × bac(s)\n25.592 kg\n", $delivery->getPickup()->getComments());
        $this->assertEquals("Code interphone : 3466\n", $delivery->getDropoff()->getComments());
    }
}
