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
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Nucleos\UserBundle\Model\UserManager as UserManagerInterface;
use Nucleos\UserBundle\Util\UserManipulator;

/**
 * `POST /api/task_lists/{id}/items` names what it wants. Everything it does
 * not name has to survive untouched — that is the whole point of it existing
 * next to `set_items`.
 *
 * @see https://github.com/coopcycle/coopcycle-web/issues/5372
 */
class AddTaskListItemsFunctionalTest extends ApiTestCase
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
     * @return array{0: User, 1: User, 2: User} dispatcher, and two couriers
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

        foreach (['courier', 'courier2'] as $username) {
            $userManipulator->create($username, '12345678', $username.'@demo.coopcycle.org', true, false);
            $userManipulator->addRole($username, 'ROLE_COURIER');
        }

        return [
            $userManager->findUserByUsername('dispatcher'),
            $userManager->findUserByUsername('courier'),
            $userManager->findUserByUsername('courier2'),
        ];
    }

    /**
     * Assign tasks the way production does, letting the Doctrine subscribers
     * build the task list and its items.
     *
     * @param Task[] $tasks
     */
    private function assignAll(array $tasks, User $courier): void
    {
        foreach ($tasks as $task) {
            $task->assignTo($courier);
        }

        $this->entityManager->flush();
    }

    private function listFor(User $courier, \DateTime $date): TaskList
    {
        $taskList = $this->entityManager->getRepository(TaskList::class)
            ->findOneBy(['courier' => $courier, 'date' => $date]);

        $this->assertNotNull($taskList, sprintf('%s should have a task list', $courier->getUsername()));

        return $taskList;
    }

    /**
     * @return array<int, string> the task IRIs on a list, in order
     */
    private function readItemIris(int $taskListId): array
    {
        $rows = $this->entityManager->getConnection()->fetchAllAssociative(
            'SELECT task_id, tour_id FROM task_list_item WHERE parent_id = ? ORDER BY position ASC',
            [$taskListId]
        );

        return array_map(
            fn (array $row) => null !== $row['task_id']
                ? '/api/tasks/'.$row['task_id']
                : '/api/tours/'.$row['tour_id'],
            $rows
        );
    }

    private function client(User $admin)
    {
        $jwtManager = self::getContainer()->get(JWTTokenManagerInterface::class);

        return static::createClient(defaultOptions: [
            'headers' => ['authorization' => 'Bearer '.$jwtManager->create($admin)],
        ]);
    }

    public function testTheItemsAlreadyOnTheListAreLeftAlone()
    {
        [ $admin, $courier ] = $this->bootstrapUsers();

        $date = new \DateTime('today');

        $existing = [];
        for ($i = 0; $i < 5; $i++) {
            $existing[] = $this->createTask($date, $i);
        }
        $newTask = $this->createTask($date, 99);
        $this->entityManager->flush();

        $this->assignAll($existing, $courier);

        $taskListId = $this->listFor($courier, $date)->getId();
        $existingIris = array_map(fn (Task $t) => '/api/tasks/'.$t->getId(), $existing);
        $newTaskIri = '/api/tasks/'.$newTask->getId();

        $this->entityManager->clear();

        $client = $this->client($admin);
        $client->request('POST', sprintf('/api/task_lists/%d/items', $taskListId), [
            'json' => ['items' => [$newTaskIri]],
        ]);

        $this->assertResponseStatusCodeSame(201);

        $this->assertSame(
            array_merge($existingIris, [$newTaskIri]),
            $this->readItemIris($taskListId),
            'naming one task must not disturb the five that were already there'
        );
    }

    public function testAnItemCanBeInsertedAtAPosition()
    {
        [ $admin, $courier ] = $this->bootstrapUsers();

        $date = new \DateTime('today');

        $existing = [];
        for ($i = 0; $i < 3; $i++) {
            $existing[] = $this->createTask($date, $i);
        }
        $newTask = $this->createTask($date, 99);
        $this->entityManager->flush();

        $this->assignAll($existing, $courier);

        $taskListId = $this->listFor($courier, $date)->getId();
        $existingIris = array_map(fn (Task $t) => '/api/tasks/'.$t->getId(), $existing);
        $newTaskIri = '/api/tasks/'.$newTask->getId();

        $this->entityManager->clear();

        $client = $this->client($admin);
        $client->request('POST', sprintf('/api/task_lists/%d/items', $taskListId), [
            'json' => ['items' => [$newTaskIri], 'position' => 1],
        ]);

        $this->assertResponseStatusCodeSame(201);

        $this->assertSame(
            [$existingIris[0], $newTaskIri, $existingIris[1], $existingIris[2]],
            $this->readItemIris($taskListId),
            'the task should land where the dispatcher dropped it'
        );
    }

    /**
     * A drag from one courier to another has to be one operation: the task
     * must not end up on both lists, nor on neither.
     */
    public function testATaskIsMovedOffTheListThatHeldIt()
    {
        [ $admin, $courier, $otherCourier ] = $this->bootstrapUsers();

        $date = new \DateTime('today');

        $task = $this->createTask($date, 1);
        $staysPut = $this->createTask($date, 2);
        $this->entityManager->flush();

        $this->assignAll([$task, $staysPut], $otherCourier);

        $sourceListId = $this->listFor($otherCourier, $date)->getId();
        $taskIri = '/api/tasks/'.$task->getId();
        $staysPutIri = '/api/tasks/'.$staysPut->getId();

        // The receiving courier has no list yet, which is the normal case for
        // the first assignment of the day.
        $this->entityManager->clear();

        $client = $this->client($admin);
        $client->request('POST', '/api/task_lists', [
            'json' => [
                'courier' => '/api/users/'.$courier->getId(),
                'date' => $date->format('Y-m-d'),
            ],
        ]);
        $this->assertResponseIsSuccessful();

        $targetListId = $this->listFor($courier, $date)->getId();

        $this->entityManager->clear();

        $client->request('POST', sprintf('/api/task_lists/%d/items', $targetListId), [
            'json' => ['items' => [$taskIri]],
        ]);

        $this->assertResponseStatusCodeSame(201);

        $this->assertSame(
            [$taskIri],
            $this->readItemIris($targetListId),
            'the task should be on the list it was dropped on'
        );
        $this->assertSame(
            [$staysPutIri],
            $this->readItemIris($sourceListId),
            'and gone from the one it came from, without taking its neighbour along'
        );
    }

    public function testNamingATaskTwiceMovesItRatherThanDuplicatingIt()
    {
        [ $admin, $courier ] = $this->bootstrapUsers();

        $date = new \DateTime('today');

        $tasks = [];
        for ($i = 0; $i < 3; $i++) {
            $tasks[] = $this->createTask($date, $i);
        }
        $this->entityManager->flush();

        $this->assignAll($tasks, $courier);

        $taskListId = $this->listFor($courier, $date)->getId();
        $iris = array_map(fn (Task $t) => '/api/tasks/'.$t->getId(), $tasks);

        $this->entityManager->clear();

        $client = $this->client($admin);
        $client->request('POST', sprintf('/api/task_lists/%d/items', $taskListId), [
            'json' => ['items' => [$iris[2]], 'position' => 0],
        ]);

        $this->assertResponseStatusCodeSame(201);

        $this->assertSame(
            [$iris[2], $iris[0], $iris[1]],
            $this->readItemIris($taskListId),
            'the task should have moved to the top, not been added a second time'
        );
    }

    /**
     * The guard from `rejectItemsFromOtherDays()` still applies: a task that
     * cannot be carried out that day is dropped rather than filed, so it stays
     * visible on the board for its own day.
     */
    public function testATaskFromAnotherDayIsNotFiled()
    {
        [ $admin, $courier ] = $this->bootstrapUsers();

        $date = new \DateTime('today');

        $onTime = $this->createTask($date, 1);
        $this->entityManager->flush();
        $this->assignAll([$onTime], $courier);

        $nextWeek = new \DateTime('today +7 days');
        $wrongDay = $this->createTask($nextWeek, 2);
        $this->entityManager->flush();

        $taskListId = $this->listFor($courier, $date)->getId();
        $onTimeIri = '/api/tasks/'.$onTime->getId();
        $wrongDayIri = '/api/tasks/'.$wrongDay->getId();

        $this->entityManager->clear();

        $client = $this->client($admin);
        $client->request('POST', sprintf('/api/task_lists/%d/items', $taskListId), [
            'json' => ['items' => [$wrongDayIri]],
        ]);

        $this->assertResponseStatusCodeSame(201);

        $this->assertSame(
            [$onTimeIri],
            $this->readItemIris($taskListId),
            'next week\'s task has no business on today\'s list'
        );
    }
}
