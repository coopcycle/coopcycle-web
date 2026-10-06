<?php

declare(strict_types=1);

namespace Tests\AppBundle\MessageHandler;

use AppBundle\Entity\Task\RecurrenceRuleGeneration;
use AppBundle\Entity\Task\RecurrenceRuleGenerationRepository;
use AppBundle\Message\GenerateOrdersForDate;
use AppBundle\MessageHandler\GenerateOrdersForDateHandler;
use Doctrine\ORM\EntityManagerInterface;
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
}
