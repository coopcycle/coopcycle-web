<?php

namespace Tests\AppBundle\Service;

use AppBundle\Message\PublishToCentrifugo;
use AppBundle\Security\UserManager;
use AppBundle\Service\LiveUpdates;
use AppBundle\Service\NotificationPreferences;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class LiveUpdatesTest extends TestCase
{
    use ProphecyTrait;

    private LiveUpdates $liveUpdates;
    private $securityMock;
    private $userManagerMock;
    private $serializerMock;
    private $translatorMock;
    private $messageBusMock;
    private $notificationPreferencesMock;
    private $realTimeMessageLoggerMock;
    private $namespace = 'test_namespace';

    public function setUp(): void
    {
        $this->securityMock = $this->prophesize(Security::class);
        $this->userManagerMock = $this->prophesize(UserManager::class);
        $this->serializerMock = $this->prophesize(SerializerInterface::class);
        $this->translatorMock = $this->prophesize(TranslatorInterface::class);
        $this->messageBusMock = $this->prophesize(MessageBusInterface::class);
        $this->notificationPreferencesMock = $this->prophesize(NotificationPreferences::class);
        $this->realTimeMessageLoggerMock = $this->prophesize(LoggerInterface::class);

        $this->liveUpdates = new LiveUpdates(
            $this->securityMock->reveal(),
            $this->userManagerMock->reveal(),
            $this->serializerMock->reveal(),
            $this->translatorMock->reveal(),
            $this->messageBusMock->reveal(),
            $this->notificationPreferencesMock->reveal(),
            $this->realTimeMessageLoggerMock->reveal(),
            $this->namespace,
        );
    }

    private function createUser()
    {
        $user = $this->prophesize(UserInterface::class);
        $user->getUserIdentifier()->willReturn('john_doe');

        return $user->reveal();
    }

    public function testToRoles(): void
    {
        $message = 'Test message';
        $usersWithRoles = [
            $this->createUser(),
            $this->createUser()
        ];
        $roles = ['ROLE_1', 'ROLE_2'];

        $this->userManagerMock->findUsersByRoles($roles)
            ->willReturn($usersWithRoles)
            ->shouldBeCalledOnce();

        $channels = array_map(function (UserInterface $user) {
            return sprintf('%s_events#%s', $this->namespace, $user->getUserIdentifier());
        }, $usersWithRoles);

        $event = [
            "event" => [
                "name" => "Test message",
                "data" => []
            ]
        ];

        $this->expectPublished($channels, $event['event']['name']);

        $this->notificationPreferencesMock->isEventEnabled(Argument::any())
            ->willReturn(false) // just test message is send via Centrifugo
            ->shouldBeCalled();

        $this->liveUpdates->toRoles($roles, $message);
    }

    public function testToDispatchers(): void
    {
        $message = 'Test message';
        $adminUsers = [
            $this->createUser(),
            $this->createUser()
        ];

        $this->userManagerMock->findUsersByRoles(LiveUpdates::DISPATCH_ROLES)
            ->willReturn($adminUsers)
            ->shouldBeCalledOnce();

        $channels = array_map(function (UserInterface $user) {
            return sprintf('%s_events#%s', $this->namespace, $user->getUserIdentifier());
        }, $adminUsers);

        $event = [
            "event" => [
                "name" => "Test message",
                "data" => []
            ]
        ];

        $this->expectPublished($channels, $event['event']['name']);

        $this->notificationPreferencesMock->isEventEnabled(Argument::any())
            ->willReturn(false) // just test message is send via Centrifugo
            ->shouldBeCalled();

        $this->liveUpdates->toDispatchers($message);
    }

    public function testToUserAndDispatchers_userNotAdmin(): void
    {
        $message = 'Test message';
        $user = $this->createUser();
        $adminUsers = [
            $this->createUser(),
            $this->createUser()
        ];
        $allUsers = array_merge([$user], $adminUsers);

        $this->userManagerMock->findUsersByRoles(LiveUpdates::DISPATCH_ROLES)
            ->willReturn($adminUsers)
            ->shouldBeCalledOnce();

        $channels = array_map(function (UserInterface $user) {
            return sprintf('%s_events#%s', $this->namespace, $user->getUserIdentifier());
        }, $allUsers);

        $event = [
            "event" => [
                "name" => "Test message",
                "data" => []
            ]
        ];

        $this->expectPublished($channels, $event['event']['name']);

        $this->notificationPreferencesMock->isEventEnabled(Argument::any())
            ->willReturn(false) // just test message is send via Centrifugo
            ->shouldBeCalled();

        $this->liveUpdates->toUserAndDispatchers($user, $message);
    }

    public function testToUserAndDispatchers_userIsAdmin(): void
    {
        $message = 'Test message';
        $adminUsers = [
            $this->createUser(),
            $this->createUser(),
            $this->createUser()
        ];
        $allUsers = $adminUsers;

        $this->userManagerMock->findUsersByRoles(LiveUpdates::DISPATCH_ROLES)
            ->willReturn($adminUsers)
            ->shouldBeCalledOnce();

        $channels = array_map(function (UserInterface $user) {
            return sprintf('%s_events#%s', $this->namespace, $user->getUserIdentifier());
        }, $allUsers);

        $event = [
            "event" => [
                "name" => "Test message",
                "data" => []
            ]
        ];

        $this->expectPublished($channels, $event['event']['name']);

        $this->notificationPreferencesMock->isEventEnabled(Argument::any())
            ->willReturn(false) // just test message is send via Centrifugo
            ->shouldBeCalled();

        $this->liveUpdates->toUserAndDispatchers($allUsers[0], $message);
    }

    public function testToUserAndRoles_userDontHaveRole(): void
    {
        $message = 'Test message';
        $user = $this->createUser();
        $usersWithRoles = [
            $this->createUser(),
            $this->createUser(),
            $this->createUser()
        ];
        $allUsers = array_merge([$user], $usersWithRoles);
        $roles = ['ROLE_1', 'ROLE_2'];

        $this->userManagerMock->findUsersByRoles($roles)
            ->willReturn($usersWithRoles)
            ->shouldBeCalledOnce();

        $channels = array_map(function (UserInterface $user) {
            return sprintf('%s_events#%s', $this->namespace, $user->getUserIdentifier());
        }, $allUsers);

        $event = [
            "event" => [
                "name" => "Test message",
                "data" => []
            ]
        ];

        $this->expectPublished($channels, $event['event']['name']);

        $this->notificationPreferencesMock->isEventEnabled(Argument::any())
            ->willReturn(false) // just test message is send via Centrifugo
            ->shouldBeCalled();

        $this->liveUpdates->toUserAndRoles($user, $roles, $message);
    }

    public function testToUserAndRoles_userHaveRole(): void
    {
        $message = 'Test message';
        $usersWithRoles = [
            $this->createUser(),
            $this->createUser(),
            $this->createUser()
        ];
        $allUsers = $usersWithRoles;
        $roles = ['ROLE_1', 'ROLE_2'];

        $this->userManagerMock->findUsersByRoles($roles)
            ->willReturn($usersWithRoles)
            ->shouldBeCalledOnce();

        $channels = array_map(function (UserInterface $user) {
            return sprintf('%s_events#%s', $this->namespace, $user->getUserIdentifier());
        }, $allUsers);

        $event = [
            "event" => [
                "name" => "Test message",
                "data" => []
            ]
        ];

        $this->expectPublished($channels, $event['event']['name']);

        $this->notificationPreferencesMock->isEventEnabled(Argument::any())
            ->willReturn(false) // just test message is send via Centrifugo
            ->shouldBeCalled();

        $this->liveUpdates->toUserAndRoles($allUsers[0], $roles, $message);
    }

    /**
     * Regression test: a user holding ROLE_DISPATCHER but not ROLE_ADMIN used to
     * be left out of order, tour and import events, because those publishers
     * targeted ROLE_ADMIN alone. Their dispatch board went silently stale --
     * nothing errored, the event was simply never addressed to their channel,
     * so a page reload did not help and no telemetry showed a failure.
     *
     * @see https://github.com/coopcycle/coopcycle-web/issues/5361
     */
    public function testDispatchersReceiveEventsBroadcastToDispatchers(): void
    {
        $this->assertContains(
            'ROLE_DISPATCHER',
            LiveUpdates::DISPATCH_ROLES,
            'Events rendered by the dispatch board must reach dispatchers, not only admins.'
        );

        $dispatcher = $this->createUser();

        $this->userManagerMock->findUsersByRoles(LiveUpdates::DISPATCH_ROLES)
            ->willReturn([$dispatcher])
            ->shouldBeCalledOnce();

        $this->notificationPreferencesMock->isEventEnabled(Argument::any())
            ->willReturn(false)
            ->shouldBeCalled();

        $this->expectPublished(
            [sprintf('%s_events#%s', $this->namespace, $dispatcher->getUserIdentifier())],
            'Test message'
        );

        $this->liveUpdates->toDispatchers('Test message');
    }

    /**
     * The live update is handed to the transport as an already-resolved message:
     * channels decided, payload serialized. Nothing downstream re-reads anything.
     *
     * @param string[] $channels
     */
    private function expectPublished(array $channels, string $eventName): void
    {
        $this->messageBusMock
            ->dispatch(Argument::that(function ($message) use ($channels, $eventName) {
                return $message instanceof PublishToCentrifugo
                    && $message->channels === $channels
                    && ($message->event['name'] ?? null) === $eventName
                    && array_key_exists('version', $message->event);
            }))
            ->willReturn(new Envelope(new \stdClass()))
            ->shouldBeCalledOnce();
    }
}
