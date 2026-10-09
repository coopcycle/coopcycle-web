<?php

namespace AppBundle\Command;

use AppBundle\Entity\Sylius\Customer;
use AppBundle\Service\Referral\ReferralCodeGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * One-off, manually-run backfill for customers who signed up before the
 * referral program shipped -- ReferralManager generates codes eagerly for
 * every signup going forward, and ProfileController::referralsAction()
 * backfills lazily on first visit to /profile/referrals, so this command
 * only matters for the gap: pre-existing customers who never visit that
 * page but might still want to be referenceable by e.g. a future
 * marketing-automation "invite your Champions" flow.
 */
final class BackfillReferralCodesCommand extends Command
{
    protected static $defaultName = 'coopcycle:referral:backfill-codes';

    private const BATCH_SIZE = 100;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ReferralCodeGenerator $referralCodeGenerator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Generates a referral code for every customer who does not have one yet')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Count customers that would be updated, without writing anything');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun = $input->getOption('dry-run');

        $query = $this->entityManager->createQueryBuilder()
            ->select('c')
            ->from(Customer::class, 'c')
            ->andWhere('c.referralCode IS NULL')
            ->getQuery();

        $count = 0;

        foreach ($query->toIterable() as $customer) {
            if (!$dryRun) {
                $this->referralCodeGenerator->generateFor($customer);
            }

            $count++;

            if (!$dryRun && 0 === $count % self::BATCH_SIZE) {
                $this->entityManager->flush();
                $this->entityManager->clear();
            }
        }

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        $output->writeln(sprintf(
            '<info>%s%d customer(s) %s a referral code.</info>',
            $dryRun ? '[dry-run] ' : '',
            $count,
            $dryRun ? 'would receive' : 'received'
        ));

        return Command::SUCCESS;
    }
}
