<?php

namespace AppBundle\Command;

use AppBundle\Entity\Referral\ReferralRepository;
use AppBundle\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Sweeps pending referrals whose referred customer never placed a first
 * order within the configured TTL, marking them expired so the admin
 * dashboard's conversion-rate stat reflects referrals that didn't convert
 * instead of counting them as still-pending forever.
 *
 * Meant to run daily on a cron/scheduler, alongside the other recurring
 * maintenance commands.
 */
final class ExpireReferralsCommand extends Command
{
    protected static $defaultName = 'coopcycle:referral:expire';

    private const DEFAULT_TTL_DAYS = 30;

    public function __construct(
        private readonly ReferralRepository $referralRepository,
        private readonly SettingsManager $settingsManager,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Marks pending referrals as expired once they are older than the configured TTL');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $ttlDays = (int) ($this->settingsManager->get('referral_pending_ttl_days') ?: self::DEFAULT_TTL_DAYS);

        $expirable = $this->referralRepository->findExpirable($ttlDays);

        foreach ($expirable as $referral) {
            $referral->markAsExpired();
        }

        $this->entityManager->flush();

        $output->writeln(sprintf('<info>%d referral(s) marked as expired.</info>', count($expirable)));

        return Command::SUCCESS;
    }
}
