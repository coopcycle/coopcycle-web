<?php

namespace Tests\AppBundle\Action\Task;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use AppBundle\Entity\Task;
use AppBundle\Fixtures\DatabasePurger;
use Doctrine\ORM\EntityManagerInterface;
use Fidry\AliceDataFixtures\LoaderInterface;
use Fidry\AliceDataFixtures\Persistence\PurgeMode;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

/**
 * Functional test for the BulkMarkAsDone action.
 *
 * Regression test for the bug where tasks would get unassigned after being
 * confirmed done via the BulkMarkAsDone endpoint. The scenario is a courier
 * sliding multiple close drop-offs simultaneously, which triggers the endpoint
 * with two tasks from different deliveries.
 *
 * The bug manifests as tasks becoming unassigned (assignedTo = null) after
 * the BulkMarkAsDone call, due to intermediate Doctrine flushes caused by
 * the OrderFulfilled → UpdateState chain that ran synchronously for each task.
 */
class BulkMarkAsDoneTest extends ApiTestCase
{
    private ?EntityManagerInterface $entityManager;
    private LoaderInterface $fixturesLoader;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->fixturesLoader = self::getContainer()->get('fidry_alice_data_fixtures.loader.doctrine');

        $dbPurger = self::getContainer()->get(DatabasePurger::class);
        $dbPurger->purge();
        $dbPurger->resetSequences();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        /** @see https://joeymasip.medium.com/symfony-phpunit-testing-database-data-322383ed0603 */
        $this->entityManager->close();
        $this->entityManager = null;
    }

    public function testBulkMarkAsDoneKeepsTasksAssigned(): void
    {
        $entities = $this->fixturesLoader->load([
            __DIR__ . '/../../../../fixtures/ORM/bulk_mark_as_done.yml',
        ], $_SERVER, [], PurgeMode::createNoPurgeMode());

        /** @var \AppBundle\Entity\User $jane */
        $jane = $entities['jane'];

        /** @var Task $dropoff1 */
        $dropoff1 = $entities['dropoff_1'];
        /** @var Task $dropoff2 */
        $dropoff2 = $entities['dropoff_2'];

        $this->assertNotNull($dropoff1->getAssignedCourier(), 'dropoff_1 should be assigned before BulkMarkAsDone');
        $this->assertNotNull($dropoff2->getAssignedCourier(), 'dropoff_2 should be assigned before BulkMarkAsDone');

        $jwtManager = self::getContainer()->get(JWTTokenManagerInterface::class);
        $token = $jwtManager->create($jane);

        $client = static::createClient(defaultOptions: [
            'headers' => [
                'authorization' => 'Bearer ' . $token,
            ],
        ]);

        $dropoff1Iri = '/api/tasks/' . $dropoff1->getId();
        $dropoff2Iri = '/api/tasks/' . $dropoff2->getId();

        $client->request('PUT', '/api/tasks/done', [
            'json' => [
                'tasks' => [$dropoff1Iri, $dropoff2Iri],
            ],
        ]);

        $this->assertResponseStatusCodeSame(200);

        $responseData = $client->getResponse()->toArray();
        $this->assertEmpty($responseData['failed'], 'No tasks should fail to be marked as done');
        $this->assertCount(2, $responseData['success'], 'Both tasks should be marked as done');

        // Re-fetch tasks from database to get their current state
        $this->entityManager->clear();

        $taskRepo = $this->entityManager->getRepository(Task::class);

        $freshDropoff1 = $taskRepo->find($dropoff1->getId());
        $freshDropoff2 = $taskRepo->find($dropoff2->getId());

        $this->assertNotNull($freshDropoff1);
        $this->assertNotNull($freshDropoff2);

        $this->assertEquals(Task::STATUS_DONE, $freshDropoff1->getStatus(), 'dropoff_1 should be DONE');
        $this->assertEquals(Task::STATUS_DONE, $freshDropoff2->getStatus(), 'dropoff_2 should be DONE');

        // Core assertion: tasks must remain assigned to their courier after being marked done
        $this->assertNotNull(
            $freshDropoff1->getAssignedCourier(),
            'dropoff_1 should still be assigned after BulkMarkAsDone'
        );
        $this->assertEquals(
            'jane',
            $freshDropoff1->getAssignedCourier()->getUserIdentifier(),
            'dropoff_1 should still be assigned to jane after BulkMarkAsDone'
        );

        $this->assertNotNull(
            $freshDropoff2->getAssignedCourier(),
            'dropoff_2 should still be assigned after BulkMarkAsDone'
        );
        $this->assertEquals(
            'jane',
            $freshDropoff2->getAssignedCourier()->getUserIdentifier(),
            'dropoff_2 should still be assigned to jane after BulkMarkAsDone'
        );
    }
}
