<?php

namespace Tests\AppBundle\Security;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Incident\Incident;
use AppBundle\Entity\Store;
use AppBundle\Entity\Task;
use AppBundle\Entity\User;
use AppBundle\Security\IncidentVoter;
use AppBundle\Security\TokenStoreExtractor;
use League\Bundle\OAuth2ServerBundle\Security\Authentication\Token\OAuth2Token;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class IncidentVoterTest extends TestCase
{
    use ProphecyTrait;

    private $authorizationChecker;
    private $storeExtractor;
    private IncidentVoter $voter;

    protected function setUp(): void
    {
        $this->authorizationChecker = $this->prophesize(AuthorizationCheckerInterface::class);
        $this->storeExtractor = $this->prophesize(TokenStoreExtractor::class);

        $this->authorizationChecker->isGranted('ROLE_DISPATCHER')->willReturn(false);
        $this->authorizationChecker->isGranted('ROLE_COURIER')->willReturn(false);
        $this->authorizationChecker->isGranted('ROLE_OAUTH2_TASKS')->willReturn(false);

        $this->voter = new IncidentVoter(
            $this->authorizationChecker->reveal(),
            $this->storeExtractor->reveal()
        );
    }

    private function createIncident(?Task $task = null, ?User $createdBy = null): Incident
    {
        $incident = new Incident();

        if (null !== $task) {
            $incident->setTask($task);
        }

        if (null !== $createdBy) {
            $incident->setCreatedBy($createdBy);
        }

        return $incident;
    }

    private function createTaskForStore(Store $store): Task
    {
        $task = new Task();

        $delivery = new Delivery();
        $delivery->setStore($store);
        $task->setDelivery($delivery);

        return $task;
    }

    private function createOAuth2Token(): OAuth2Token
    {
        return new OAuth2Token(null, 'access_token_id', 'oauth_client_id', ['tasks'], 'ROLE_OAUTH2_');
    }

    private function createToken(?User $user): TokenInterface
    {
        $token = $this->prophesize(TokenInterface::class);
        $token->getUser()->willReturn($user);

        return $token->reveal();
    }

    public function testDispatcherCanDoAnything(): void
    {
        $this->authorizationChecker->isGranted('ROLE_DISPATCHER')->willReturn(true);

        $incident = $this->createIncident(new Task(), new User());
        $token = $this->createToken(new User());

        foreach ([IncidentVoter::VIEW, IncidentVoter::EDIT, IncidentVoter::CREATE] as $attribute) {
            $this->assertSame(
                VoterInterface::ACCESS_GRANTED,
                $this->voter->vote($token, $incident, [$attribute])
            );
        }
    }

    public function testCourierAssignedToTheTaskIsGranted(): void
    {
        $this->authorizationChecker->isGranted('ROLE_COURIER')->willReturn(true);

        $courier = new User();

        $task = new Task();
        $task->assignTo($courier);

        $incident = $this->createIncident($task);
        $token = $this->createToken($courier);

        foreach ([IncidentVoter::VIEW, IncidentVoter::EDIT, IncidentVoter::CREATE] as $attribute) {
            $this->assertSame(
                VoterInterface::ACCESS_GRANTED,
                $this->voter->vote($token, $incident, [$attribute])
            );
        }
    }

    public function testCourierNotAssignedToTheTaskIsDenied(): void
    {
        $this->authorizationChecker->isGranted('ROLE_COURIER')->willReturn(true);

        $task = new Task();
        $task->assignTo(new User());

        $incident = $this->createIncident($task, new User());
        $token = $this->createToken(new User());

        foreach ([IncidentVoter::VIEW, IncidentVoter::EDIT, IncidentVoter::CREATE] as $attribute) {
            $this->assertSame(
                VoterInterface::ACCESS_DENIED,
                $this->voter->vote($token, $incident, [$attribute])
            );
        }
    }

    public function testCourierIsDeniedWhenTheTaskIsUnassigned(): void
    {
        $this->authorizationChecker->isGranted('ROLE_COURIER')->willReturn(true);

        $incident = $this->createIncident(new Task());
        $token = $this->createToken(new User());

        $this->assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($token, $incident, [IncidentVoter::EDIT])
        );
    }

    public function testCourierKeepsAccessToTheIncidentTheyReported(): void
    {
        $this->authorizationChecker->isGranted('ROLE_COURIER')->willReturn(true);

        $courier = new User();

        // The task has been re-assigned to somebody else in the meantime
        $task = new Task();
        $task->assignTo(new User());

        $incident = $this->createIncident($task, $courier);
        $token = $this->createToken($courier);

        $this->assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($token, $incident, [IncidentVoter::VIEW])
        );
    }

    public function testCourierIsDeniedWhenTheIncidentHasNoTaskYet(): void
    {
        $this->authorizationChecker->isGranted('ROLE_COURIER')->willReturn(true);

        $incident = $this->createIncident();
        $token = $this->createToken(new User());

        $this->assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($token, $incident, [IncidentVoter::CREATE])
        );
    }

    public function testPlainUserIsDenied(): void
    {
        $task = new Task();
        $task->assignTo($user = new User());

        $incident = $this->createIncident($task, $user);
        $token = $this->createToken($user);

        $this->assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($token, $incident, [IncidentVoter::VIEW])
        );
    }

    public function testStoreIsGrantedOnItsOwnTasks(): void
    {
        $this->authorizationChecker->isGranted('ROLE_OAUTH2_TASKS')->willReturn(true);

        $store = new Store();
        $this->storeExtractor->extractStore()->willReturn($store);

        $incident = $this->createIncident($this->createTaskForStore($store));

        $this->assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($this->createOAuth2Token(), $incident, [IncidentVoter::EDIT])
        );
    }

    public function testStoreIsDeniedOnTheTasksOfAnotherStore(): void
    {
        $this->authorizationChecker->isGranted('ROLE_OAUTH2_TASKS')->willReturn(true);

        $this->storeExtractor->extractStore()->willReturn(new Store());

        $incident = $this->createIncident($this->createTaskForStore(new Store()));

        $this->assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($this->createOAuth2Token(), $incident, [IncidentVoter::EDIT])
        );
    }

    public function testStoreWithoutTokenStoreIsDenied(): void
    {
        $this->authorizationChecker->isGranted('ROLE_OAUTH2_TASKS')->willReturn(true);

        $this->storeExtractor->extractStore()->willReturn(null);

        $incident = $this->createIncident($this->createTaskForStore(new Store()));

        $this->assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($this->createOAuth2Token(), $incident, [IncidentVoter::VIEW])
        );
    }
}
