<?php

namespace Tests\AppBundle\MessageHandler;

use AppBundle\Entity\User;
use AppBundle\Message\PushNotification;
use AppBundle\MessageHandler\PushNotificationHandler;
use AppBundle\Service\RemotePushNotificationManager;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

class PushNotificationHandlerTest extends TestCase
{
    use ProphecyTrait;

    public function setUp(): void
    {
        $this->remotePushNotificationManager = $this->prophesize(RemotePushNotificationManager::class);

        $this->handler = new PushNotificationHandler(
            $this->remotePushNotificationManager->reveal()
        );
    }

    public function testSend()
    {
        $title = 'Hello, world!';
        $body = "Some body text";
        $users = [new User(), new User()];
        $data = ['foo' => 'bar'];

        $pushNotification = new PushNotification($title, $body, $users, $data);

        $this->remotePushNotificationManager
            ->send($pushNotification)
            ->shouldBeCalledTimes(1);

        call_user_func_array($this->handler, [ $pushNotification ]);
    }
}
