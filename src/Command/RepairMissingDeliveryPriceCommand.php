<?php

namespace AppBundle\Command;

use AppBundle\Entity\Sylius\CalculateUsingPricingRules;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Pricing\PriceReviewIncidents;
use AppBundle\Pricing\PricingManager;
use AppBundle\Sylius\Order\OrderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * One-off repair for the orders that were left without any order item.
 *
 * The price of an on demand delivery order is held by its order items, so an
 * order without any of them has no price at all, and every page showing it
 * fails on Order::getDeliveryPrice(). They were written by a re-pricing that
 * found no matching rule: it removed the previous items and added none.
 *
 * PricingManager::processDeliveryOrder() no longer leaves an order that way,
 * but the orders written before it does not fix itself.
 */
class RepairMissingDeliveryPriceCommand extends Command
{
    private SymfonyStyle $io;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PricingManager $pricingManager,
        private readonly PriceReviewIncidents $priceReviewIncidents
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('coopcycle:orders:repair-missing-delivery-price')
            ->setDescription('Prices the delivery orders that were left without any order item')
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'List the orders that would be repaired, without writing anything'
            )
            ->addOption(
                'limit',
                null,
                InputOption::VALUE_REQUIRED,
                'Repair at most this many orders'
            )
            ->addOption(
                'incidents',
                null,
                InputOption::VALUE_NONE,
                'Also report an incident for each order that could only be given a price of 0. '.
                'Off by default: an incident notifies the store webhooks, which is a lot of noise for old orders.'
            );
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun = $input->getOption('dry-run');
        $withIncidents = $input->getOption('incidents');
        $limit = $input->getOption('limit');

        $orders = $this->findOrdersWithoutItems(null !== $limit ? (int) $limit : null);

        if (0 === count($orders)) {
            $this->io->success('No order without a price, nothing to repair');

            return Command::SUCCESS;
        }

        $this->io->text(sprintf('Found %d order(s) without a price', count($orders)));

        $rows = [];
        $failed = 0;

        foreach ($orders as $order) {
            try {
                $total = $this->repair($order, $dryRun);

                $rows[] = [
                    $order->getId(),
                    $order->getNumber(),
                    $order->getDelivery()->getId(),
                    $order->getState(),
                    number_format($total / 100, 2),
                ];
            } catch (\Exception $e) {
                $failed++;

                $this->io->warning(sprintf(
                    'Could not repair order #%d: %s',
                    $order->getId(),
                    $e->getMessage()
                ));
            }
        }

        $this->io->table(
            ['Order', 'Number', 'Delivery', 'State', $dryRun ? 'Price (excl. tax)' : 'New total'],
            $rows
        );

        // The incidents are collected while the orders are priced, and created
        // on console.terminate; drop the ones that are not wanted
        if ($dryRun || !$withIncidents) {
            $this->priceReviewIncidents->discardPending();
        }

        if ($dryRun) {
            $this->io->note('Dry run, nothing was written');

            return Command::SUCCESS;
        }

        $this->entityManager->flush();

        $this->io->success(sprintf('Repaired %d order(s)', count($rows)));

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * @return OrderInterface[]
     */
    private function findOrdersWithoutItems(?int $limit): array
    {
        $qb = $this->entityManager
            ->getRepository(Order::class)
            ->createQueryBuilder('o')
            // Only on demand delivery orders: a foodtech order holds its price
            // in adjustments, and does not need an order item to be displayed
            ->innerJoin('o.delivery', 'd')
            ->andWhere('o.items IS EMPTY')
            ->andWhere('o.vendors IS EMPTY')
            ->orderBy('o.id', 'ASC');

        if (null !== $limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Prices the order again with the rules that apply to it today, which is
     * what a dispatcher re-pricing it by hand would get. When none of them
     * match, processDeliveryOrder() falls back to a price of 0 to review.
     *
     * @return int the new total, in cents; without the taxes on a dry run,
     *             as they are only calculated once the items are on the order
     */
    private function repair(OrderInterface $order, bool $dryRun): int
    {
        $delivery = $order->getDelivery();

        $productVariants = $this->pricingManager->getProductVariantsWithPricingStrategy(
            $delivery,
            new CalculateUsingPricingRules()
        );

        if ($dryRun) {
            // Leave the order alone: reporting the price the rules give today
            // is enough to tell what the repair would do
            return array_sum(array_map(
                fn ($productVariant) => $productVariant->getPrice(),
                $productVariants
            ));
        }

        $this->pricingManager->processDeliveryOrder($order, $productVariants);

        return $order->getTotal();
    }
}
