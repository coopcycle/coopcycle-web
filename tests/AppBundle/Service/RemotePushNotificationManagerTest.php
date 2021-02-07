<?php

namespace Tests\AppBundle\Service;

use AppBundle\Entity\User;
use AppBundle\Entity\RemotePushToken;
use AppBundle\Service\RemotePushNotificationManager;
use Doctrine\ORM\EntityManagerInterface;
use Kreait\Firebase\Factory as FirebaseFactory;
use Kreait\Firebase\Messaging as FirebaseMessaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Exception\Messaging\NotFound;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\NullLogger;
use Pushok;
use Symfony\Contracts\Translation\TranslatorInterface;

class RemotePushNotificationManagerTest extends TestCase
{
    use ProphecyTrait;

    private $remotePushNotificationManager;

    private function assertArrayIsZeroIndexed(array $value)
    {
        $this->assertEquals(range(0, count($value) - 1), array_keys($value), 'Failed asserting that an array is zero-indexed');
    }

    private function generateApnsToken()
    {
        $characters = 'abcdef0123456789';

        $token = '';
        $max = strlen($characters) - 1;
        for ($i = 0; $i < 64; $i++) {
            $token .= $characters[mt_rand(0, $max)];
        }

        return $token;
    }

    public function setUp(): void
    {
        $this->firebaseMessaging = $this->prophesize(FirebaseMessaging::class);
        $this->firebaseFactory = $this->prophesize(FirebaseFactory::class);
        $this->pushOkClient = $this->prophesize(Pushok\Client::class);

        $this->firebaseFactory->createMessaging()
            ->willReturn($this->firebaseMessaging->reveal());

        $this->entityManager = $this->prophesize(EntityManagerInterface::class);

        $this->translator = $this->prophesize(TranslatorInterface::class);
        $this->translator->trans('notifications.tap_to_open')
            ->willReturn('Tap to open');

        $this->remotePushNotificationManager = new RemotePushNotificationManager(
            $this->firebaseFactory->reveal(),
            $this->pushOkClient->reveal(),
            $this->entityManager->reveal(),
            $this->translator->reveal(),
            new NullLogger()
        );
    }

    public function testSendOneWithApns()
    {
        $deviceToken = $this->generateApnsToken();

        $remotePushToken = new RemotePushToken();
        $remotePushToken->setToken($deviceToken);
        $remotePushToken->setPlatform('ios');

        $this->pushOkClient
            ->addNotifications(Argument::that(function (array $notifications) use ($deviceToken) {

                $this->assertCount(1, $notifications);
                $this->assertEquals($deviceToken, $notifications[0]->getDeviceToken());

                $payload = $notifications[0]->getPayload();

                $this->assertEquals('Hello world!', $payload->getAlert()->getTitle());

                return true;
            }))
            ->shouldBeCalled();

        $this->pushOkClient
            ->push()
            ->shouldBeCalled();

        $this->remotePushNotificationManager->send('Hello world!', $remotePushToken);
    }

    public function testSendMulitpleWithApns()
    {
        $token1 = $this->generateApnsToken();
        $remotePushToken1 = new RemotePushToken();
        $remotePushToken1->setToken($token1);
        $remotePushToken1->setPlatform('ios');

        $token2 = $this->generateApnsToken();
        $remotePushToken2 = new RemotePushToken();
        $remotePushToken2->setToken($token2);
        $remotePushToken2->setPlatform('ios');

        $this->pushOkClient
            ->addNotifications(Argument::that(function (array $notifications) use ($token1, $token2) {

                $this->assertCount(2, $notifications);

                $deviceTokens = array_map(function (Pushok\Notification $n) {
                    return $n->getDeviceToken();
                }, $notifications);

                $this->assertContains($token1, $deviceTokens);
                $this->assertContains($token2, $deviceTokens);

                return true;
            }))
            ->shouldBeCalled();

        $this->pushOkClient
            ->push()
            ->willReturn([]);

        $this->remotePushNotificationManager->send('Hello world!', [
            $remotePushToken1,
            $remotePushToken2
        ]);

        $this->pushOkClient
            ->push()
            ->shouldHaveBeenCalled();
    }

