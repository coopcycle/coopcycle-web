<?php

namespace Tests\AppBundle\Service;

use AppBundle\Security\UserManager;
use AppBundle\Service\LiveUpdates;
use AppBundle\Service\NotificationPreferences;
use phpcent\Client as CentrifugoClient;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
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
    private $centrifugoClientMock;
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
        $this->centrifugoClientMock = $this->prophesize(CentrifugoClient::class);
        $this->messageBusMock = $this->prophesize(MessageBusInterface::class);
        $this->notificationPreferencesMock = $this->prophesize(NotificationPreferences::class);
        $this->realTimeMessageLoggerMock = $this->prophesize(LoggerInterface::class);

        $this->liveUpdates = new LiveUpdates(
            $this->securityMock->reveal(),
            $this->userManagerMock->reveal(),
            $this->serializerMock->reveal(),
            $this->translatorMock->reveal(),
            $this->centrifugoClientMock->reveal(),
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

        $this->centrifugoClientMock->broadcast($channels, $event)->shouldBeCalledOnce();

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

        $this->centrifugoClientMock->broadcast($channels, $event)->shouldBeCalledOnce();

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

        $this->centrifugoClientMock->broadcast($channels, $event)->shouldBeCalledOnce();

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

        $this->centrifugoClientMock->broadcast($channels, $event)->shouldBeCalledOnce();

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

        $this->centrifugoClientMock->broadcast($channels, $event)->shouldBeCalledOnce();

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

        $this->centrifugoClientMock->broadcast($channels, $event)->shouldBeCalledOnce();

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

        $this->centrifugoClientMock->broadcast(
            [sprintf('%s_events#%s', $this->namespace, $dispatcher->getUserIdentifier())],
            ['event' => ['name' => 'Test message', 'data' => []]]
        )->shouldBeCalledOnce();

        $this->liveUpdates->toDispatchers('Test message');
    }

    /**
     * Centrifugo answers 200 with an `error` object when it refuses a publication,
     * and phpcent only throws on a non-200 -- so without inspecting the body a
     * refused event is indistinguishable from a delivered one, and simply never
     * reaches the dispatch board.
     */
    public function testRefusedPublicationIsLoggedAsAnError(): void
    {
        $user = $this->createUser();

        $this->userManagerMock->findUsersByRoles(LiveUpdates::DISPATCH_ROLES)
            ->willReturn([$user])
            ->shouldBeCalledOnce();

        $this->notificationPreferencesMock->isEventEnabled(Argument::any())
            ->willReturn(false)
            ->shouldBeCalled();

        $this->centrifugoClientMock->broadcast(Argument::cetera())
            ->willReturn(['error' => ['code' => 102, 'message' => 'unknown channel']])
            ->shouldBeCalledOnce();

        $this->realTimeMessageLoggerMock->info(Argument::cetera())->shouldBeCalled();
        $this->realTimeMessageLoggerMock
            ->error(Argument::containingString('Centrifugo refused event'))
            ->shouldBeCalledOnce();

        $this->liveUpdates->toDispatchers('Test message');
    }

    public function testDeliveredPublicationIsNotLoggedAsAnError(): void
    {
        $user = $this->createUser();

        $this->userManagerMock->findUsersByRoles(LiveUpdates::DISPATCH_ROLES)
            ->willReturn([$user])
            ->shouldBeCalledOnce();

        $this->notificationPreferencesMock->isEventEnabled(Argument::any())
            ->willReturn(false)
            ->shouldBeCalled();

        $this->centrifugoClientMock->broadcast(Argument::cetera())
            ->willReturn(['result' => []])
            ->shouldBeCalledOnce();

        $this->realTimeMessageLoggerMock->info(Argument::cetera())->shouldBeCalled();
        $this->realTimeMessageLoggerMock->error(Argument::cetera())->shouldNotBeCalled();

        $this->liveUpdates->toDispatchers('Test message');
    }
}
