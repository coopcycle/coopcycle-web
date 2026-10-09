<?php

namespace AppBundle\Command;

use AppBundle\Entity\Loyalty\LoyaltyPointsEntry;
use AppBundle\Entity\Loyalty\LoyaltyPointsEntryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Settles credits whose expiry date has passed: zeroes what was left on them
 * and records an expiry line for the same amount.
 *
 * This does not change anyone's balance. Lapsed credits are already left out
 * of it, and out of what can be spent, by their expiry date -- so points
 * can't be spent after lapsing whether or not this has run. What it does is
 * leave the ledger self-explanatory: without it a customer sees points they
 * earned, a balance that no longer includes them, and nothing in between
 * saying why.
 *
 * Meant to run daily on a cron/scheduler, alongside the other recurring
 * maintenance commands.
 */
final class ExpireLoyaltyPointsCommand extends Command
{
    protected static $defaultName = 'coopcycle:loyalty:expire-points';

    private const BATCH_SIZE = 100;

    public function __construct(
        private readonly LoyaltyPointsEntryRepository $pointsEntryRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Settles loyalty points whose expiry date has passed')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report what would be expired, without writing anything');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun = $input->getOption('dry-run');

        $query = $this->pointsEntryRepository->createLapsedCreditsQueryBuilder()->getQuery();

        $credits = 0;
        $points = 0;

        foreach ($query->toIterable() as $credit) {
            $remaining = $credit->getRemaining();

            $credits++;
            $points += $remaining;

            if ($dryRun) {
                continue;
            }

            $expiry = LoyaltyPointsEntry::expiry($credit->getCustomer(), $remaining);
            $this->entityManager->persist($expiry);
            $credit->expire();

            if (0 === $credits % self::BATCH_SIZE) {
                $this->entityManager->flush();
                // Not clear() -- toIterable() is still walking the result,
                // and detaching the entities it hands back mid-iteration
                // would lose the changes made to them.
            }
        }

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        $output->writeln(sprintf(
            '<info>%s%d point(s) across %d credit(s) %s.</info>',
            $dryRun ? '[dry-run] ' : '',
            $points,
            $credits,
            $dryRun ? 'would be expired' : 'expired'
        ));

        return Command::SUCCESS;
    }
}
