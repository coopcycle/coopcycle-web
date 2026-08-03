<?php

namespace Tests\AppBundle\Command;

use AppBundle\Command\GenerateCouponsCommand;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Sylius\Component\Promotion\Generator\PromotionCouponGeneratorInterface;
use Sylius\Component\Promotion\Model\PromotionInterface;
use Sylius\Component\Promotion\Repository\PromotionRepositoryInterface;
use Symfony\Component\Console\Tester\CommandTester;

class GenerateCouponsCommandTest extends TestCase
{
    use ProphecyTrait;

    private $promotionRepository;
    private $couponGenerator;
    private $promotion;

    /** Holder mutated from the generate() stub (Prophecy rebinds $this in the closure). */
    private $capture;

    public function setUp(): void
    {
        $this->promotionRepository = $this->prophesize(PromotionRepositoryInterface::class);
        $this->couponGenerator = $this->prophesize(PromotionCouponGeneratorInterface::class);
        $this->promotion = $this->prophesize(PromotionInterface::class);

        $this->promotion->isCouponBased()->willReturn(true);
        $this->promotionRepository->findOneBy(['code' => 'SUMMER26'])
            ->willReturn($this->promotion->reveal());

        // Capture the instruction handed to the generator so we can assert on it.
        $capture = new \stdClass();
        $capture->instruction = null;
        $this->capture = $capture;
        $this->couponGenerator
            ->generate(Argument::type(PromotionInterface::class), Argument::any())
            ->will(function ($args) use ($capture) {
                $capture->instruction = $args[1];

                return [];
            });
    }

    private function tester(): CommandTester
    {
        $command = new GenerateCouponsCommand(
            $this->promotionRepository->reveal(),
            $this->couponGenerator->reveal(),
        );

        return new CommandTester($command);
    }

    public function testPrefixAndSuffixOptionsFlowIntoInstruction()
    {
        $tester = $this->tester();
        $exitCode = $tester->execute([
            'promotion-code' => 'SUMMER26',
            'count' => '37000',
            '--length' => '5',
            '--prefix' => 'SUM',
            '--suffix' => '_FR',
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Coupons have been generated', $tester->getDisplay());

        $this->assertNotNull($this->capture->instruction);
        $this->assertSame(37000, $this->capture->instruction->getAmount());
        $this->assertSame(5, $this->capture->instruction->getCodeLength());
        $this->assertSame('SUM', $this->capture->instruction->getPrefix());
        $this->assertSame('_FR', $this->capture->instruction->getSuffix());
    }

    public function testPrefixAndSuffixDefaultToNullWhenOmitted()
    {
        $tester = $this->tester();
        $tester->execute([
            'promotion-code' => 'SUMMER26',
            'count' => '100',
        ]);

        $this->assertNull($this->capture->instruction->getPrefix());
        $this->assertNull($this->capture->instruction->getSuffix());
        // Default length is 10 when --length is omitted.
        $this->assertSame(10, $this->capture->instruction->getCodeLength());
    }

    public function testFailsWhenPromotionNotFound()
    {
        $this->promotionRepository->findOneBy(['code' => 'MISSING'])->willReturn(null);

        $tester = $this->tester();
        $exitCode = $tester->execute([
            'promotion-code' => 'MISSING',
            'count' => '100',
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('No promotion found', $tester->getDisplay());
    }

    public function testFailsWhenPromotionNotCouponBased()
    {
        $this->promotion->isCouponBased()->willReturn(false);

        $tester = $this->tester();
        $exitCode = $tester->execute([
            'promotion-code' => 'SUMMER26',
            'count' => '100',
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('not coupon based', $tester->getDisplay());
    }
}
