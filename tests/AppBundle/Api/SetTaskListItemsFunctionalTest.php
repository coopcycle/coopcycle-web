<?php

namespace Tests\AppBundle\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Task;
use AppBundle\Entity\TaskList;
use AppBundle\Entity\User;
use AppBundle\Fixtures\DatabasePurger;
use Doctrine\ORM\EntityManagerInterface;
use Fidry\AliceDataFixtures\Persistence\PurgeMode;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Nucleos\UserBundle\Util\UserManipulator;
use Nucleos\UserBundle\Model\UserManager as UserManagerInterface;

/**
 * Assigning or unassigning one task must not rewrite every other task that was
 * already on the courier's list.
 *
 * @see \AppBundle\Entity\Model\TaggableTrait::setTags()
 */
class SetTaskListItemsFunctionalTest extends ApiTestCase
{
    private const TASKS_ALREADY_ON_THE_LIST = 20;

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

    private function createTask(\DateTime $date, int $minute): Task
    {
        $geo = new GeoCoordinates('48.86'.str_pad((string) $minute, 4, '0', STR_PAD_LEFT), '2.33');

        $address = new Address();
        $address->setStreetAddress(sprintf('%d, rue de Test, Paris', $minute));
        $address->setAddressLocality('Paris');
        $address->setPostalCode('75001');
        $address->setGeo($geo);

        $task = new Task();
        $task->setAddress($address);
        $task->setAfter((clone $date)->setTime(10, 0));
        $task->setBefore((clone $date)->setTime(18, 0));

        $this->entityManager->persist($address);
        $this->entityManager->persist($task);

        return $task;
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

    /**
     * @return array<string, int> how many times each interesting statement ran
     */
    private function countQueries($client): array
    {
        $counts = ['task_update' => 0, 'task_event_select' => 0, 'edifact_select' => 0];

        foreach ($client->getProfile()->getCollector('db')->getQueries() as $queries) {
            foreach ($queries as $query) {
                $sql = preg_replace('/\s+/', ' ', is_array($query) ? $query['sql'] : $query->getSql());

                if (str_starts_with($sql, 'UPDATE task SET')) {
                    $counts['task_update']++;
                }
                if (str_contains($sql, 'FROM task_event t0 WHERE t0.task_id = ?')) {
                    $counts['task_event_select']++;
                }
                if (str_contains($sql, 'FROM edifact_message')) {
                    $counts['edifact_select']++;
                }
            }
        }

        return $counts;
    }

    /**
     * The endpoint still works — mobile apps in the field depend on it — but
     * it has to say that it is going away, and when, to clients that read the
     * response rather than the docs.
     *
     * @see https://github.com/coopcycle/coopcycle-web/issues/5372
     */
    public function testTheResponseSaysTheEndpointIsDeprecated()
    {
        $jwtManager = self::getContainer()->get(JWTTokenManagerInterface::class);

        [ $admin, $courier ] = $this->bootstrapUsers();

        $date = new \DateTime('today');
        $task = $this->createTask($date, 1);
        $this->entityManager->flush();

        $task->assignTo($courier);
        $this->entityManager->flush();

        $taskIri = '/api/tasks/'.$task->getId();

        $this->entityManager->clear();

        $client = static::createClient(defaultOptions: [
            'headers' => ['authorization' => 'Bearer '.$jwtManager->create($admin)],
        ]);

        $client->request('PUT', sprintf('/api/task_lists/set_items/%s/courier', $date->format('Y-m-d')), [
            'json' => ['items' => [$taskIri]],
        ]);

        $this->assertResponseIsSuccessful();

        $headers = $client->getResponse()->getHeaders();

        $this->assertSame(
            [(new \DateTimeImmutable(TaskList::SET_ITEMS_SUNSET))->format(\DateTimeInterface::RFC1123)],
            $headers['sunset'] ?? [],
            'clients need to know when this stops working'
        );
        $this->assertArrayHasKey('deprecation', $headers);
        $this->assertStringStartsWith('@', $headers['deprecation'][0]);

        $links = implode(', ', $headers['link'] ?? []);
        $this->assertStringContainsString('/api/task_lists/{id}/items>; rel="successor-version"', $links);
        $this->assertStringContainsString('/api/task_lists/{id}/order>; rel="successor-version"', $links);
    }

    public function testAssigningOneTaskDoesNotRewriteTheWholeList()
    {
        $jwtManager = self::getContainer()->get(JWTTokenManagerInterface::class);

        [ $admin, $courier ] = $this->bootstrapUsers();

        // The task list is created for the dispatch day, which the assignment
        // path resolves as "today", so build everything around today's date.
        $date = new \DateTime('today');

        $tasks = [];
        for ($i = 0; $i < self::TASKS_ALREADY_ON_THE_LIST; $i++) {
            $tasks[] = $this->createTask($date, $i);
        }

        // The one task the dispatcher drags onto the list
        $newTask = $this->createTask($date, 99);

        $this->entityManager->flush();

        // Assign them the way production does, and let the Doctrine subscribers
        // build the TaskList and its items.
        $assignedIris = [];
        foreach ($tasks as $task) {
            $task->assignTo($courier);
            $assignedIris[] = '/api/tasks/'.$task->getId();
        }
        $this->entityManager->flush();

        $newTaskIri = '/api/tasks/'.$newTask->getId();

        $taskList = $this->entityManager->getRepository(TaskList::class)
            ->findOneBy(['courier' => $courier, 'date' => $date]);

        $this->assertNotNull($taskList, 'the fixture should have produced a task list');
        $this->assertCount(
            self::TASKS_ALREADY_ON_THE_LIST,
            $taskList->getItems(),
            'every task should already be on the list before the request'
        );

        $this->entityManager->clear();

        $client = static::createClient(defaultOptions: [
            'headers' => ['authorization' => 'Bearer '.$jwtManager->create($admin)],
        ]);

        $client->enableProfiler();

        $client->request('PUT', sprintf('/api/task_lists/set_items/%s/courier', $date->format('Y-m-d')), [
            'json' => ['items' => array_merge($assignedIris, [$newTaskIri])],
        ]);

        $this->assertResponseStatusCodeSame(200);

        $counts = $this->countQueries($client);
        $taskUpdates = $counts['task_update'];
        $taskEventSelects = $counts['task_event_select'];
        $edifactSelects = $counts['edifact_select'];

        // Only the task that was actually added should be written.
        //
        // TaggableSubscriber::postLoad hands every taggable entity a lazy tag
        // loader through setTags(), which used to bump updatedAt -- so every
        // task was dirty the moment it was hydrated and the flush rewrote the
        // whole list. Assigning one task to a 20-task list issued 21 UPDATEs.
        $this->assertSame(1, $taskUpdates, 'only the newly assigned task should be written');

        // Each "updated" task used to initialise its own events collection, in
        // TaskSubscriber::onFlush -> Task::addEvent -> containsEventWithName.
        $this->assertLessThanOrEqual(1, $taskEventSelects, 'task events should not be loaded one task at a time');

        // TaskChangedNotifier is a postUpdate listener that only acts on a
        // status change, but used to touch the EDIFACT messages first -- one
        // query per updated task, always empty for coops with no transporter.
        $this->assertSame(0, $edifactSelects, 'a pure assignment should not look at EDIFACT messages');
    }

    /**
     * A bulk assignment does change every task, so they legitimately all go
     * through TaskSubscriber::onFlush -- but their events must still be loaded
     * in one batch rather than one query per task.
     */
    public function testBulkAssignmentLoadsTaskEventsInOneQuery()
    {
        $jwtManager = self::getContainer()->get(JWTTokenManagerInterface::class);

        [ $admin, $courier ] = $this->bootstrapUsers();

        $date = new \DateTime('today');

        $iris = [];
        for ($i = 0; $i < self::TASKS_ALREADY_ON_THE_LIST; $i++) {
            $this->createTask($date, $i);
        }
        $this->entityManager->flush();

        foreach ($this->entityManager->getRepository(Task::class)->findAll() as $task) {
            $iris[] = '/api/tasks/'.$task->getId();
        }

        $this->assertCount(self::TASKS_ALREADY_ON_THE_LIST, $iris);

        $this->entityManager->clear();

        $client = static::createClient(defaultOptions: [
            'headers' => ['authorization' => 'Bearer '.$jwtManager->create($admin)],
        ]);

        $client->enableProfiler();

        $client->request('PUT', sprintf('/api/task_lists/set_items/%s/courier', $date->format('Y-m-d')), [
            'json' => ['items' => $iris],
        ]);

        $this->assertResponseStatusCodeSame(200);

        $counts = $this->countQueries($client);

        // Every task really is assigned here, so every task is written.
        $this->assertSame(self::TASKS_ALREADY_ON_THE_LIST, $counts['task_update']);

        // ...but TaskSubscriber preloads their events, so this stays flat
        // instead of growing with the number of tasks.
        $this->assertLessThanOrEqual(2, $counts['task_event_select']);
    }

    /**
     * Adding or removing an item changes what the list holds, so the list's
     * version has to move with it — that is what a client's `If-Match` is
     * compared against.
     *
     * Doctrine only increments a version field as part of the UPDATE it issues
     * for a dirty entity, and a membership change touches the item rows, not
     * the list. Without `TaskCollectionVersionSubscriber` the list gets no
     * UPDATE at all and the version silently stands still while its contents
     * change — leaving a stale client free to write over them.
     */
    public function testChangingTheItemsBumpsTheListVersion()
    {
        $jwtManager = self::getContainer()->get(JWTTokenManagerInterface::class);

        [ $admin, $courier ] = $this->bootstrapUsers();

        $date = new \DateTime('today');

        $tasks = [];
        for ($i = 0; $i < 3; $i++) {
            $tasks[] = $this->createTask($date, $i);
        }
        $extraTask = $this->createTask($date, 99);
        $this->entityManager->flush();

        $iris = [];
        foreach ($tasks as $task) {
            $task->assignTo($courier);
            $iris[] = '/api/tasks/'.$task->getId();
        }
        $this->entityManager->flush();

        $extraIri = '/api/tasks/'.$extraTask->getId();

        $versionBefore = $this->readListVersion($courier, $date);
        $this->assertNotNull($versionBefore, 'the list should be versioned');

        $client = static::createClient(defaultOptions: [
            'headers' => ['authorization' => 'Bearer '.$jwtManager->create($admin)],
        ]);

        // Add one item
        $client->request('PUT', sprintf('/api/task_lists/set_items/%s/courier', $date->format('Y-m-d')), [
            'json' => ['items' => array_merge($iris, [$extraIri])],
        ]);
        $this->assertResponseStatusCodeSame(200);

        $versionAfterAdd = $this->readListVersion($courier, $date);
        $this->assertGreaterThan(
            $versionBefore,
            $versionAfterAdd,
            'adding an item should bump the version'
        );

        // Remove it again
        $client->request('PUT', sprintf('/api/task_lists/set_items/%s/courier', $date->format('Y-m-d')), [
            'json' => ['items' => $iris],
        ]);
        $this->assertResponseStatusCodeSame(200);

        $this->assertGreaterThan(
            $versionAfterAdd,
            $this->readListVersion($courier, $date),
            'removing an item should bump the version'
        );
    }

    /**
     * Read the persisted version, not a hydrated one: the request runs in its
     * own kernel, and this side's entity manager can hand back an entity that
     * predates it.
     */
    private function readListVersion(User $courier, \DateTime $date): ?int
    {
        $version = $this->entityManager->getConnection()->fetchOne(
            'SELECT version FROM task_list WHERE courier_id = ? AND date = ?',
            [$courier->getId(), $date->format('Y-m-d')]
        );

        $this->assertNotFalse($version, 'the courier should have a task list');

        return (int) $version;
    }
}
