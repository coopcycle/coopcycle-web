<?php

namespace Tests\AppBundle\MessageHandler;

use AppBundle\Entity\User;
use AppBundle\Message\PushNotification;
use AppBundle\MessageHandler\PushNotificationHandler;
use AppBundle\Service\RemotePushNotificationManager;
use FOS\UserBundle\Model\UserManagerInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

class PushNotificationHandlerTest extends TestCase
{
    use ProphecyTrait;

    public function setUp(): void
    {
        $this->remotePushNotificationManager = $this->prophesize(RemotePushNotificationManager::class);
        $this->userManager = $this->prophesize(UserManagerInterface::class);

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

        $this->remotePushNotificationManager
            ->send($content, [$user], [])
            ->shouldBeCalled();

        call_user_func_array($this->handler, [ new PushNotification('Hello, world!', ['foo', 'bar']) ]);
    }

    public function testSend()
    {
        $bar = new User();
        $foo = new User();

        $this->userManager->findUserByUsername('bar')->willReturn($bar);
        $this->userManager->findUserByUsername('foo')->willReturn($foo);

        $content = 'Hello, world!';

        $this->remotePushNotificationManager
            ->send($content, [$bar, $foo], ['foo' => 'bar'])
            ->shouldBeCalled();

        call_user_func_array($this->handler, [ new PushNotification('Hello, world!', ['foo', 'bar'], ['foo' => 'bar']) ]);
    }
}
