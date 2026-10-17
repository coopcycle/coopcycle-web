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
 * `PUT /api/task_lists/{id}/order` is the one whole-list write left. It is
 * only safe because it refuses to be anything but a reorder.
 *
 * @see https://github.com/coopcycle/coopcycle-web/issues/5372
 */
class TaskListOrderFunctionalTest extends ApiTestCase
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
     * @return array{0: User, 1: User}
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
     * @return array{0: object, 1: int, 2: array<int, string>} client, list id, task IRIs in order
     */
    private function aListOfThree(): array
    {
        [ $admin, $courier ] = $this->bootstrapUsers();

        $date = new \DateTime('today');

        $tasks = [];
        for ($i = 0; $i < 3; $i++) {
            $tasks[] = $this->createTask($date, $i);
        }
        $this->entityManager->flush();

        foreach ($tasks as $task) {
            $task->assignTo($courier);
        }
        $this->entityManager->flush();

        $taskList = $this->entityManager->getRepository(TaskList::class)
            ->findOneBy(['courier' => $courier, 'date' => $date]);

        $this->assertNotNull($taskList, 'the courier should have a task list');

        $taskListId = $taskList->getId();
        $iris = array_map(fn (Task $t) => '/api/tasks/'.$t->getId(), $tasks);

        $this->entityManager->clear();

        $jwtManager = self::getContainer()->get(JWTTokenManagerInterface::class);
        $client = static::createClient(defaultOptions: [
            'headers' => ['authorization' => 'Bearer '.$jwtManager->create($admin)],
        ]);

        return [$client, $taskListId, $iris];
    }

    /**
     * @return array<int, string> the task IRIs on a list, in order
     */
    private function readItemIris(int $taskListId): array
    {
        $rows = $this->entityManager->getConnection()->fetchAllAssociative(
            'SELECT task_id FROM task_list_item WHERE parent_id = ? ORDER BY position ASC',
            [$taskListId]
        );

        return array_map(fn (array $row) => '/api/tasks/'.$row['task_id'], $rows);
    }

    private function readVersion(int $taskListId): int
    {
        return (int) $this->entityManager->getConnection()->fetchOne(
            'SELECT version FROM task_list WHERE id = ?',
            [$taskListId]
        );
    }

    public function testTheOrderIsRewritten()
    {
        [$client, $taskListId, $iris] = $this->aListOfThree();

        $wanted = [$iris[2], $iris[0], $iris[1]];

        $client->request('PUT', sprintf('/api/task_lists/%d/order', $taskListId), [
            'headers' => ['If-Match' => sprintf('"%d"', $this->readVersion($taskListId))],
            'json' => ['items' => $wanted],
        ]);

        $this->assertResponseStatusCodeSame(200);
        $this->assertSame($wanted, $this->readItemIris($taskListId));
    }

    /**
     * The property the whole split exists for: an ordering write cannot be the
     * thing that unassigns somebody's work.
     */
    public function testAPayloadThatDropsAnItemIsRefused()
    {
        [$client, $taskListId, $iris] = $this->aListOfThree();

        $client->request('PUT', sprintf('/api/task_lists/%d/order', $taskListId), [
            'headers' => ['If-Match' => sprintf('"%d"', $this->readVersion($taskListId))],
            'json' => ['items' => [$iris[0], $iris[1]]],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSame($iris, $this->readItemIris($taskListId), 'nothing should have moved');
    }

    public function testAPayloadThatAddsAnItemIsRefused()
    {
        [$client, $taskListId, $iris] = $this->aListOfThree();

        $stranger = $this->createTask(new \DateTime('today'), 42);
        $this->entityManager->flush();

        $client->request('PUT', sprintf('/api/task_lists/%d/order', $taskListId), [
            'headers' => ['If-Match' => sprintf('"%d"', $this->readVersion($taskListId))],
            'json' => ['items' => array_merge($iris, ['/api/tasks/'.$stranger->getId()])],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSame($iris, $this->readItemIris($taskListId), 'nothing should have been assigned');
    }

    public function testARepeatedItemIsRefused()
    {
        [$client, $taskListId, $iris] = $this->aListOfThree();

        $client->request('PUT', sprintf('/api/task_lists/%d/order', $taskListId), [
            'headers' => ['If-Match' => sprintf('"%d"', $this->readVersion($taskListId))],
            'json' => ['items' => [$iris[0], $iris[1], $iris[1]]],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSame($iris, $this->readItemIris($taskListId));
    }

    public function testTheVersionIsRequired()
    {
        [$client, $taskListId, $iris] = $this->aListOfThree();

        $client->request('PUT', sprintf('/api/task_lists/%d/order', $taskListId), [
            'json' => ['items' => array_reverse($iris)],
        ]);

        $this->assertResponseStatusCodeSame(428);
        $this->assertSame($iris, $this->readItemIris($taskListId));
    }

    public function testAStaleVersionIsRefused()
    {
        [$client, $taskListId, $iris] = $this->aListOfThree();

        $stale = $this->readVersion($taskListId);

        // Somebody else reorders first, moving the version on.
        $client->request('PUT', sprintf('/api/task_lists/%d/order', $taskListId), [
            'headers' => ['If-Match' => sprintf('"%d"', $stale)],
            'json' => ['items' => [$iris[1], $iris[0], $iris[2]]],
        ]);
        $this->assertResponseStatusCodeSame(200);

        $client->request('PUT', sprintf('/api/task_lists/%d/order', $taskListId), [
            'headers' => ['If-Match' => sprintf('"%d"', $stale)],
            'json' => ['items' => array_reverse($iris)],
        ]);

        $this->assertResponseStatusCodeSame(409);
        $this->assertSame(
            [$iris[1], $iris[0], $iris[2]],
            $this->readItemIris($taskListId),
            'the first write should stand'
        );
    }

    public function testAMalformedVersionIsARequestError()
    {
        [$client, $taskListId, $iris] = $this->aListOfThree();

        $client->request('PUT', sprintf('/api/task_lists/%d/order', $taskListId), [
            'headers' => ['If-Match' => 'not-a-version'],
            'json' => ['items' => array_reverse($iris)],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }
}
