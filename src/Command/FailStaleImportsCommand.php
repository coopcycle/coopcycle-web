<?php

namespace AppBundle\Command;

use AppBundle\Entity\ImportQueue;
use AppBundle\Service\ImportStatusManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Closes the imports that no worker is handling any more.
 *
 * An import is moved out of "pending"/"started" by the worker, either by the
 * handler finishing or by ImportFailureSubscriber when the message is
 * given up on. Neither runs if the worker never picks the message up, or dies
 * mid-import (out of memory, a deploy, the host going away), and the import
 * then spins in the admin for good.
 *
 * It also closes the imports left waiting for a dispatcher to map their columns, which
 * no worker will ever pick up: those are cancelled rather than failed, nothing of them
 * having been read, let alone imported.
 *
 * Meant to be run periodically.
 */
class FailStaleImportsCommand extends Command
{
    private const DEFAULT_MINUTES = 60;

    /**
     * An import waiting to be mapped is waiting for a person, who may well be back after
     * lunch: much longer than the time a worker is given to report its progress.
     */
    private const DEFAULT_MAPPING_MINUTES = 24 * 60;

    private SymfonyStyle $io;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ImportStatusManager $statusManager,
        private readonly TranslatorInterface $translator
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('coopcycle:imports:fail-stale')
            // The old name, which the deployments' cron entries still call
            ->setAliases(['coopcycle:deliveries:fail-stale-imports'])
            ->setDescription('Fails the imports left unfinished by a worker that is gone')
            ->addOption(
                'older-than',
                null,
                InputOption::VALUE_REQUIRED,
                'Only fail the imports that have not moved for at least this many minutes. '.
                'A running import reports its progress every few rows, so this can stay well below the time a large file takes.',
                self::DEFAULT_MINUTES
            )
            ->addOption(
                'mapping-older-than',
                null,
                InputOption::VALUE_REQUIRED,
                'Only cancel the imports waiting for their columns to be mapped when they have been waiting '.
                'for at least this many minutes. They wait for a dispatcher, so give them much longer.',
                self::DEFAULT_MAPPING_MINUTES
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'List the imports that would be failed, without writing anything'
            );
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun = $input->getOption('dry-run');
        $minutes = (int) $input->getOption('older-than');

        if ($minutes < 1) {
            $this->io->error('--older-than must be a number of minutes greater than 0');

            return Command::INVALID;
        }

        $mappingMinutes = (int) $input->getOption('mapping-older-than');

        if ($mappingMinutes < 1) {
            $this->io->error('--mapping-older-than must be a number of minutes greater than 0');

            return Command::INVALID;
        }

        $threshold = new \DateTime(sprintf('-%d minutes', $minutes));

        $abandoned = $this->cancelAbandonedMappings(
            new \DateTime(sprintf('-%d minutes', $mappingMinutes)),
            $dryRun
        );

        $queues = $this->findStaleImports($threshold);

        if (0 === count($queues)) {
            if (0 === $abandoned) {
                $this->io->success('No stale import');
            }

            return Command::SUCCESS;
        }

        $message = $this->translator->trans('deliveries.imports.error.stalled');

        $rows = [];

        foreach ($queues as $queue) {
            $rows[] = [
                $queue->getId(),
                $queue->getFilename(),
                $queue->getStatus(),
                $queue->getCreatedAt()->format('Y-m-d H:i:s'),
            ];

            if (!$dryRun) {
                $this->statusManager->failed(
                    $queue,
                    ImportStatusManager::errorForWholeImport($message)
                );
            }
        }

        $this->io->table(['Id', 'File', 'Status', 'Created at'], $rows);

        if ($dryRun) {
            $this->io->note('Dry run, nothing was written');

            return Command::SUCCESS;
        }

        $this->io->success(sprintf('Failed %d stale import(s)', count($rows)));

        return Command::SUCCESS;
    }

    /**
     * Closes the imports nobody came back to map.
     *
     * Cancelled, not failed: a failed import is shown as one that went wrong, offers its
     * errors and its rows to download, and is told to be imported again. None of that
     * means anything for a file that was never even read.
     *
     * @return int how many were cancelled
     */
    private function cancelAbandonedMappings(\DateTime $threshold, bool $dryRun): int
    {
        /** @var ImportQueue[] $queues */
        $queues = $this->entityManager
            ->getRepository(ImportQueue::class)
            ->createQueryBuilder('q')
            ->andWhere('q.status = :mapping')
            ->andWhere('q.createdAt < :threshold')
            ->setParameter('mapping', ImportQueue::STATUS_MAPPING)
            ->setParameter('threshold', $threshold)
            ->orderBy('q.id', 'ASC')
            ->getQuery()
            ->getResult();

        if (0 === count($queues)) {
            return 0;
        }

        $rows = [];

        foreach ($queues as $queue) {
            $rows[] = [
                $queue->getId(),
                $queue->getFilename(),
                $queue->getCreatedAt()->format('Y-m-d H:i:s'),
            ];

            if (!$dryRun) {
                $this->statusManager->cancel($queue);
            }
        }

        $this->io->table(['Id', 'File', 'Created at'], $rows);

        if ($dryRun) {
            $this->io->note(sprintf('Dry run, %d import(s) waiting to be mapped were left alone', count($rows)));
        } else {
            $this->io->success(sprintf('Cancelled %d import(s) nobody came back to map', count($rows)));
        }

        return count($rows);
    }

    /**
     * @return ImportQueue[]
     */
    private function findStaleImports(\DateTime $threshold): array
    {
        return $this->entityManager
            ->getRepository(ImportQueue::class)
            ->createQueryBuilder('q')
            ->andWhere('q.status IN (:statuses)')
            // A large import takes hours, what tells it from a dead one is that it keeps
            // reporting its progress. An import the worker never picked up is still
            // pending, and has neither progressedAt nor startedAt to go by.
            // While saving, nothing is reported: a file is saved in a single transaction,
            // the progress written meanwhile would not be seen. progressedAt is when
            // saving started, and a worker dying then rolls it all back.
            ->andWhere('COALESCE(q.progressedAt, q.startedAt, q.createdAt) < :threshold')
            ->setParameter('statuses', [
                ImportQueue::STATUS_PENDING,
                ImportQueue::STATUS_STARTED,
                ImportQueue::STATUS_SAVING,
            ])
            ->setParameter('threshold', $threshold)
            ->orderBy('q.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
