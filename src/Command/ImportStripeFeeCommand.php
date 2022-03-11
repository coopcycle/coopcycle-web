<?php

namespace AppBundle\Command;

use AppBundle\Service\StripeManager;
use AppBundle\Sylius\OrderProcessing\OrderTaxesProcessor;
use AppBundle\Sylius\Order\AdjustmentInterface;
use Carbon\Carbon;
use Doctrine\ORM\EntityManagerInterface;
use Stripe;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ImportStripeFeeCommand extends Command
{
    private $orderRepository;
    private $orderManager;

    public function __construct(
        RepositoryInterface $orderRepository,
        EntityManagerInterface $orderManager,
        FactoryInterface $adjustmentFactory,
        StripeManager $stripeManager)
    {
        $this->orderRepository = $orderRepository;
        $this->orderManager = $orderManager;
        $this->adjustmentFactory = $adjustmentFactory;
        $this->stripeManager = $stripeManager;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('coopcycle:orders:import-stripe-fee')
            ->setDescription('Import Stripe fee')
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Dry run'
            )
            ->addOption(
                'date',
                'd',
                InputOption::VALUE_REQUIRED,
                'Date'
            )
            ->addOption(
                'order',
                'o',
                InputOption::VALUE_REQUIRED,
                'Order'
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Overwrite Stripe fees even if they were already imported'
            );
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    /**
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->stripeManager->configure();

        $options = array_filter($input->getOptions());

        if (isset($options['order']) && isset($options['date'])) {
            $this->io->title('Options "order" & "date" are mutually exclusive');

            return 1;
        }

        $this->io->title('Importing Stripe fees');

        $dryRun = $input->getOption('dry-run');
        $force = $input->getOption('force');

        $orders = [];

        if (isset($options['order'])) {

            $this->io->text(sprintf('Retrieving order #%d', $options['order']));
            $orders[] = $this->orderRepository->find($options['order']);

        } else {

            $date = $this->getDate($input);

            if (is_array($date)) {
                [ $start, $end ] = $date;
                $this->io->text(sprintf('Retrieving orders fulfilled between %s and %s',
                    $start->format('Y-m-d'), $end->format('Y-m-d')));

                $orders = $this->orderRepository->findFulfilledOrdersByDateRange($start, $end);
            } else {
                $this->io->text(sprintf('Retrieving orders fulfilled on %s',
                    $date->format('Y-m-d')));

                $orders = $this->orderRepository->findFulfilledOrdersByDate($date);
            }
        }

        $this->io->text(sprintf('Found %d orders to process', count($orders)));

        foreach ($orders as $order) {

            $stripeFeeAdjustments = $order->getAdjustments(AdjustmentInterface::STRIPE_FEE_ADJUSTMENT);

            if (count($stripeFeeAdjustments) > 0 && !$force) {
                $this->io->section(sprintf('Stripe fees for order #%d already imported, skipping…', $order->getId()));
                continue;
            }

            $this->io->section(sprintf('Importing Stripe fees for order #%d', $order->getId()));

            $lastCompletedPayment = $order->getLastPayment('completed');

            if (!$lastCompletedPayment) {
                $lastPayment = $order->getLastPayment();
                if ($lastPayment) {
                    $this->io->text(sprintf('Last payment is in state %s, skipping…', $lastPayment->getState()));
                } else {
                    $this->io->text('Order has no payment associated, skipping…');
                }
                continue;
            }

            $stripeUserId = $lastCompletedPayment->getStripeUserId();

            $stripeOptions = [];
            if ($stripeUserId) {
                $stripeOptions['stripe_account'] = $stripeUserId;
            }

            try {

                $charge = null;
                $stripeFee = 0;

                $paymentIntent = $lastCompletedPayment->getPaymentIntent();
                if (null !== $paymentIntent) {
                    $intent = Stripe\PaymentIntent::retrieve($paymentIntent, $stripeOptions);
                    if (count($intent->charges->data) === 1) {
                        $charge = current($intent->charges->data);
                    }
                } else {
                    $charge = Stripe\Charge::retrieve($lastCompletedPayment->getCharge(), $stripeOptions);
                }

                if (null === $charge) {
                    $this->io->text('No charge was found');
                    continue;
                }

                $balanceTransaction = Stripe\BalanceTransaction::retrieve($charge->balance_transaction, $stripeOptions);

                foreach ($balanceTransaction->fee_details as $feeDetail) {
                    if ('stripe_fee' === $feeDetail->type) {
                        $stripeFee = $feeDetail->amount;
                        break;
                    }
                }

                if ($stripeFee > 0) {
                    $order->removeAdjustments(AdjustmentInterface::STRIPE_FEE_ADJUSTMENT);

                    $this->io->text(sprintf('Stripe fee = %d', $stripeFee));

                    $stripeFeeAdjustment = $this->adjustmentFactory->createWithData(
                        AdjustmentInterface::STRIPE_FEE_ADJUSTMENT,
                        'Stripe fee',
                        $stripeFee,
                        $neutral = true
                    );
                    $order->addAdjustment($stripeFeeAdjustment);

                    if (!$dryRun) {
                        $this->orderManager->flush();
                    }
                }

            } catch (\Exception $e) {
                $this->io->caution($e->getMessage());
            }
        }

        return 0;
    }

    private function getDate($input)
    {
        $dateOption = $input->getOption('date');

        if ($dateOption) {

            if (1 === preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/', $dateOption, $matches)) {
                return new \DateTime($dateOption);
            }

            if (1 === preg_match('/^([0-9]{4})-([0-9]{2})$/', $dateOption, $matches)) {
                $year = $matches[1];
                $month = $matches[2];

                $start = new \DateTime();
                $start->setDate($year, $month, 1);
                $start->setTime(0, 0, 0);

                $end = clone $start;
                $end->setDate($start->format('Y'), $start->format('m'), $start->format('t'));
                $end->setTime(23, 59, 59);

                return [ $start, $end ];
            }


            return false;
        }

        return Carbon::yesterday();
    }
}
