<?php

declare(strict_types=1);

namespace Tests\AppBundle\MessageHandler;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Task;
use AppBundle\Entity\Task\RecurrenceRule;
use AppBundle\Entity\Task\RecurrenceRuleGeneration;
use AppBundle\Entity\Task\RecurrenceRuleGenerationRepository;
use AppBundle\Exception\GenerateOrdersException;
use AppBundle\Fixtures\DatabasePurger;
use AppBundle\Message\GenerateOrdersForDate;
use AppBundle\MessageHandler\GenerateOrdersForDateHandler;
use AppBundle\Messenger\TransactionalMessages;
use AppBundle\Service\DeliveryCreatedNotifier;
use AppBundle\Service\DeliveryOrderManager;
use AppBundle\Sylius\Order\OrderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * A worker that dies mid-run never acknowledges its message, so the transport
 * delivers it again, outside of Messenger's retry count. On lcr, a worker running
 * out of memory picked the same date up 92 times in 4 minutes, creating a
 * delivery each time.
 */
class GenerateOrdersForDateHandlerTest extends KernelTestCase
{
    private const DATE = '2030-01-15';
    private const MONDAY = '2030-01-14';

    private ?EntityManagerInterface $entityManager;
    private RecurrenceRuleGenerationRepository $repository;
    private GenerateOrdersForDateHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->repository = self::getContainer()->get(RecurrenceRuleGenerationRepository::class);
        $this->handler = self::getContainer()->get(GenerateOrdersForDateHandler::class);

