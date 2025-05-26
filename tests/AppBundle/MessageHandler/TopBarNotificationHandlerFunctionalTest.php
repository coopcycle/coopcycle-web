<?php

namespace Tests\AppBundle\MessageHandler;

use AppBundle\Message\TopBarNotification;
use AppBundle\MessageHandler\TopBarNotificationHandler;
use AppBundle\Service\LiveUpdates;
use Prophecy\PhpUnit\ProphecyTrait;
use Ramsey\Uuid\Uuid;
use Redis;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class TopBarNotificationHandlerFunctionalTest extends KernelTestCase
{
    private $redis;
    private $liveUpdates;

    use ProphecyTrait;

    public function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        $this->redis = self::getContainer()->get(Redis::class);
        $this->liveUpdates = $this->prophesize(LiveUpdates::class);
    }

    public function tearDown(): void
    {
        $this->redis->del('user:bob:notifications');
        $this->redis->del('user:bob:notifications_data');

        parent::tearDown();
    }

    public function testNotificationsLimit()
    {
        $handler = new TopBarNotificationHandler(
            $this->redis,
            $this->liveUpdates->reveal()
        );

        for ($i = 0; $i < 500; $i++) {
            $message = new TopBarNotification(['bob'], 'Lorem ipsum');
            call_user_func_array($handler, [$message]);
        }

        $this->assertEquals(TopBarNotificationHandler::MAX_NOTIFICATIONS, $this->redis->llen('user:bob:notifications'));
        $this->assertEquals(TopBarNotificationHandler::MAX_NOTIFICATIONS, $this->redis->hlen('user:bob:notifications_data'));
    }

    public function testNotificationsLimitWithExisting()
    {
        $handler = new TopBarNotificationHandler(
            $this->redis,
            $this->liveUpdates->reveal()
        );

        for ($i = 0; $i < 500; $i++) {

            $uuid = Uuid::uuid4()->toString();

            $payload = [
                'id' => $uuid,
                'message' => 'Lorem ipsum',
                'timestamp' => (new \DateTime())->getTimestamp()
            ];

            $this->redis->lPush('user:bob:notifications', $uuid);
            $this->redis->hSet('user:bob:notifications_data', $uuid, json_encode($payload));
        }

        $message = new TopBarNotification(['bob'], 'Lorem ipsum');
        call_user_func_array($handler, [$message]);

        $this->assertEquals(TopBarNotificationHandler::MAX_NOTIFICATIONS, $this->redis->llen('user:bob:notifications'));
        $this->assertEquals(TopBarNotificationHandler::MAX_NOTIFICATIONS, $this->redis->hlen('user:bob:notifications_data'));
    }
}
