<?php

namespace Tests\AppBundle\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Task;
use AppBundle\Entity\User;
use AppBundle\Fixtures\DatabasePurger;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Nucleos\UserBundle\Model\UserManager as UserManagerInterface;
use AppBundle\Domain\Task\Event\TaskDone;
use AppBundle\Message\Task\PublishLiveUpdate as PublishLiveUpdateMessage;
use AppBundle\MessageHandler\Task\PublishLiveUpdateHandler;
use Nucleos\UserBundle\Util\UserManipulator;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;

/**
 * A task completion has to carry its own state to the dispatch board.
 *
 * Until 7cba0ee65 ("Optimize dispatch assignation") every taggable entity was
 * marked dirty as it was hydrated, so a completion changeset also contained
 * `updatedAt`. TaskSubscriber only skips the `task:updated` event when the
 * status is the *sole* change, so completions used to emit `task:updated`
 * alongside `task:done`, and the board was kept in sync by that redundancy --
 * roughly 6.000 extra events a day across naofood, lcr and sicklo.
 *
 * That is now gone (a ~90% drop in `task:updated` from the day the fix was
 * deployed), which is correct, but it means the single `task:done` event is
 * the only thing telling the board a task was completed. These tests pin down
 * what that one event is worth.
 *
 * @see \AppBundle\Doctrine\EventSubscriber\TaskSubscriber::onFlush()
 * @see \AppBundle\Entity\Model\TaggableTrait::setTags()
 */
class TaskDoneLiveUpdateFunctionalTest extends ApiTestCase
{
    private $entityManager;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $dbPurger = self::getContainer()->get(DatabasePurger::class);
        $dbPurger->purge();
        $dbPurger->resetSequences();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->entityManager->close();
        $this->entityManager = null;
    }

    /**
     * @return array{0: User, 1: User} the dispatcher and the courier
     */
    private function bootstrapUsers(): array
    {
        $userManipulator = self::getContainer()->get(UserManipulator::class);
        $userManager = self::getContainer()->get(UserManagerInterface::class);
        $fixturesLoader = self::getContainer()->get('fidry_alice_data_fixtures.loader.doctrine');

        $fixturesLoader->load([
            __DIR__.'/../../../fixtures/ORM/settings_mandatory.yml',
            __DIR__.'/../../../fixtures/ORM/sylius_channels.yml',
        ], $_SERVER);

        $userManipulator->create('dispatcher', '12345678', 'dispatcher@demo.coopcycle.org', true, false);
        $userManipulator->addRole('dispatcher', 'ROLE_ADMIN');

        $userManipulator->create('courier', '12345678', 'courier@demo.coopcycle.org', true, false);
        $userManipulator->addRole('courier', 'ROLE_COURIER');

        return [
            $userManager->findUserByUsername('dispatcher'),
            $userManager->findUserByUsername('courier'),
        ];
    }

    private function createTaskAssignedTo(User $courier): Task
    {
        $address = new Address();
        $address->setStreetAddress('1, rue de Test, Paris');
        $address->setAddressLocality('Paris');
        $address->setPostalCode('75001');
        $address->setGeo(new GeoCoordinates('48.8640', '2.33'));

        $date = new \DateTime('today');

        $task = new Task();
        $task->setAddress($address);
        $task->setAfter((clone $date)->setTime(10, 0));
        $task->setBefore((clone $date)->setTime(18, 0));

        $this->entityManager->persist($address);
        $this->entityManager->persist($task);
        $this->entityManager->flush();

        $task->assignTo($courier);
        $this->entityManager->flush();

        return $task;
    }

    /**
     * @return string[] the names of the events recorded against the task
     */
    private function recordedEventNames(int $taskId): array
    {
        $names = $this->entityManager->getConnection()->fetchFirstColumn(
            'SELECT name FROM task_event WHERE task_id = ? ORDER BY id',
            [$taskId]
        );

        return $names;
    }

    /**
     * Completing a task is a pure status change, so TaskSubscriber takes the
     * `$isOnlyStatusChange` branch and emits no `task:updated`. This is the
     * behaviour the Sept 29 optimization introduced; the test exists so that
     * anyone who makes the board depend on `task:updated` again finds out here
     * rather than from a coop in the middle of a dinner service.
     */
    public function testCompletingATaskEmitsDoneAndNoLongerEmitsUpdated()
    {
        $jwtManager = self::getContainer()->get(JWTTokenManagerInterface::class);

        [ , $courier ] = $this->bootstrapUsers();

        $task = $this->createTaskAssignedTo($courier);
        $taskId = $task->getId();

        // Assigning the task already recorded events of its own; only the ones
        // the completion adds are interesting.
        $before = $this->recordedEventNames($taskId);

        $this->entityManager->clear();

        $client = static::createClient(defaultOptions: [
            'headers' => ['authorization' => 'Bearer '.$jwtManager->create($courier)],
        ]);

        $client->request('PUT', sprintf('/api/tasks/%d/done', $taskId), [
            'json' => ['notes' => ''],
        ]);

        $this->assertResponseStatusCodeSame(200);

        $added = array_values(array_diff(
            $this->recordedEventNames($taskId),
            $before
        ));

        $this->assertContains('task:done', $added,
            'completing a task must record a task:done event');

        $this->assertNotContains('task:updated', $added,
            'a pure status change takes the $isOnlyStatusChange branch, so no '
            .'task:updated is emitted -- the board has only task:done to go on');
    }

    /**
     * The message carries a task id, not a task, and it is queued from the domain
     * event -- which is dispatched before the request has flushed. So the worker
     * can read a version of the task that predates the completion, and publishing
     * it sends a `task:done` carrying `status=TODO`: the dispatch board then shows
     * a task as still to do, minutes after the courier completed it.
     *
     * Observed on lcr on 2026-10-05: task#141178 published `task:done` with
     * `status=TODO` and an `updatedAt` 22 minutes behind.
     */
    public function testAStaleReadIsRetriedRatherThanPublishedAsDone()
    {
        [ , $courier ] = $this->bootstrapUsers();

        // Never completed: this is what the worker sees when it wins the race
        // against the commit.
        $task = $this->createTaskAssignedTo($courier);

        $this->assertSame(Task::STATUS_TODO, $task->getStatus());

        $handler = self::getContainer()->get(PublishLiveUpdateHandler::class);

        $this->expectException(RecoverableMessageHandlingException::class);

        $handler(new PublishLiveUpdateMessage($task->getId(), TaskDone::class));
    }

    /**
     * The guard must only reject a genuinely stale read -- once the completion is
     * visible, the event publishes as before.
     */
    public function testACommittedCompletionIsPublished()
    {
        [ , $courier ] = $this->bootstrapUsers();

        $task = $this->createTaskAssignedTo($courier);

        $task->setStatus(Task::STATUS_DONE);
        $this->entityManager->flush();

        $handler = self::getContainer()->get(PublishLiveUpdateHandler::class);

        $handler(new PublishLiveUpdateMessage($task->getId(), TaskDone::class));

        $this->assertSame(Task::STATUS_DONE, $task->getStatus(),
            'a committed completion must publish without being retried');
    }
}