    public function testSendOneWithFcm()
    {
        $token = $this->generateApnsToken();

        $remotePushToken = new RemotePushToken();
        $remotePushToken->setToken($token);
        $remotePushToken->setPlatform('android');

        $msr = FirebaseMessaging\MulticastSendReport::withItems([
            FirebaseMessaging\SendReport::success(
                FirebaseMessaging\MessageTarget::with('token', $token),
                []
            )
        ]);

        $this->firebaseMessaging
            ->sendMulticast(Argument::that(function (CloudMessage $message) {

                $data = json_decode(json_encode($message), true);

                $this->assertArrayHasKey('android', $data);

                if (isset($data['notification'])) {
                    $this->assertEquals('Hello world!', $data['notification']['title']);
                    $this->assertEquals('Tap to open', $data['notification']['body']);
                }

                return true;

            }), [ $token ])
            ->willReturn($msr)
            ->shouldBeCalled();

        $this->remotePushNotificationManager->send('Hello world!', $remotePushToken);
    }

    public function testSendMultipleWithFcm()
    {
        $token1 = $this->generateApnsToken();
        $remotePushToken1 = new RemotePushToken();
        $remotePushToken1->setToken($token1);
        $remotePushToken1->setPlatform('android');

        $token2 = $this->generateApnsToken();
        $remotePushToken2 = new RemotePushToken();
        $remotePushToken2->setToken($token2);
        $remotePushToken2->setPlatform('android');

        $msr = FirebaseMessaging\MulticastSendReport::withItems([
            FirebaseMessaging\SendReport::success(
                FirebaseMessaging\MessageTarget::with('token', $token1),
                []
            ),
            FirebaseMessaging\SendReport::success(
                FirebaseMessaging\MessageTarget::with('token', $token2),
                []
            ),
        ]);

        $this->firebaseMessaging
            ->sendMulticast(Argument::that(function (CloudMessage $message) {

                $data = json_decode(json_encode($message), true);

                $this->assertArrayHasKey('android', $data);

                if (isset($data['notification'])) {
                    $this->assertEquals('Hello world!', $data['notification']['title']);
                    $this->assertEquals('Tap to open', $data['notification']['body']);
                }

                return true;

            }), [ $token1, $token2 ])
            ->willReturn($msr)
            ->shouldBeCalled();

        $this->remotePushNotificationManager->send('Hello world!', [
            $remotePushToken1,
            $remotePushToken2
        ]);
    }

    public function testSendMultipleWithMissingToken()
    {
        $token1 = $this->generateApnsToken();
        $token2 = $this->generateApnsToken();

        $remotePushToken1 = new RemotePushToken();
        $remotePushToken1->setToken($token1);
        $remotePushToken1->setPlatform('android');

        $remotePushToken2 = new RemotePushToken();
        $remotePushToken2->setToken($token2);
        $remotePushToken2->setPlatform('android');

        $user1 = new User();
        $user1->getRemotePushTokens()->add($remotePushToken1);

        $user2 = new User();
        $user2->getRemotePushTokens()->add($remotePushToken2);

        $user3 = new User();

        $msr = FirebaseMessaging\MulticastSendReport::withItems([
            FirebaseMessaging\SendReport::success(
                FirebaseMessaging\MessageTarget::with('token', $token1),
                []
            ),
            FirebaseMessaging\SendReport::success(
                FirebaseMessaging\MessageTarget::with('token', $token2),
                []
            ),
        ]);

        $this->firebaseMessaging
            ->sendMulticast(Argument::that(function (CloudMessage $message) {

                $data = json_decode(json_encode($message), true);

                $this->assertArrayHasKey('android', $data);

                if (isset($data['notification'])) {
                    $this->assertEquals('Hello world!', $data['notification']['title']);
                    $this->assertEquals('Tap to open', $data['notification']['body']);
                }

                return true;

            }), [ $token1, $token2 ])
            ->willReturn($msr)
            ->shouldBeCalled();

        $this->remotePushNotificationManager->send('Hello world!', [
            $user1,
            $user2,
            $user3
        ]);
    }

