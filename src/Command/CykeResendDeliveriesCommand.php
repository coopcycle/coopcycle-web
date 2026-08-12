<?php

namespace AppBundle\Command;

use AppBundle\Entity\Cyke\Delivery as CykeDelivery;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Store;
use AppBundle\Message\DeliveryCreated;
use AppBundle\MessageHandler\CreateCykeDelivery;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CykeResendDeliveriesCommand extends Command
{
    private SymfonyStyle $io;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private CreateCykeDelivery $createCykeDelivery,
        private bool $cykeEnabled = false)
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('coopcycle:cyke:resend-deliveries')
            ->setDescription('Re-send to Cyke the deliveries of Cyke-enabled stores that were never accepted (no Cyke mapping yet).')
            ->addOption(
                'since',
                null,
                InputOption::VALUE_REQUIRED,
                'Only consider deliveries created on or after this date (e.g. "2026-07-24" or "-3 days").'
            )
            ->addOption(
                'store',
                null,
                InputOption::VALUE_REQUIRED,
                'Restrict to a single store id.'
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'List the deliveries that would be re-sent without actually sending them.'
            );
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun = (bool) $input->getOption('dry-run');

        if (!$this->cykeEnabled) {
            $this->io->warning('Cyke integration is disabled (CYKE_ENABLED=0). Nothing to do.');

            return Command::SUCCESS;
        }

        $since = null;
        if (null !== $input->getOption('since')) {
            try {
                $since = new \DateTime($input->getOption('since'));
            } catch (\Exception $e) {
                $this->io->error(sprintf('Invalid --since value "%s": %s', $input->getOption('since'), $e->getMessage()));

                return Command::FAILURE;
            }
        }

        // isCykeEnabled() combines several nullable columns, so we filter the
        // (small) list of stores in PHP rather than trying to express it in SQL.
        $stores = array_filter(
            $this->entityManager->getRepository(Store::class)->findAll(),
            fn(Store $store): bool => $store->isCykeEnabled()
        );

        if (null !== $input->getOption('store')) {
            $storeId = (int) $input->getOption('store');
            $stores = array_filter($stores, fn(Store $store): bool => $store->getId() === $storeId);

            if (empty($stores)) {
                $this->io->error(sprintf('Store #%d is not Cyke-enabled (or does not exist).', $storeId));

                return Command::FAILURE;
            }
        }

        $this->io->text(sprintf('%d Cyke-enabled store(s)', count($stores)));

        if (empty($stores)) {
            return Command::SUCCESS;
        }

        $deliveries = $this->findDeliveriesWithoutMapping($stores, $since);

        if (empty($deliveries)) {
            $this->io->success('No deliveries to re-send.');

            return Command::SUCCESS;
        }

        $sent = 0;
        $failed = 0;

        foreach ($deliveries as $delivery) {

            if ($dryRun) {
                $this->io->text(sprintf('Delivery #%d  no mapping  -> would re-send', $delivery->getId()));
                continue;
            }

            // The handler is idempotent (it no-ops when a mapping already exists)
            // and swallows its own HTTP errors, so we re-check for a mapping
            // afterwards to report the real outcome.
            ($this->createCykeDelivery)(new DeliveryCreated($delivery));

            $mapping = $this->entityManager
                ->getRepository(CykeDelivery::class)
                ->findOneBy(['delivery' => $delivery]);

            if (null !== $mapping) {
                $sent++;
                $this->io->text(sprintf('Delivery #%d  -> sent (Cyke id %s)', $delivery->getId(), $mapping->getCykeId()));
            } else {
                $failed++;
                $this->io->text(sprintf('Delivery #%d  -> failed (see cyke log)', $delivery->getId()));
            }
        }

        if ($dryRun) {
            $this->io->success(sprintf('%d delivery(ies) would be re-sent (dry run).', count($deliveries)));

            return Command::SUCCESS;
        }

        $this->io->success(sprintf('%d re-sent, %d failed.', $sent, $failed));

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * @param Store[] $stores
     *
     * @return Delivery[]
     */
    private function findDeliveriesWithoutMapping(array $stores, ?\DateTimeInterface $since): array
    {
        $qb = $this->entityManager->getRepository(Delivery::class)->createQueryBuilder('d');

        $qb
            ->leftJoin(CykeDelivery::class, 'cd', 'WITH', 'cd.delivery = d')
            ->andWhere('cd.id IS NULL')
            ->andWhere('d.store IN (:stores)')
            ->setParameter('stores', $stores)
            ->orderBy('d.id', 'ASC');

        if (null !== $since) {
            $qb
                ->andWhere('d.createdAt >= :since')
                ->setParameter('since', $since);
        }

        return $qb->getQuery()->getResult();
    }
}
