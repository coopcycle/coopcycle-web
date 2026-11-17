<?php

namespace AppBundle\Command;

use AppBundle\Entity\OrderTransferRepository;
use AppBundle\Entity\Sylius\OrderRepository;
use AppBundle\Message\GiftCard\SettleGiftCardOrder;
use AppBundle\Sylius\Order\OrderInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Pays restaurants for gift-card orders that fell through the cracks.
 *
 * Settlement normally runs off Stripe's charge.captured webhook, or off the
 * capture itself when a card covered the whole order and there was no charge
 * to capture. Neither is guaranteed: a webhook can be dropped, a worker can
 * die between capturing and dispatching, and an instance can simply have its
 * webhook misconfigured.
 *
 * Without this, the symptom is a restaurant quietly not being paid, noticed
 * weeks later by whoever reconciles the accounts. The guard against paying
 * twice is the OrderTransfer row, not this command's judgement: it only
 * dispatches, and settlement itself refuses to run on an order that already
 * has a successful transfer.
 */
final class SettlePendingGiftCardOrdersCommand extends Command
{
    protected static $defaultName = 'coopcycle:gift-cards:settle-pending';

    /**
     * How long to let the normal path finish before stepping in. Long enough
     * that a slow webhook is not mistaken for a lost one.
     */
    private const GRACE_MINUTES = 30;

    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly OrderTransferRepository $orderTransferRepository,
        private readonly MessageBusInterface $messageBus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Settles gift-card orders whose transfer never went out')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report what would be settled, without dispatching anything');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun = $input->getOption('dry-run');

        $orders = $this->orderRepository->createQueryBuilder('o')
            ->andWhere('o.giftCard IS NOT NULL')
            ->andWhere('o.state = :fulfilled')
            ->andWhere('o.updatedAt <= :before')
            ->setParameter('fulfilled', OrderInterface::STATE_FULFILLED)
            ->setParameter('before', new \DateTime(sprintf('-%d minutes', self::GRACE_MINUTES)))
            ->getQuery()
            ->toIterable();

        $pending = 0;

        foreach ($orders as $order) {

            if ($this->orderTransferRepository->hasSucceededTransfer($order)) {
                continue;
            }

            $pending++;

            $output->writeln(sprintf(
                '%s order #%d (%s)',
                $dryRun ? '[dry-run] would settle' : 'Settling',
                $order->getId(),
                $order->getNumber() ?? '?'
            ));

            if (!$dryRun) {
                // No charge id: by this point the complement charge is long
                // captured, so the transfer is sourced from the gift card's
                // purchase charges, or goes out untied.
                $this->messageBus->dispatch(new SettleGiftCardOrder($order->getId()));
            }
        }

        $output->writeln(sprintf('%d order(s) still unsettled', $pending));

        return Command::SUCCESS;
    }
}
