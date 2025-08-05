<?php

namespace Tests\AppBundle\MessageHandler;

use AppBundle\Entity\User;
use AppBundle\Message\PushNotification;
use AppBundle\Message\PushNotificationV2;
use AppBundle\MessageHandler\PushNotificationHandler;
use AppBundle\Service\RemotePushNotificationManager;
use Nucleos\UserBundle\Model\UserManager;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

class PushNotificationHandlerTest extends TestCase
{
    use ProphecyTrait;

    public function setUp(): void
    {
        $this->remotePushNotificationManager = $this->prophesize(RemotePushNotificationManager::class);
        $this->userManager = $this->prophesize(UserManager::class);

        $this->handler = new PushNotificationHandler(
            $this->remotePushNotificationManager->reveal(),
            $this->userManager->reveal()
        );
    }

    public function testSkipsUnknownUsers()
    {
        $user = new User();

        $this->userManager->findUserByUsername('bar')->willReturn($user);
        $this->userManager->findUserByUsername('foo')->willReturn(null);

        $content = 'Hello, world!';
        $pushNotification = new PushNotification($content, ['foo', 'bar']);

        $this->remotePushNotificationManager
            ->send($pushNotification, [$user])
            ->shouldBeCalled();

        call_user_func_array($this->handler, [ $pushNotification ]);
    }

    public function testSend()
    {
        $bar = new User();
        $foo = new User();

        $this->userManager->findUserByUsername('bar')->willReturn($bar);
        $this->userManager->findUserByUsername('foo')->willReturn($foo);

        $content = 'Hello, world!';
        $pushNotification = new PushNotification($content, ['foo', 'bar'], ['foo' => 'bar']);

        $this->remotePushNotificationManager
            ->send($pushNotification, [$bar, $foo])
            ->shouldBeCalled();

        call_user_func_array($this->handler, [ $pushNotification ]);

        $pushNotificationV2 = new PushNotificationV2($content, "Some body text", [$bar, $foo], ['foo' => 'bar']);

        $this->remotePushNotificationManager
            ->send($pushNotificationV2, [])
            ->shouldBeCalled();

        call_user_func_array($this->handler, [ $pushNotificationV2 ]);
    }
}
