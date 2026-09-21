<?php

declare(strict_types=1);

namespace Tests\AppBundle\Entity\Task;

use AppBundle\Entity\Task\RecurrenceRuleGeneration;
use AppBundle\Entity\Task\RecurrenceRuleGenerationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The lock that keeps a date from being generated twice is enforced by the
 * database, so it is only worth testing against one.
 */
class RecurrenceRuleGenerationRepositoryTest extends KernelTestCase
{
    private const DATE = '2030-01-15';

    private ?EntityManagerInterface $entityManager;
    private RecurrenceRuleGenerationRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->repository = self::getContainer()->get(RecurrenceRuleGenerationRepository::class);

        $this->entityManager->getConnection()->executeStatement('DELETE FROM task_rrule_generation');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
        $this->entityManager = null;
    }

    public function testClaimsADateNobodyHasTaken()
    {
        $this->assertTrue($this->repository->claim(self::DATE));

        $generation = $this->repository->findOneByDate(self::DATE);

        $this->assertNotNull($generation);
        $this->assertEquals(RecurrenceRuleGeneration::STATUS_PENDING, $generation->getStatus());
        $this->assertEquals(0, $generation->getAttempts());
    }

    public function testDoesNotClaimADateAlreadyQueued()
    {
        $this->assertTrue($this->repository->claim(self::DATE));

        // A second dispatcher opening the dashboard before the worker got to it
        $this->assertFalse($this->repository->claim(self::DATE));
    }

    public function testDoesNotClaimADateBeingGenerated()
    {
        $this->repository->claim(self::DATE);

        $generation = $this->repository->findOneByDate(self::DATE);
        $generation->start();
        $this->entityManager->flush();

        $this->assertFalse($this->repository->claim(self::DATE));
    }

    public function testClaimsADateWhoseRunWentQuiet()
    {
        $this->repository->claim(self::DATE);

        $generation = $this->repository->findOneByDate(self::DATE);
        $generation->start();
        $this->entityManager->flush();

        // The worker was killed mid-generation and nothing ever finished the
        // row, so the date must not stay locked for good
        $this->entityManager->getConnection()->executeStatement(
            "UPDATE task_rrule_generation SET updated_at = updated_at - INTERVAL '1 hour' WHERE date = :date",
            ['date' => self::DATE]
        );

        $this->assertTrue($this->repository->claim(self::DATE));

        $this->entityManager->refresh($generation);

        $this->assertEquals(RecurrenceRuleGeneration::STATUS_PENDING, $generation->getStatus());
    }

    public function testClaimsADateAgainOnceTheRunIsOver()
    {
        $this->repository->claim(self::DATE);

        $generation = $this->repository->findOneByDate(self::DATE);
        $generation->start();
        $generation->fail(1, 'Something went wrong');
        $generation->finish();
        $this->entityManager->flush();

        $this->assertEquals(RecurrenceRuleGeneration::STATUS_FAILED, $generation->getStatus());

        $createdAt = $generation->getCreatedAt();

        // A run that is over, successful or not, does not hold the date
        $this->assertTrue($this->repository->claim(self::DATE));

        $this->entityManager->refresh($generation);

        // The upsert re-queues the row it finds, it does not replace it
        $this->assertEquals($createdAt, $generation->getCreatedAt());

        $this->assertEquals(RecurrenceRuleGeneration::STATUS_PENDING, $generation->getStatus());
        $this->assertEquals(0, $generation->getAttempts());
        $this->assertEquals(0, $generation->getFailed());
        $this->assertEquals([], $generation->getErrors());
        $this->assertNull($generation->getFinishedAt());
    }
}
