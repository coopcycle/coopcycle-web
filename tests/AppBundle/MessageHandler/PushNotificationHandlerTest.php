<?php

namespace Tests\AppBundle\MessageHandler;

use AppBundle\Entity\User;
use AppBundle\Message\PushNotification;
use AppBundle\MessageHandler\PushNotificationHandler;
use AppBundle\Service\RemotePushNotification;
use AppBundle\Service\RemotePushNotificationManager;
use Nucleos\UserBundle\Model\UserManager;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

class PushNotificationHandlerTest extends TestCase
{
    use ProphecyTrait;

    public function setUp(): void
    {
        $this->remotePushNotificationManager = $this->prophesize(
            RemotePushNotificationManager::class
        );
        $this->userManager = $this->prophesize(UserManager::class);

        $this->handler = new PushNotificationHandler(
            $this->remotePushNotificationManager->reveal(),
            $this->userManager->reveal()
        );
    }

    public function testSkipsUnknownUsers()
    {
        $bar = new User();
        $bar->setUsername('bar');
        $foo = new User();
        $foo->setUsername('foo');

        $this->userManager->findUserByUsername('bar')->willReturn($bar);
        $this->userManager->findUserByUsername('foo')->willReturn(null);

        $pushNotification = new PushNotification('Hello, world!', '', [$bar], ['key' => 'value']);

        $this->remotePushNotificationManager
            ->send(Argument::type(RemotePushNotification::class), [$bar])
            ->shouldBeCalled();

        call_user_func_array($this->handler, [$pushNotification]);
    }

    public function testSend()
    {
        $bar = new User();
        $bar->setUsername('bar');
        $foo = new User();
        $foo->setUsername('foo');

        $this->userManager->findUserByUsername('bar')->willReturn($bar);
        $this->userManager->findUserByUsername('foo')->willReturn($foo);

        $pushNotification = new PushNotification(
            'Hello, world!',
            'Some body text',
            [$bar, $foo],
            ['key' => 'value']
        );

        $this->remotePushNotificationManager
            ->send(Argument::type(RemotePushNotification::class), [$bar, $foo])
            ->shouldBeCalledTimes(1);

        call_user_func_array($this->handler, [$pushNotification]);
    }
}
