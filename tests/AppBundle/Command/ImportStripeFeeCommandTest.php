<?php

namespace Tests\AppBundle\Command;

use AppBundle\Command\ImportStripeFeeCommand;
use AppBundle\Entity\Sylius\OrderRepository;
use AppBundle\Service\StripeManager;
use Carbon\Carbon;
use Doctrine\ORM\EntityManagerInterface;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class ImportStripeFeeCommandTest extends KernelTestCase
{
    use ProphecyTrait;

    public function setUp(): void
    {
        $this->orderRepository = $this->prophesize(OrderRepository::class);
        $this->entityManager = $this->prophesize(EntityManagerInterface::class);
        $this->adjustmentFactory = $this->prophesize(FactoryInterface::class);
        $this->stripeManager = $this->prophesize(StripeManager::class);

        $command = new ImportStripeFeeCommand(
            $this->orderRepository->reveal(),
            $this->entityManager->reveal(),
            $this->adjustmentFactory->reveal(),
            $this->stripeManager->reveal()
        );

        $this->commandTester = new CommandTester($command);
    }

    public function tearDown(): void
    {
        Carbon::setTestNow();
    }

    public function testWithoutOptions()
    {
        Carbon::setTestNow(Carbon::parse('2020-02-20 01:00:00'));

        $this->orderRepository
            ->findFulfilledOrdersByDate(Argument::type(\DateTimeInterface::class))
            ->willReturn([])
            ->shouldBeCalled();

        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();

        $this->assertStringContainsString('Retrieving orders fulfilled on 2020-02-19', $output);
    }

    public function testWithDateOption()
    {
        $this->orderRepository
            ->findFulfilledOrdersByDate(Argument::type(\DateTimeInterface::class))
            ->willReturn([])
            ->shouldBeCalled();

        $this->commandTester->execute([
            '--date' => '2020-02-20'
        ]);

        $output = $this->commandTester->getDisplay();

        $this->assertStringContainsString('Retrieving orders fulfilled on 2020-02-20', $output);
    }

    public function testWithDateAsMonthOption()
    {
        $this->orderRepository
            ->findFulfilledOrdersByDateRange(
                Argument::type(\DateTimeInterface::class),
                Argument::type(\DateTimeInterface::class)
            )
            ->willReturn([])
            ->shouldBeCalled();

        $this->commandTester->execute([
            '--date' => '2020-02'
        ]);

        $output = $this->commandTester->getDisplay();

        $this->assertStringContainsString('Retrieving orders fulfilled between 2020-02-01 and 2020-02-29', $output);
    }
}
