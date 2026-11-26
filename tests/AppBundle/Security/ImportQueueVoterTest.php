<?php

namespace Tests\AppBundle\Security;

use AppBundle\Entity\ImportQueue;
use AppBundle\Entity\Store;
use AppBundle\Entity\User;
use AppBundle\Security\ImportQueueVoter;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class ImportQueueVoterTest extends TestCase
{
    use ProphecyTrait;

    private $authorizationChecker;
    private ImportQueueVoter $voter;

    protected function setUp(): void
    {
        $this->authorizationChecker = $this->prophesize(AuthorizationCheckerInterface::class);

        $this->authorizationChecker->isGranted('ROLE_DISPATCHER')->willReturn(false);
        $this->authorizationChecker->isGranted('ROLE_STORE')->willReturn(false);

        $this->voter = new ImportQueueVoter($this->authorizationChecker->reveal());
    }

    private function createToken(?User $user): TokenInterface
    {
        $token = $this->prophesize(TokenInterface::class);
        $token->getUser()->willReturn($user);

        return $token->reveal();
    }

    private function createStore(int $id): Store
    {
        $store = new Store();

        $reflection = new \ReflectionProperty(Store::class, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($store, $id);

        return $store;
    }

    private function createImport(string $type, ?Store $store = null): ImportQueue
    {
        $queue = new ImportQueue($type);
        $queue->setStore($store);

        return $queue;
    }

    private function assertVote(int $expected, ImportQueue $queue, TokenInterface $token): void
    {
        foreach (['view', 'edit'] as $attribute) {
            $this->assertSame(
                $expected,
                $this->voter->vote($token, $queue, [$attribute]),
                sprintf('for the "%s" attribute', $attribute)
            );
        }
    }

    /**
     * The imports tab is behind ^/admin, which ROLE_DISPATCHER opens: a dispatcher who
     * can see a row can cancel it, download it and read its errors.
     */
    public function testDispatchersAreGrantedEveryImport(): void
    {
        $this->authorizationChecker->isGranted('ROLE_DISPATCHER')->willReturn(true);

        $token = $this->createToken(new User());

        $this->assertVote(
            VoterInterface::ACCESS_GRANTED,
            $this->createImport(ImportQueue::TYPE_DELIVERIES, $this->createStore(1)),
            $token
        );
        $this->assertVote(
            VoterInterface::ACCESS_GRANTED,
            $this->createImport(ImportQueue::TYPE_TASKS),
            $token
        );
    }

    public function testStoreOwnerIsGrantedTheImportsOfTheirStore(): void
    {
        $this->authorizationChecker->isGranted('ROLE_STORE')->willReturn(true);

        $store = $this->createStore(1);

        $user = $this->prophesize(User::class);
        $user->ownsStore($store)->willReturn(true);

        $this->assertVote(
            VoterInterface::ACCESS_GRANTED,
            $this->createImport(ImportQueue::TYPE_DELIVERIES, $store),
            $this->createToken($user->reveal())
        );
    }

    public function testStoreOwnerIsDeniedTheImportsOfAnotherStore(): void
    {
        $this->authorizationChecker->isGranted('ROLE_STORE')->willReturn(true);

        $store = $this->createStore(1);

        $user = $this->prophesize(User::class);
        $user->ownsStore($store)->willReturn(false);

        $this->assertVote(
            VoterInterface::ACCESS_DENIED,
            $this->createImport(ImportQueue::TYPE_DELIVERIES, $store),
            $this->createToken($user->reveal())
        );
    }

    /**
     * An import with no store belongs to the co-op, not to one of its stores. Asking
     * whether a store user owns a store that isn't there would either throw or, worse,
     * hand them the whole file: its addresses, names and phone numbers.
     */
    public function testStoreOwnerIsDeniedAnImportWithNoStore(): void
    {
        $this->authorizationChecker->isGranted('ROLE_STORE')->willReturn(true);

        $user = $this->prophesize(User::class);
        $user->ownsStore(\Prophecy\Argument::any())->shouldNotBeCalled();

        $this->assertVote(
            VoterInterface::ACCESS_DENIED,
            $this->createImport(ImportQueue::TYPE_TASKS),
            $this->createToken($user->reveal())
        );
    }

    public function testAnyoneElseIsDenied(): void
    {
        $this->assertVote(
            VoterInterface::ACCESS_DENIED,
            $this->createImport(ImportQueue::TYPE_DELIVERIES, $this->createStore(1)),
            $this->createToken(new User())
        );
    }
}
