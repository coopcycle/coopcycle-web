<?php

namespace Tests\AppBundle\Integration\Rdc;

use AppBundle\Entity\Rdc\RdcProcessedWebhook;
use AppBundle\Integration\Rdc\Webhook\RdcIdempotencyChecker;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\NullLogger;

class RdcIdempotencyCheckerTest extends TestCase
{
    use ProphecyTrait;

    public function testIsAlreadyProcessedReturnsFalseForNewLoUri(): void
    {
        $entityManager = $this->prophesize(EntityManagerInterface::class);

        $repository = $this->prophesize(\Doctrine\Persistence\ObjectRepository::class);
        $repository->findOneBy(['loUri' => 'lo://new'])->willReturn(null);

        $entityManager
            ->getRepository(RdcProcessedWebhook::class)
            ->willReturn($repository->reveal());

        $checker = new RdcIdempotencyChecker($entityManager->reveal(), new NullLogger());

        $this->assertFalse($checker->isAlreadyProcessed('lo://new'));
    }

    public function testIsAlreadyProcessedReturnsTrueForExistingLoUri(): void
    {
        $entityManager = $this->prophesize(EntityManagerInterface::class);

        $existingWebhook = new RdcProcessedWebhook('lo://existing', 'create');

        $repository = $this->prophesize(\Doctrine\Persistence\ObjectRepository::class);
        $repository->findOneBy(['loUri' => 'lo://existing'])->willReturn($existingWebhook);

        $entityManager
            ->getRepository(RdcProcessedWebhook::class)
            ->willReturn($repository->reveal());

        $checker = new RdcIdempotencyChecker($entityManager->reveal(), new NullLogger());

        $this->assertTrue($checker->isAlreadyProcessed('lo://existing'));
    }

    public function testMarkAsProcessedPersistsAndFlushes(): void
    {
        $entityManager = $this->prophesize(EntityManagerInterface::class);
        $entityManager->persist(Argument::any())->shouldBeCalled();
        $entityManager->flush()->shouldBeCalled();

        $checker = new RdcIdempotencyChecker($entityManager->reveal(), new NullLogger());

        $checker->markAsProcessed('lo://test', 'create');
    }

    public function testResolveEventTypeReturnsUpdateForCreateOnExisting(): void
    {
        $entityManager = $this->prophesize(EntityManagerInterface::class);

        $existingWebhook = new RdcProcessedWebhook('lo://existing', 'create');

        $repository = $this->prophesize(\Doctrine\Persistence\ObjectRepository::class);
        $repository->findOneBy(['loUri' => 'lo://existing'])->willReturn($existingWebhook);

        $entityManager
            ->getRepository(RdcProcessedWebhook::class)
            ->willReturn($repository->reveal());

        $checker = new RdcIdempotencyChecker($entityManager->reveal(), new NullLogger());

        $result = $checker->resolveEventType('lo://existing', 'create');

        $this->assertEquals('update', $result);
    }

    public function testResolveEventTypeReturnsOriginalForNewLoUri(): void
    {
        $entityManager = $this->prophesize(EntityManagerInterface::class);

        $repository = $this->prophesize(\Doctrine\Persistence\ObjectRepository::class);
        $repository->findOneBy(['loUri' => 'lo://new'])->willReturn(null);

        $entityManager
            ->getRepository(RdcProcessedWebhook::class)
            ->willReturn($repository->reveal());

        $checker = new RdcIdempotencyChecker($entityManager->reveal(), new NullLogger());

        $result = $checker->resolveEventType('lo://new', 'create');

        $this->assertEquals('create', $result);
    }
}