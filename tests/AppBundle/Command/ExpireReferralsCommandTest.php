<?php

namespace Tests\AppBundle\Command;

use AppBundle\Command\ExpireReferralsCommand;
use AppBundle\Entity\Referral\Referral;
use AppBundle\Entity\Referral\ReferralRepository;
use AppBundle\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Console\Tester\CommandTester;

class ExpireReferralsCommandTest extends TestCase
{
    use ProphecyTrait;

    private $referralRepository;
    private $settingsManager;
    private $entityManager;

    protected function setUp(): void
    {
        $this->referralRepository = $this->prophesize(ReferralRepository::class);
        $this->settingsManager = $this->prophesize(SettingsManager::class);
        $this->entityManager = $this->prophesize(EntityManagerInterface::class);
    }

    private function tester(): CommandTester
    {
        $command = new ExpireReferralsCommand(
            $this->referralRepository->reveal(),
            $this->settingsManager->reveal(),
            $this->entityManager->reveal(),
        );

        return new CommandTester($command);
    }

    public function testUsesConfiguredTtl(): void
    {
        $this->settingsManager->get('referral_pending_ttl_days')->willReturn('45');
        $this->referralRepository->findExpirable(45)->willReturn([]);

        $tester = $this->tester();
        $exitCode = $tester->execute([]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('0 referral(s) marked as expired', $tester->getDisplay());
    }

    public function testFallsBackToDefaultTtlWhenUnconfigured(): void
    {
        $this->settingsManager->get('referral_pending_ttl_days')->willReturn(null);
        $this->referralRepository->findExpirable(30)->willReturn([]);

        $tester = $this->tester();
        $tester->execute([]);

        $this->referralRepository->findExpirable(30)->shouldHaveBeenCalled();
    }

    public function testMarksExpirableReferralsAsExpiredAndFlushes(): void
    {
        $referralA = new Referral();
        $referralB = new Referral();

        $this->settingsManager->get('referral_pending_ttl_days')->willReturn('30');
        $this->referralRepository->findExpirable(30)->willReturn([$referralA, $referralB]);

        $this->entityManager->flush()->shouldBeCalledOnce();

        $tester = $this->tester();
        $exitCode = $tester->execute([]);

        $this->assertSame(0, $exitCode);
        $this->assertSame(Referral::STATUS_EXPIRED, $referralA->getStatus());
        $this->assertSame(Referral::STATUS_EXPIRED, $referralB->getStatus());
        $this->assertStringContainsString('2 referral(s) marked as expired', $tester->getDisplay());
    }
}
