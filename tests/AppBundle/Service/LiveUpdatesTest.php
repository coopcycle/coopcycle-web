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
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class LiveUpdatesTest extends TestCase
{
    use ProphecyTrait;

    private LiveUpdates $liveUpdates;
    private $tokenStorageMock;
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
        $this->tokenStorageMock = $this->prophesize(TokenStorageInterface::class);
        $this->userManagerMock = $this->prophesize(UserManager::class);
        $this->serializerMock = $this->prophesize(SerializerInterface::class);
        $this->translatorMock = $this->prophesize(TranslatorInterface::class);
        $this->centrifugoClientMock = $this->prophesize(CentrifugoClient::class);
        $this->messageBusMock = $this->prophesize(MessageBusInterface::class);
        $this->notificationPreferencesMock = $this->prophesize(NotificationPreferences::class);
        $this->realTimeMessageLoggerMock = $this->prophesize(LoggerInterface::class);

        $this->liveUpdates = new LiveUpdates(
            $this->tokenStorageMock->reveal(),
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

    public function testToRoles(): void
    {
        $message = 'Test message';
        $usersWithRoles = [
            $this->prophesize(UserInterface::class)->reveal(),
            $this->prophesize(UserInterface::class)->reveal()
        ];
        $roles = ['ROLE_1', 'ROLE_2'];

        $this->userManagerMock->findUsersByRoles($roles)
            ->willReturn($usersWithRoles)
            ->shouldBeCalledOnce();

        $channels = array_map(function (UserInterface $user) {
            return sprintf('%s_events#%s', $this->namespace, $user->getUsername());
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

    public function testToAdmins(): void
    {
        $message = 'Test message';
        $adminUsers = [
            $this->prophesize(UserInterface::class)->reveal(),
            $this->prophesize(UserInterface::class)->reveal()
        ];

        $this->userManagerMock->findUsersByRoles(['ROLE_ADMIN'])
            ->willReturn($adminUsers)
            ->shouldBeCalledOnce();

        $channels = array_map(function (UserInterface $user) {
            return sprintf('%s_events#%s', $this->namespace, $user->getUsername());
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

        $this->liveUpdates->toAdmins($message);
    }

    public function testToUserAndAdmins_userNotAdmin(): void
    {
        $message = 'Test message';
        $user = $this->prophesize(UserInterface::class)->reveal();
        $adminUsers = [
            $this->prophesize(UserInterface::class)->reveal(),
            $this->prophesize(UserInterface::class)->reveal()
        ];
        $allUsers = array_merge([$user], $adminUsers);

        $this->userManagerMock->findUsersByRoles(['ROLE_ADMIN'])
            ->willReturn($adminUsers)
            ->shouldBeCalledOnce();

        $channels = array_map(function (UserInterface $user) {
            return sprintf('%s_events#%s', $this->namespace, $user->getUsername());
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

        $this->liveUpdates->toUserAndAdmins($user, $message);
    }

    public function testToUserAndAdmins_userIsAdmin(): void
    {
        $message = 'Test message';
        $adminUsers = [
            $this->prophesize(UserInterface::class)->reveal(),
            $this->prophesize(UserInterface::class)->reveal(),
            $this->prophesize(UserInterface::class)->reveal()
        ];
        $allUsers = $adminUsers;

        $this->userManagerMock->findUsersByRoles(['ROLE_ADMIN'])
            ->willReturn($adminUsers)
            ->shouldBeCalledOnce();

        $channels = array_map(function (UserInterface $user) {
            return sprintf('%s_events#%s', $this->namespace, $user->getUsername());
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

        $this->liveUpdates->toUserAndAdmins($allUsers[0], $message);
    }

    public function testToUserAndRoles_userDontHaveRole(): void
    {
        $message = 'Test message';
        $user = $this->prophesize(UserInterface::class)->reveal();
        $usersWithRoles = [
            $this->prophesize(UserInterface::class)->reveal(),
            $this->prophesize(UserInterface::class)->reveal(),
            $this->prophesize(UserInterface::class)->reveal()
        ];
        $allUsers = array_merge([$user], $usersWithRoles);
        $roles = ['ROLE_1', 'ROLE_2'];

        $this->userManagerMock->findUsersByRoles($roles)
            ->willReturn($usersWithRoles)
            ->shouldBeCalledOnce();

        $channels = array_map(function (UserInterface $user) {
            return sprintf('%s_events#%s', $this->namespace, $user->getUsername());
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
            $this->prophesize(UserInterface::class)->reveal(),
            $this->prophesize(UserInterface::class)->reveal(),
            $this->prophesize(UserInterface::class)->reveal()
        ];
        $allUsers = $usersWithRoles;
        $roles = ['ROLE_1', 'ROLE_2'];

        $this->userManagerMock->findUsersByRoles($roles)
            ->willReturn($usersWithRoles)
            ->shouldBeCalledOnce();

        $channels = array_map(function (UserInterface $user) {
            return sprintf('%s_events#%s', $this->namespace, $user->getUsername());
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
}