        $this->entityManager->getConnection()->executeStatement('DELETE FROM task_rrule_generation');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
        $this->entityManager = null;
    }

    private function createGeneration(int $attempts): RecurrenceRuleGeneration
    {
        $generation = new RecurrenceRuleGeneration(new \DateTime(self::DATE));
        for ($i = 0; $i < $attempts; $i++) {
            $generation->start();
        }

        $this->entityManager->persist($generation);
        $this->entityManager->flush();

        return $generation;
    }

    public function testGivesUpOnARunThatKeepsCrashing()
    {
        // Four runs started, none of them got to finish()
        $this->createGeneration(4);

        ($this->handler)(new GenerateOrdersForDate(self::DATE));

        $this->entityManager->clear();
        $generation = $this->repository->findOneByDate(self::DATE);

        $this->assertEquals(RecurrenceRuleGeneration::STATUS_FAILED, $generation->getStatus());
        // Not started a fifth time
        $this->assertEquals(4, $generation->getAttempts());
        $this->assertEquals([
            ['recurrence_rule' => null, 'message' => 'The worker crashed 4 times in a row'],
        ], $generation->getErrors());
    }

    public function testRetriesARunThatCrashedLessThanTheLimit()
    {
        $this->createGeneration(3);

        ($this->handler)(new GenerateOrdersForDate(self::DATE));

        $this->entityManager->clear();
        $generation = $this->repository->findOneByDate(self::DATE);

        $this->assertEquals(RecurrenceRuleGeneration::STATUS_COMPLETED, $generation->getStatus());
        $this->assertEquals(4, $generation->getAttempts());
    }

    public function testRetriesARunThatFailedCleanly()
    {
        // Messenger's own retries ran out, each run reached finish(), and the
        // message is retried by hand from the failed transport.
        $generation = $this->createGeneration(4);
        $generation->fail(42, 'No rule matched');
        $generation->finish();
        $this->entityManager->flush();

        ($this->handler)(new GenerateOrdersForDate(self::DATE));

        $this->entityManager->clear();
        $generation = $this->repository->findOneByDate(self::DATE);

        $this->assertEquals(RecurrenceRuleGeneration::STATUS_COMPLETED, $generation->getStatus());
        $this->assertEquals(5, $generation->getAttempts());
    }

    public function testRollsBackARuleThatFailsHalfway()
    {
        // LOAD FIXTURES: two rules generating orders on mondays and fridays
        $dbPurger = self::getContainer()->get(DatabasePurger::class);
        $dbPurger->purge();
        $dbPurger->resetSequences();

        $fixturesLoader = self::getContainer()->get('fidry_alice_data_fixtures.loader.doctrine');
        $fixturesLoader->load([
            __DIR__.'/../../../fixtures/ORM/settings_mandatory.yml',
            __DIR__.'/../../../fixtures/ORM/sylius_channels.yml',
            __DIR__.'/../../../fixtures/ORM/sylius_products.yml',
            __DIR__.'/../../../fixtures/ORM/sylius_taxation.yml',
            __DIR__.'/../../../fixtures/ORM/payment_methods.yml',
            __DIR__.'/../../../fixtures/ORM/recurrence_rules_generate_orders.yml',
        ], $_SERVER);

        [$firstRule, $secondRule] = $this->entityManager->getRepository(RecurrenceRule::class)->findBy([], ['id' => 'ASC']);

        // The first rule writes its order and tasks, then fails: on lcr, the
        // worker ran out of memory publishing the live updates of the new tasks.
        $failingDeliveryOrderManager = new class(
            self::getContainer()->get(DeliveryOrderManager::class),
            $firstRule->getId()
        ) extends DeliveryOrderManager {
            public function __construct(
                private readonly DeliveryOrderManager $inner,
                private readonly int $failingRuleId,
            )
            {
            }

            public function createOrderFromRecurrenceRule(
                RecurrenceRule $recurrenceRule,
                string $startDate,
                bool $persist = true,
                bool $throwException = false
            ): ?OrderInterface {
                $order = $this->inner->createOrderFromRecurrenceRule($recurrenceRule, $startDate, $persist, $throwException);

                if ($recurrenceRule->getId() === $this->failingRuleId) {
                    throw new \RuntimeException('Allowed memory size exhausted');
                }

                return $order;
            }
        };

        $handler = new GenerateOrdersForDateHandler(
            $this->entityManager,
            $this->repository,
            $failingDeliveryOrderManager,
            self::getContainer()->get(DeliveryCreatedNotifier::class),
            new NullLogger(),
            self::getContainer()->get(TransactionalMessages::class),
            self::getContainer()->get(ManagerRegistry::class),
        );

        try {
            $handler(new GenerateOrdersForDate(self::MONDAY));
            $this->fail('The failed rule should be retried');
        } catch (GenerateOrdersException $e) {
            $this->assertEquals(
                sprintf('Failed to generate recurring orders for 1 rule(s): %d', $firstRule->getId()),
                $e->getMessage()
            );
        }

        $this->entityManager->clear();

        $generation = $this->repository->findOneByDate(self::MONDAY);
        $this->assertEquals(RecurrenceRuleGeneration::STATUS_FAILED, $generation->getStatus());
        $this->assertEquals(1, $generation->getSucceeded());
        $this->assertEquals(1, $generation->getFailed());

        // Nothing is left of the first rule, the second one is committed
        $orders = $this->entityManager->getRepository(Order::class)->findAll();
        $this->assertCount(1, $orders);
        $this->assertEquals($secondRule->getId(), $orders[0]->getSubscription()->getId());
        $this->assertCount(1, $this->entityManager->getRepository(Delivery::class)->findAll());
        $this->assertCount(2, $this->entityManager->getRepository(Task::class)->findAll());

        // The retry generates the first rule only
        ($this->handler)(new GenerateOrdersForDate(self::MONDAY));

        $this->entityManager->clear();

        $generation = $this->repository->findOneByDate(self::MONDAY);
        $this->assertEquals(RecurrenceRuleGeneration::STATUS_COMPLETED, $generation->getStatus());
        $this->assertEquals(1, $generation->getSucceeded());

        $this->assertCount(2, $this->entityManager->getRepository(Order::class)->findAll());
        $this->assertCount(4, $this->entityManager->getRepository(Task::class)->findAll());
    }
}