    public function testSendMultipleWithMixedTokens()
    {
        $token1 = $this->generateApnsToken();
        $token2 = $this->generateApnsToken();
        $token3 = $this->generateApnsToken();

        $remotePushToken1 = new RemotePushToken();
        $remotePushToken1->setToken($token1);
        $remotePushToken1->setPlatform('ios');

        $remotePushToken2 = new RemotePushToken();
        $remotePushToken2->setToken($token2);
        $remotePushToken2->setPlatform('android');

        $remotePushToken3 = new RemotePushToken();
        $remotePushToken3->setToken($token3);
        $remotePushToken3->setPlatform('android');

        $user1 = new User();
        $user1->getRemotePushTokens()->add($remotePushToken1);

        $user2 = new User();
        $user2->getRemotePushTokens()->add($remotePushToken2);

        $user3 = new User();
        $user3->getRemotePushTokens()->add($remotePushToken3);

        $this->pushOkClient
            ->addNotifications(Argument::that(function (array $notifications) use ($token1, $token2) {

                $this->assertCount(1, $notifications);

                return true;
            }))
            ->shouldBeCalled();

        $this->pushOkClient
            ->push()
            ->willReturn([]);

        $msr = FirebaseMessaging\MulticastSendReport::withItems([
            FirebaseMessaging\SendReport::success(
                FirebaseMessaging\MessageTarget::with('token', $token2),
                []
            ),
            FirebaseMessaging\SendReport::failure(
                FirebaseMessaging\MessageTarget::with('token', $token3),
                new NotFound()
            ),
        ]);

        $this->firebaseMessaging
            ->sendMulticast(Argument::that(function (CloudMessage $message) {

                $data = json_decode(json_encode($message), true);

                $this->assertArrayHasKey('android', $data);
                $this->assertArrayHasKey('data', $data);

                $this->assertArrayHasKey('event', $data['data']);
                $this->assertEquals('{"name":"foo"}', $data['data']['event']);

                if (isset($data['notification'])) {
                    $this->assertEquals('Hello world!', $data['notification']['title']);
                    $this->assertEquals('Tap to open', $data['notification']['body']);
                }

                return true;

            }), [ $token2, $token3 ])
            ->willReturn($msr)
            ->shouldBeCalled();

        $this->remotePushNotificationManager->send('Hello world!', [
            $user1,
            $user2,
            $user3
        ], ['event' => [ 'name' => 'foo' ]]);

        $this->pushOkClient
            ->push()
            ->shouldHaveBeenCalled();
    }

    public function testSendMultipleWithFcmWithNotFoundError()
    {
        $token1 = $this->generateApnsToken();
        $remotePushToken1 = new RemotePushToken();
        $remotePushToken1->setToken($token1);
        $remotePushToken1->setPlatform('android');

        $token2 = $this->generateApnsToken();
        $remotePushToken2 = new RemotePushToken();
        $remotePushToken2->setToken($token2);
        $remotePushToken2->setPlatform('android');

        $msr = FirebaseMessaging\MulticastSendReport::withItems([
            FirebaseMessaging\SendReport::success(
                FirebaseMessaging\MessageTarget::with('token', $token1),
                []
            ),
            FirebaseMessaging\SendReport::failure(
                FirebaseMessaging\MessageTarget::with('token', $token2),
                new NotFound()
            ),
        ]);

        $this->firebaseMessaging
            ->sendMulticast(Argument::that(function (CloudMessage $message) {

                $data = json_decode(json_encode($message), true);

                $this->assertArrayHasKey('android', $data);

                if (isset($data['notification'])) {
                    $this->assertEquals('Hello world!', $data['notification']['title']);
                    $this->assertEquals('Tap to open', $data['notification']['body']);
                }

                return true;

            }), [ $token1, $token2 ])
            ->willReturn($msr)
            ->shouldBeCalled();

        $this->remotePushNotificationManager->send('Hello world!', [
            $remotePushToken1,
            $remotePushToken2
        ]);

        $this->entityManager->remove($remotePushToken2)->shouldHaveBeenCalled();
        $this->entityManager->flush()->shouldHaveBeenCalled();
    }
}
