<?php

namespace AppBundle\Command;

use AppBundle\Entity\Sylius\Customer;
use Doctrine\ORM\EntityManagerInterface;
use RZ\CanonicalEmail\EmailCanonizer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * One-off, manually-run backfill for customers created before
 * Customer::$referralCanonicalEmail existed. CustomerListener keeps it in
 * sync from here on, but until this has run, an account predating the column
 * won't be recognised as the one a new alias is impersonating -- so the
 * referral program would still reward farming it.
 */
final class BackfillReferralCanonicalEmailsCommand extends Command
{
    protected static $defaultName = 'coopcycle:referral:backfill-canonical-emails';

    private const BATCH_SIZE = 100;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EmailCanonizer $emailCanonizer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Computes the canonical email of every customer that does not have one yet')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Count customers that would be updated, without writing anything');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun = $input->getOption('dry-run');

        $query = $this->entityManager->createQueryBuilder()
            ->select('c')
            ->from(Customer::class, 'c')
            ->andWhere('c.referralCanonicalEmail IS NULL')
            ->andWhere('c.email IS NOT NULL')
            ->getQuery();

        $count = 0;

        foreach ($query->toIterable() as $customer) {
            if (!$dryRun) {
                $customer->setReferralCanonicalEmail(
                    $this->emailCanonizer->getCanonicalEmailAddress($customer->getEmail())
                );
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
            '<info>%s%d customer(s) %s a canonical email.</info>',
            $dryRun ? '[dry-run] ' : '',
            $count,
            $dryRun ? 'would receive' : 'received'
        ));

        return Command::SUCCESS;
    }
}
