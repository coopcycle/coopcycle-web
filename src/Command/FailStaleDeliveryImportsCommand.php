<?php

namespace AppBundle\Command;

use AppBundle\Entity\Delivery\ImportQueue as DeliveryImportQueue;
use AppBundle\Service\DeliveryImportStatusManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Closes the delivery imports that no worker is handling any more.
 *
 * An import is moved out of "pending"/"started" by the worker, either by the
 * handler finishing or by DeliveryImportFailureSubscriber when the message is
 * given up on. Neither runs if the worker never picks the message up, or dies
 * mid-import (out of memory, a deploy, the host going away), and the import
 * then spins in the admin for good.
 *
 * Meant to be run periodically.
 */
class FailStaleDeliveryImportsCommand extends Command
{
    private const DEFAULT_MINUTES = 60;

    private SymfonyStyle $io;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DeliveryImportStatusManager $statusManager,
        private readonly TranslatorInterface $translator
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('coopcycle:deliveries:fail-stale-imports')
            ->setDescription('Fails the delivery imports left unfinished by a worker that is gone')
            ->addOption(
                'older-than',
                null,
                InputOption::VALUE_REQUIRED,
                'Only fail the imports that have been running for at least this many minutes. '.
                'Keep it well above the time a large file legitimately takes.',
                self::DEFAULT_MINUTES
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

        $threshold = new \DateTime(sprintf('-%d minutes', $minutes));

        $queues = $this->findStaleImports($threshold);

        if (0 === count($queues)) {
            $this->io->success('No stale delivery import');

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
                    DeliveryImportStatusManager::errorForWholeImport($message)
                );
            }
        }

        $this->io->table(['Id', 'File', 'Status', 'Created at'], $rows);

        if ($dryRun) {
            $this->io->note('Dry run, nothing was written');

            return Command::SUCCESS;
        }

        $this->io->success(sprintf('Failed %d stale delivery import(s)', count($rows)));

        return Command::SUCCESS;
    }

    /**
     * @return DeliveryImportQueue[]
     */
    private function findStaleImports(\DateTime $threshold): array
    {
        return $this->entityManager
            ->getRepository(DeliveryImportQueue::class)
            ->createQueryBuilder('q')
            ->andWhere('q.status IN (:statuses)')
            // An import the worker never picked up is still pending, and has
            // no startedAt to go by
            ->andWhere('COALESCE(q.startedAt, q.createdAt) < :threshold')
            ->setParameter('statuses', [
                DeliveryImportQueue::STATUS_PENDING,
                DeliveryImportQueue::STATUS_STARTED,
            ])
            ->setParameter('threshold', $threshold)
            ->orderBy('q.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
