<?php

namespace Tests\AppBundle\Service;

use AppBundle\Entity\ApiUser;
use AppBundle\Entity\RemotePushToken;
use AppBundle\Service\RemotePushNotificationManager;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;

class RemotePushNotificationManagerTest extends TestCase
{
    private $remotePushNotificationManager;

    private function assertArrayIsZeroIndexed(array $value)
    {
        $this->assertEquals(range(0, count($value) - 1), array_keys($value), 'Failed asserting that an array is zero-indexed');
    }

    private function assertFcmRequest(Request $request, $multiple = false, array $recipients = [])
    {
        $body = (string) $request->getBody();
        $payload = json_decode($body, true);

        $this->assertEquals('POST', $request->getMethod());
        $this->assertTrue($request->hasHeader('Authorization'));
        $this->assertEquals('key=1234567890', $request->getHeaderLine('Authorization'));

        if ($multiple) {
            $this->assertArrayHasKey('registration_ids', $payload);
            $this->assertArrayIsZeroIndexed($payload['registration_ids']);
            $this->assertCount(count($recipients), $payload['registration_ids']);
            $this->assertEquals($recipients, $payload['registration_ids']);
        } else {
            $this->assertArrayHasKey('to', $payload);
        }
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
        $this->httpClient = $this->prophesize(HttpClient::class);
        $this->apns = $this->prophesize(\ApnsPHP_Push::class);

        $this->remotePushNotificationManager = new RemotePushNotificationManager(
            $this->httpClient->reveal(),
            $this->apns->reveal(),
            'passphrase',
            '1234567890'
        );
    }

    public function testSendOneWithApns()
    {
        $remotePushToken = new RemotePushToken();
        $remotePushToken->setToken($this->generateApnsToken());
        $remotePushToken->setPlatform('ios');

        $this->apns
            ->setProviderCertificatePassphrase('passphrase')
            ->shouldBeCalled();

        $this->apns
            ->connect()
            ->shouldBeCalled();

        $this->apns
            ->add(Argument::that(function (\ApnsPHP_Message $message) {
                return $message->getText() === 'Hello world!';
            }))
            ->shouldBeCalled();

        $this->apns
            ->send()
            ->shouldBeCalled();

        $this->apns
            ->disconnect()
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

        $this->apns
            ->setProviderCertificatePassphrase('passphrase')
            ->shouldBeCalled();

        $this->apns
            ->connect()
            ->shouldBeCalled();

        $this->apns
            ->add(Argument::that(function (\ApnsPHP_Message $message) use ($token1, $token2) {
                return $message->getText() === 'Hello world!'
                    && 2 === count($message->getRecipients())
                    && in_array($token1, $message->getRecipients())
                    && in_array($token2, $message->getRecipients());
            }))
            ->shouldBeCalled();

        $this->apns
            ->send()
            ->shouldBeCalled();

        $this->apns
            ->disconnect()
            ->shouldBeCalled();

        $this->remotePushNotificationManager->send('Hello world!', [
            $remotePushToken1,
            $remotePushToken2
        ]);
    }

    public function testSendOneWithFcm()
    {
        $token = $this->generateApnsToken();

        $remotePushToken = new RemotePushToken();
        $remotePushToken->setToken($token);
        $remotePushToken->setPlatform('android');

        $this->httpClient
            ->send(Argument::that(function (Request $request) use ($token) {

                $body = (string) $request->getBody();
                $payload = json_decode($body, true);

                return 'POST' === $request->getMethod()
                    && $request->hasHeader('Authorization')
                    && 'key=1234567890' === $request->getHeaderLine('Authorization')
                    && isset($payload['to'])
                    && $token === $payload['to'];
            }))
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

        $this->httpClient
            ->send(Argument::that(function (Request $request) use ($token1, $token2) {
                $this->assertFcmRequest($request, $multiple = true, [ $token1, $token2 ]);

                return true;
            }))
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

        $user1 = new ApiUser();
        $user1->getRemotePushTokens()->add($remotePushToken1);

        $user2 = new ApiUser();
        $user2->getRemotePushTokens()->add($remotePushToken2);

        $user3 = new ApiUser();

        $this->httpClient
            ->send(Argument::that(function (Request $request) use ($token1, $token2) {
                $this->assertFcmRequest($request, $multiple = true, [ $token1, $token2 ]);

                return true;
            }))
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

        $user1 = new ApiUser();
        $user1->getRemotePushTokens()->add($remotePushToken1);

        $user2 = new ApiUser();
        $user2->getRemotePushTokens()->add($remotePushToken2);

        $user3 = new ApiUser();
        $user3->getRemotePushTokens()->add($remotePushToken3);

        $this->httpClient
            ->send(Argument::that(function (Request $request) use ($token1, $token2, $token3) {
                $this->assertFcmRequest($request, $multiple = true, [ $token2, $token3 ]);

                return true;
            }))
            ->shouldBeCalled();

        $this->remotePushNotificationManager->send('Hello world!', [
            $user1,
            $user2,
            $user3
        ]);
    }
}
